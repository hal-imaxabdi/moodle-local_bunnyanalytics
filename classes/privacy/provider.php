<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_bunnyanalytics\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider: lets admins export and delete a student's video viewing data.
 *
 * @package    local_bunnyanalytics
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    /** @var string[] Tables holding user data. */
    const TABLES = ['local_bunnyanalytics_prog', 'local_bunnyanalytics_events'];

    /**
     * Describes stored data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_bunnyanalytics_prog', [
            'userid' => 'privacy:metadata:userid',
            'courseid' => 'privacy:metadata:courseid',
            'videoguid' => 'privacy:metadata:videoguid',
            'percentwatched' => 'privacy:metadata:percentwatched',
            'views' => 'privacy:metadata:counts',
            'pauses' => 'privacy:metadata:counts',
            'replays' => 'privacy:metadata:counts',
            'rewinds' => 'privacy:metadata:counts',
            'timelast' => 'privacy:metadata:timelast',
        ], 'privacy:metadata:prog');
        $collection->add_database_table('local_bunnyanalytics_events', [
            'userid' => 'privacy:metadata:userid',
            'courseid' => 'privacy:metadata:courseid',
            'videoguid' => 'privacy:metadata:videoguid',
            'eventtype' => 'privacy:metadata:eventtype',
            'position' => 'privacy:metadata:position',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:events');
        return $collection;
    }

    /**
     * Course contexts where the user has data.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        foreach (self::TABLES as $table) {
            $sql = "SELECT ctx.id
                      FROM {context} ctx
                      JOIN {{$table}} t ON t.courseid = ctx.instanceid AND ctx.contextlevel = :level
                     WHERE t.userid = :userid";
            $contextlist->add_from_sql($sql, ['level' => CONTEXT_COURSE, 'userid' => $userid]);
        }
        return $contextlist;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }
        foreach (self::TABLES as $table) {
            $userlist->add_from_sql('userid', "SELECT userid FROM {{$table}} WHERE courseid = :courseid",
                ['courseid' => $context->instanceid]);
        }
    }

    /**
     * Exports data.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }
            $params = ['userid' => $userid, 'courseid' => $context->instanceid];
            $progress = $DB->get_records('local_bunnyanalytics_prog', $params, 'id',
                'videoguid, percentwatched, secondswatched, views, plays, pauses, replays, rewinds, completed, timefirst, timelast');
            $events = $DB->get_records('local_bunnyanalytics_events', $params, 'id',
                'id, videoguid, eventtype, position, timecreated');
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_bunnyanalytics')],
                (object) ['progress' => array_values($progress), 'events' => array_values($events)]
            );
        }
    }

    /**
     * Deletes all data in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }
        foreach (self::TABLES as $table) {
            $DB->delete_records($table, ['courseid' => $context->instanceid]);
        }
    }

    /**
     * Deletes one user's data.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }
            foreach (self::TABLES as $table) {
                $DB->delete_records($table, ['userid' => $userid, 'courseid' => $context->instanceid]);
            }
        }
    }

    /**
     * Deletes several users' data in one context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_COURSE || !$userlist->get_userids()) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params['courseid'] = $context->instanceid;
        foreach (self::TABLES as $table) {
            $DB->delete_records_select($table, "courseid = :courseid AND userid $insql", $params);
        }
    }
}
