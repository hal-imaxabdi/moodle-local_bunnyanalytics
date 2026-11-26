<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_bunnyanalytics;

/**
 * Data for the three report levels: site, course, video.
 *
 * Course and video reports only count "learners" (enrolled users who appear in completion
 * reports, i.e. students), so teachers checking the video don't skew the numbers.
 *
 * @package    local_bunnyanalytics
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report {

    /** @var string Capability that marks a user as a learner. */
    const LEARNER_CAP = 'moodle/course:isincompletionreports';

    /**
     * One row per course that has tracked video activity.
     *
     * @return array of stdClass
     */
    public static function site_summary(): array {
        global $DB;
        $sql = "SELECT p.courseid, c.fullname,
                       COUNT(DISTINCT p.videoguid) AS videos,
                       COUNT(DISTINCT p.userid) AS viewers,
                       SUM(p.views) AS views,
                       AVG(p.percentwatched) AS avgpercent,
                       SUM(p.completed) AS completions,
                       MAX(p.timelast) AS lastwatched
                  FROM {local_bunnyanalytics_prog} p
                  JOIN {course} c ON c.id = p.courseid
              GROUP BY p.courseid, c.fullname
              ORDER BY c.fullname";
        return array_values($DB->get_records_sql($sql));
    }

    /**
     * One row per video watched in a course.
     *
     * @param int $courseid
     * @return array of stdClass
     */
    public static function course_summary(int $courseid): array {
        global $DB;
        $context = \context_course::instance($courseid);
        [$esql, $eparams] = get_enrolled_sql($context, self::LEARNER_CAP, 0, true);

        $sql = "SELECT p.videoguid,
                       COUNT(DISTINCT p.userid) AS viewers,
                       SUM(p.views) AS views,
                       AVG(p.percentwatched) AS avgpercent,
                       SUM(p.completed) AS completions,
                       SUM(p.pauses) AS pauses,
                       SUM(p.replays) AS replays,
                       SUM(p.rewinds) AS rewinds,
                       MAX(p.timelast) AS lastwatched
                  FROM {local_bunnyanalytics_prog} p
                  JOIN ($esql) e ON e.id = p.userid
                 WHERE p.courseid = :courseid
              GROUP BY p.videoguid";
        $rows = $DB->get_records_sql($sql, array_merge($eparams, ['courseid' => $courseid]));
        $videos = self::video_info(array_keys($rows));

        foreach ($rows as $guid => $row) {
            $info = $videos[$guid] ?? null;
            $row->title = ($info && $info->title !== '') ? $info->title : $guid;
            $row->bunnyviews = $info->bunnyviews ?? null;
            $row->engagementscore = $info->engagementscore ?? null;
        }
        $rows = array_values($rows);
        usort($rows, fn($a, $b) => strcasecmp($a->title, $b->title));
        return $rows;
    }

    /**
     * One row per learner in the course for a single video, including learners who never started it.
     *
     * @param int $courseid
     * @param string $videoguid
     * @return array of stdClass
     */
    public static function video_detail(int $courseid, string $videoguid): array {
        global $DB;
        $context = \context_course::instance($courseid);
        [$esql, $eparams] = get_enrolled_sql($context, self::LEARNER_CAP, 0, true);
        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;

        $sql = "SELECT u.id, $namefields,
                       p.views, p.plays, p.pauses, p.replays, p.rewinds, p.percentwatched,
                       p.secondswatched, p.completed, p.timefirst, p.timelast
                  FROM {user} u
                  JOIN ($esql) e ON e.id = u.id
             LEFT JOIN {local_bunnyanalytics_prog} p
                       ON p.userid = u.id AND p.courseid = :courseid AND p.videoguid = :videoguid
              ORDER BY u.lastname, u.firstname";
        return array_values($DB->get_records_sql($sql,
            array_merge($eparams, ['courseid' => $courseid, 'videoguid' => $videoguid])));
    }

    /**
     * Number of learners enrolled in the course.
     *
     * @param int $courseid
     * @return int
     */
    public static function count_learners(int $courseid): int {
        return count_enrolled_users(\context_course::instance($courseid), self::LEARNER_CAP, 0, true);
    }

    /**
     * Cached video info from the Bunny API task, keyed by GUID.
     *
     * @param array $guids
     * @return array
     */
    public static function video_info(array $guids): array {
        global $DB;
        if (!$guids) {
            return [];
        }
        $records = $DB->get_records_list('local_bunnyanalytics_videos', 'videoguid', $guids);
        $out = [];
        foreach ($records as $r) {
            $out[$r->videoguid] = $r;
        }
        return $out;
    }
}
