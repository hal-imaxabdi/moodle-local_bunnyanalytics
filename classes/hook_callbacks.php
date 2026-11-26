<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_bunnyanalytics;

/**
 * Hook callbacks.
 *
 * @package    local_bunnyanalytics
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Loads the player tracker on course pages for logged-in users.
     *
     * Always leaves an HTML comment "bunnyanalytics: <status>" in the page footer, so you can
     * see in the browser why the tracker did or did not load.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function before_footer(\core\hook\output\before_footer_html_generation $hook): void {
        global $PAGE;

        try {
            $status = self::load_tracker($PAGE);
        } catch (\Throwable $e) {
            $status = 'error ' . $e->getMessage();
        }
        $status = preg_replace('/[^A-Za-z0-9 _.:=\/-]/', '', $status);
        $hook->add_html("<!-- bunnyanalytics: {$status} -->");
    }

    /**
     * Adds the tracker JS if this page should be tracked.
     *
     * @param \moodle_page $page
     * @return string status for the HTML comment
     */
    protected static function load_tracker(\moodle_page $page): string {
        if (during_initial_install()) {
            return 'skipped install';
        }
        // Treat "never saved" as enabled; only an explicit untick turns tracking off.
        if ((string) get_config('local_bunnyanalytics', 'enabled') === '0') {
            return 'skipped disabled';
        }
        if (!isloggedin() || isguestuser()) {
            return 'skipped notloggedin';
        }

        $courseid = 0;
        if (!empty($page->course->id)) {
            $courseid = (int) $page->course->id;
        } else if ($page->context && ($coursecontext = $page->context->get_course_context(false))) {
            $courseid = (int) $coursecontext->instanceid;
        }
        if (!$courseid || $courseid == SITEID) {
            return 'skipped notcourse';
        }

        $cmid = !empty($page->cm->id) ? (int) $page->cm->id : 0;
        $playerjsurl = (string) get_config('local_bunnyanalytics', 'playerjsurl');
        if ($playerjsurl === '') {
            $playerjsurl = 'https://assets.mediadelivery.net/playerjs/playerjs-latest.min.js';
        }
        $flush = (int) get_config('local_bunnyanalytics', 'flushinterval');

        $page->requires->js_call_amd('local_bunnyanalytics/tracker', 'init', [[
            'courseid' => $courseid,
            'cmid' => $cmid,
            'playerjsurl' => $playerjsurl,
            'flushinterval' => $flush > 0 ? max(5, $flush) : 15,
        ]]);
        return "loaded course={$courseid} cm={$cmid}";
    }
}
