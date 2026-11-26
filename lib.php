<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Library functions for local_bunnyanalytics.
 *
 * @package    local_bunnyanalytics
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds a "Video analytics" link to the course navigation (the course "More" menu).
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context $context
 */
function local_bunnyanalytics_extend_navigation_course(navigation_node $navigation, stdClass $course, context $context) {
    if (has_capability('local/bunnyanalytics:viewreports', $context)) {
        $url = new moodle_url('/local/bunnyanalytics/index.php', ['courseid' => $course->id]);
        $navigation->add(get_string('reporttitle', 'local_bunnyanalytics'), $url,
            navigation_node::TYPE_SETTING, null, 'local_bunnyanalytics', new pix_icon('i/report', ''));
    }
}
