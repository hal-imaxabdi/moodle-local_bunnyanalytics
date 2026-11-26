<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * English strings for local_bunnyanalytics.
 *
 * @package    local_bunnyanalytics
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Bunny Stream video analytics';
$string['reporttitle'] = 'Video analytics';
$string['bunnyanalytics:viewreports'] = 'View Bunny video analytics reports';
$string['taskfetchstats'] = 'Fetch video statistics from Bunny Stream';
$string['apierror'] = 'Bunny Stream API error: {$a}';

// Settings.
$string['enabled'] = 'Enable tracking';
$string['enabled_desc'] = 'Track play, pause, replay and watch progress of Bunny Stream videos embedded in courses.';
$string['libraryid'] = 'Stream library ID';
$string['libraryid_desc'] = 'Found in the Bunny dashboard under Stream → your library → API.';
$string['apikey'] = 'Stream library API key';
$string['apikey_desc'] = 'The library\'s API key (Stream → your library → API). Stored on the server only; used by the daily statistics task.';
$string['playerjsurl'] = 'player.js URL';
$string['playerjsurl_desc'] = 'Bunny-hosted player.js build used to listen to the player.';
$string['completionthreshold'] = 'Completion threshold (%)';
$string['completionthreshold_desc'] = 'A video counts as completed when a student has watched at least this percentage of it (unique seconds, so rewatching does not count twice).';
$string['flushinterval'] = 'Send interval (seconds)';
$string['flushinterval_desc'] = 'How often the browser sends progress while a video is playing. Pauses and endings are always sent immediately.';
$string['storerawevents'] = 'Store raw events';
$string['storerawevents_desc'] = 'Keep every play/pause/rewind event with its video position, for detailed analysis later (e.g. which parts students rewatch). The summary report works without this.';
$string['statsdays'] = 'Statistics period (days)';
$string['statsdays_desc'] = 'How many days back to request Bunny statistics for (max 365).';

// Report.
$string['intro_site'] = 'Video activity in every course that has tracked Bunny videos. Select a course for details.';
$string['intro_course'] = 'Bunny videos watched in this course by its {$a} enrolled students. Select a video to see each student.';
$string['intro_video'] = 'Each student\'s activity on “{$a}”.';
$string['backtovideos'] = 'All videos in this course';
$string['nodata'] = 'No video activity has been recorded yet.';
$string['footnote'] = 'Watched % counts unique seconds watched. Rewinds are jumps back of more than 5 seconds; replays are plays after reaching the end. Bunny views and engagement come from Bunny\'s own statistics (all viewers, refreshed daily).';
$string['col_video'] = 'Video';
$string['col_videos'] = 'Videos';
$string['col_viewers'] = 'Viewers';
$string['col_started'] = 'Students started';
$string['col_views'] = 'Views';
$string['col_avgpercent'] = 'Avg. watched';
$string['col_percent'] = 'Watched';
$string['col_completions'] = 'Completed';
$string['col_pauses'] = 'Pauses';
$string['col_replays'] = 'Replays';
$string['col_rewinds'] = 'Rewinds';
$string['col_bunnyviews'] = 'Bunny views';
$string['col_engagement'] = 'Engagement (0–100)';
$string['col_status'] = 'Status';
$string['col_timewatched'] = 'Time watched';
$string['col_firstwatched'] = 'First watched';
$string['col_lastwatched'] = 'Last watched';
$string['status_notstarted'] = 'Not started';
$string['status_inprogress'] = 'In progress';
$string['status_completed'] = 'Completed';

// Privacy.
$string['privacy:metadata:prog'] = 'A summary of how each user watched each Bunny Stream video in a course.';
$string['privacy:metadata:events'] = 'Individual player events (play, pause, rewind, etc.) for Bunny Stream videos.';
$string['privacy:metadata:userid'] = 'The user who watched the video.';
$string['privacy:metadata:courseid'] = 'The course the video was watched in.';
$string['privacy:metadata:videoguid'] = 'The Bunny Stream video identifier.';
$string['privacy:metadata:percentwatched'] = 'How much of the video the user has watched.';
$string['privacy:metadata:counts'] = 'How many times the user viewed, paused, replayed or rewound the video.';
$string['privacy:metadata:timelast'] = 'When the user last watched the video.';
$string['privacy:metadata:eventtype'] = 'The type of player event.';
$string['privacy:metadata:position'] = 'The position in the video when the event happened.';
$string['privacy:metadata:timecreated'] = 'When the event was recorded.';
