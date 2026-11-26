// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Listens to Bunny Stream players on the page and reports what the student does to Moodle.
 *
 * @module     local_bunnyanalytics/tracker
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* global M */

const IFRAME_RE = /mediadelivery\.net\/(?:embed|play)\/(\d+)\/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i;
const SEGMENT_GAP = 3; // A jump bigger than this (seconds) is a seek, not normal playback.
const REWIND_MIN = 5; // Jumping back more than this counts as a rewind.
const MAX_QUEUE = 200;
const SCRUB_WINDOW = 1200; // Jumps closer together than this (ms) are one drag of the timeline.
const STALL_MS = 2500; // Time not moving for this long while playing counts as a pause.

let config = null;
let playerjsPromise = null;
const trackers = [];

const round1 = (n) => Math.round(Number(n) * 10) / 10;
const randomId = () => Math.random().toString(36).slice(2, 12) + Date.now().toString(36);

/**
 * Loads Bunny's player.js through RequireJS (a plain script tag would clash with Moodle's AMD loader).
 *
 * @returns {Promise<Object>}
 */
const loadPlayerJs = () => {
    if (!playerjsPromise) {
        playerjsPromise = new Promise((resolve, reject) => {
            if (window.playerjs) {
                resolve(window.playerjs);
                return;
            }
            window.require([config.playerjsurl], (mod) => resolve(mod || window.playerjs), reject);
        });
    }
    return playerjsPromise;
};

/**
 * Sends a batch to the Moodle web service. keepalive lets it finish even if the page is closing.
 *
 * @param {Object} args
 */
const send = (args) => {
    const url = M.cfg.wwwroot + '/lib/ajax/service.php?sesskey=' + encodeURIComponent(M.cfg.sesskey) +
        '&info=local_bunnyanalytics_log_events';
    const body = JSON.stringify([{index: 0, methodname: 'local_bunnyanalytics_log_events', args}]);
    try {
        fetch(url, {
            method: 'POST',
            body,
            keepalive: true,
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
        }).catch(() => null);
    } catch (e) {
        // Tracking must never break the page.
    }
};

class VideoTracker {
    /**
     * @param {HTMLIFrameElement} iframe
     * @param {Object} playerjs
     * @param {string} libraryid
     * @param {string} videoguid
     */
    constructor(iframe, playerjs, libraryid, videoguid) {
        this.libraryid = parseInt(libraryid, 10);
        this.videoguid = videoguid.toLowerCase();
        this.sessionid = randomId();
        this.queue = [];
        this.segments = [];
        this.segStart = null;
        this.lastPos = 0;
        this.duration = 0;
        this.ended = false;
        this.justReplayed = false;
        this.hasPos = false;
        this.playing = false;
        this.lastTick = 0;
        this.lastAdvance = 0;
        this.scrub = null;
        this.lastSent = Date.now();

        // The Player must exist before the iframe loads, or it misses the "ready" handshake.
        // So we create it first, then reload the iframe. A unique src param also avoids
        // player.js mixing up two players of the same video on one page.
        this.player = new playerjs.Player(iframe);
        const url = new URL(iframe.src, window.location.href);
        url.searchParams.set('ba', this.sessionid);
        iframe.src = url.toString();

        this.player.on('ready', () => this.bind());
    }

    bind() {
        const p = this.player;
        p.getDuration((d) => {
            this.duration = Number(d) || this.duration;
        });
        // Bunny's player does not always send play/pause, so these events and the time
        // updates both feed the same playing/paused state, and each change is counted once.
        p.on('play', () => this.markPlaying());
        p.on('pause', () => this.markPaused());
        p.on('ended', () => {
            this.finishScrub();
            this.closeSegment(false);
            this.playing = false;
            if (!this.ended) {
                this.ended = true;
                this.push('ended', this.duration || this.lastPos);
            }
            this.flush();
        });
        p.on('timeupdate', (data) => this.onTime(data || {}));

        // Detects pauses the player didn't report: time stopped moving while we thought it was playing.
        this.stallTimer = setInterval(() => {
            if (this.playing && Date.now() - this.lastAdvance > STALL_MS) {
                this.markPaused();
            }
        }, 1000);
    }

    markPlaying() {
        if (this.playing) {
            return;
        }
        this.playing = true;
        this.lastAdvance = Date.now();
        if (this.ended) {
            this.ended = false;
            this.justReplayed = true;
            this.push('replay', 0);
            this.push('play', 0);
        } else {
            this.push('play', this.lastPos);
        }
    }

    markPaused() {
        if (!this.playing) {
            return;
        }
        this.playing = false;
        this.finishScrub();
        this.closeSegment(false);
        // Stopping at the very end is the video finishing, not a pause.
        if (this.duration && this.lastPos >= this.duration - 1) {
            if (!this.ended) {
                this.ended = true;
                this.push('ended', this.duration);
                this.flush();
            }
            return;
        }
        this.push('pause', this.lastPos);
        this.flush();
    }

    /**
     * Records the jump, merging rapid jumps (dragging the timeline) into one action.
     *
     * @param {number} from
     * @param {number} to
     */
    noteJump(from, to) {
        const now = Date.now();
        if (this.scrub && now - this.scrub.at < SCRUB_WINDOW) {
            this.scrub.to = to;
            this.scrub.at = now;
        } else {
            this.finishScrub();
            this.scrub = {from, to, at: now};
        }
        clearTimeout(this.scrubTimer);
        this.scrubTimer = setTimeout(() => this.finishScrub(), SCRUB_WINDOW);
    }

    finishScrub() {
        clearTimeout(this.scrubTimer);
        if (!this.scrub) {
            return;
        }
        const {from, to} = this.scrub;
        this.scrub = null;
        const delta = to - from;
        if (delta < -REWIND_MIN) {
            this.push('rewind', to);
        } else if (delta > SEGMENT_GAP) {
            this.push('seek', to);
        }
    }

    /**
     * @param {{seconds: number, duration: number}} data
     */
    onTime(data) {
        const t = Number(data.seconds) || 0;
        if (data.duration) {
            this.duration = Number(data.duration);
        }
        const now = Date.now();
        const elapsed = this.lastTick ? (now - this.lastTick) / 1000 : 0;
        this.lastTick = now;
        const delta = t - this.lastPos;
        const jumped = this.hasPos && (delta < -1 || delta > SEGMENT_GAP);

        if (jumped) {
            // Close the old range and start a new one where they landed.
            if (this.segStart !== null) {
                this.addSegment(this.segStart, this.lastPos);
            }
            // Going back to 0 after the end is a replay, not a rewind.
            if (!this.justReplayed && !(this.ended && t < 2)) {
                this.noteJump(this.lastPos, t);
            }
            this.segStart = t;
        } else {
            if (this.segStart === null) {
                this.segStart = t;
            }
            // Time moving forward at roughly real speed means the video is playing.
            if (delta > 0.05 && elapsed > 0 && delta < elapsed * 2.5 + 0.3) {
                this.lastAdvance = now;
                this.markPlaying();
            }
        }
        this.hasPos = true;
        this.justReplayed = false;
        this.lastPos = t;

        if (now - this.lastSent > config.flushinterval * 1000) {
            this.closeSegment(true);
            this.flush();
        }
    }

    addSegment(start, end) {
        if (end - start > 0.5) {
            this.segments.push({start: round1(start), end: round1(end)});
        }
    }

    /**
     * @param {boolean} keepOpen continue the current range from here (still playing)
     */
    closeSegment(keepOpen) {
        if (this.segStart !== null) {
            this.addSegment(this.segStart, this.lastPos);
        }
        this.segStart = keepOpen ? this.lastPos : null;
    }

    push(type, position) {
        if (this.queue.length < MAX_QUEUE) {
            this.queue.push({type, position: round1(position || 0), time: Math.floor(Date.now() / 1000)});
        }
    }

    flush() {
        this.lastSent = Date.now();
        if (!this.queue.length && !this.segments.length) {
            return;
        }
        send({
            courseid: config.courseid,
            cmid: config.cmid,
            libraryid: this.libraryid,
            videoguid: this.videoguid,
            sessionid: this.sessionid,
            duration: round1(this.duration),
            position: round1(this.lastPos),
            events: this.queue.splice(0),
            segments: this.segments.splice(0),
        });
    }
}

/**
 * Finds Bunny iframes not yet tracked and attaches a tracker to each.
 *
 * @param {Object} playerjs
 */
const attach = (playerjs) => {
    document.querySelectorAll('iframe').forEach((iframe) => {
        if (iframe.dataset.bunnyTracked) {
            return;
        }
        const match = IFRAME_RE.exec(iframe.getAttribute('src') || '');
        if (!match) {
            return;
        }
        iframe.dataset.bunnyTracked = '1';
        trackers.push(new VideoTracker(iframe, playerjs, match[1], match[2]));
    });
};

const hasBunnyIframe = () => Array.from(document.querySelectorAll('iframe'))
    .some((f) => !f.dataset.bunnyTracked && IFRAME_RE.test(f.getAttribute('src') || ''));

const flushAll = () => trackers.forEach((t) => {
    t.finishScrub();
    t.closeSegment(true);
    t.flush();
});

/**
 * Entry point, called from the plugin's footer hook.
 *
 * @param {Object} cfg {courseid, cmid, playerjsurl, flushinterval}
 */
export const init = (cfg) => {
    if (config) {
        return;
    }
    config = cfg;

    const start = () => loadPlayerJs().then((playerjs) => {
        attach(playerjs);
        return playerjs;
    }).catch(() => null);

    if (hasBunnyIframe()) {
        start();
    }

    // Videos added later (collapsed sections, Book chapters loaded dynamically, etc.).
    new MutationObserver(() => {
        if (hasBunnyIframe()) {
            start();
        }
    }).observe(document.body, {childList: true, subtree: true});

    // Send whatever is pending when the student leaves or switches tab.
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            flushAll();
        }
    });
    window.addEventListener('pagehide', flushAll);
};
