<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_bunnyanalytics\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_bunnyanalytics\segments;

/**
 * Receives a batch of player events from the browser and updates the student's progress.
 *
 * @package    local_bunnyanalytics
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class log_events extends external_api {

    /** @var string[] Event types the tracker may send. */
    const EVENT_TYPES = ['play', 'pause', 'ended', 'replay', 'rewind', 'seek'];

    /** @var int Max events per request, to limit abuse. */
    const MAX_EVENTS = 200;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'cmid' => new external_value(PARAM_INT, 'Course module id, 0 if not in an activity', VALUE_DEFAULT, 0),
            'libraryid' => new external_value(PARAM_INT, 'Bunny Stream library id'),
            'videoguid' => new external_value(PARAM_ALPHANUMEXT, 'Bunny video GUID'),
            'sessionid' => new external_value(PARAM_ALPHANUMEXT, 'Random id for this page view of the video'),
            'duration' => new external_value(PARAM_FLOAT, 'Video length in seconds', VALUE_DEFAULT, 0),
            'position' => new external_value(PARAM_FLOAT, 'Last known playback position', VALUE_DEFAULT, 0),
            'events' => new external_multiple_structure(
                new external_single_structure([
                    'type' => new external_value(PARAM_ALPHA, 'play, pause, ended, replay, rewind or seek'),
                    'position' => new external_value(PARAM_FLOAT, 'Position in seconds'),
                    'time' => new external_value(PARAM_INT, 'Client unix time'),
                ]), 'Events', VALUE_DEFAULT, []),
            'segments' => new external_multiple_structure(
                new external_single_structure([
                    'start' => new external_value(PARAM_FLOAT, 'Start second'),
                    'end' => new external_value(PARAM_FLOAT, 'End second'),
                ]), 'Ranges watched since the last batch', VALUE_DEFAULT, []),
        ]);
    }

    /**
     * Records the events.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $libraryid
     * @param string $videoguid
     * @param string $sessionid
     * @param float $duration
     * @param float $position
     * @param array $events
     * @param array $segments
     * @return array
     */
    public static function execute(int $courseid, int $cmid, int $libraryid, string $videoguid, string $sessionid,
            float $duration = 0, float $position = 0, array $events = [], array $segments = []): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid, 'cmid' => $cmid, 'libraryid' => $libraryid, 'videoguid' => $videoguid,
            'sessionid' => $sessionid, 'duration' => $duration, 'position' => $position,
            'events' => $events, 'segments' => $segments,
        ]);

        if ($params['cmid']) {
            $cm = get_coursemodule_from_id('', $params['cmid'], $params['courseid'], false, MUST_EXIST);
            $context = \context_module::instance($cm->id);
        } else {
            $context = \context_course::instance($params['courseid']);
        }
        // Checks the user is logged in and can access this course/activity.
        self::validate_context($context);

        $guid = strtolower($params['videoguid']);
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $guid)) {
            throw new \invalid_parameter_exception('Invalid video GUID');
        }
        $sessionid = \core_text::substr($params['sessionid'], 0, 64);
        $duration = max(0, $params['duration']);
        $now = time();

        $events = array_slice(array_filter($params['events'],
            fn($e) => in_array($e['type'], self::EVENT_TYPES, true)), 0, self::MAX_EVENTS);

        // 1. Raw events (optional).
        if ($events && get_config('local_bunnyanalytics', 'storerawevents') !== '0') {
            $rows = [];
            foreach ($events as $e) {
                $rows[] = (object) [
                    'userid' => $USER->id,
                    'courseid' => $params['courseid'],
                    'cmid' => $params['cmid'],
                    'videoguid' => $guid,
                    'sessionid' => $sessionid,
                    'eventtype' => $e['type'],
                    'position' => round(max(0, $e['position']), 2),
                    'timecreated' => $now,
                ];
            }
            $DB->insert_records('local_bunnyanalytics_events', $rows);
        }

        // 2. Per-student summary.
        $conditions = ['userid' => $USER->id, 'courseid' => $params['courseid'], 'videoguid' => $guid];
        $record = $DB->get_record('local_bunnyanalytics_prog', $conditions);
        if (!$record) {
            $record = (object) array_merge($conditions, [
                'cmid' => $params['cmid'], 'libraryid' => $params['libraryid'], 'duration' => 0,
                'secondswatched' => 0, 'percentwatched' => 0, 'segments' => '[]', 'views' => 0, 'plays' => 0,
                'pauses' => 0, 'replays' => 0, 'rewinds' => 0, 'completed' => 0, 'lastsession' => '',
                'lastposition' => 0, 'timefirst' => $now, 'timelast' => $now,
            ]);
        }

        foreach ($events as $e) {
            switch ($e['type']) {
                case 'play':
                    $record->plays++;
                    if ($record->lastsession !== $sessionid) {
                        $record->views++;
                        $record->lastsession = $sessionid;
                    }
                    break;
                case 'pause':
                    $record->pauses++;
                    break;
                case 'replay':
                    $record->replays++;
                    break;
                case 'rewind':
                    $record->rewinds++;
                    break;
            }
        }

        if ($duration > 0) {
            $record->duration = $duration;
        }
        $existing = json_decode((string) $record->segments, true) ?: [];
        $merged = segments::merge($existing, $params['segments'], (float) $record->duration);
        $record->segments = json_encode($merged);
        $record->secondswatched = round(segments::total($merged), 2);
        $record->percentwatched = $record->duration > 0
            ? round(min(100, $record->secondswatched / $record->duration * 100), 2) : 0;

        $threshold = (int) get_config('local_bunnyanalytics', 'completionthreshold') ?: 90;
        if ($record->percentwatched >= $threshold) {
            $record->completed = 1;
        }
        $record->lastposition = round(max(0, $params['position']), 2);
        $record->timelast = $now;
        if ($params['cmid']) {
            $record->cmid = $params['cmid'];
        }

        if (empty($record->id)) {
            try {
                $record->id = $DB->insert_record('local_bunnyanalytics_prog', $record);
            } catch (\dml_write_exception $ex) {
                // Two batches arrived at once and the other one created the row first; retry as update.
                $record->id = $DB->get_field('local_bunnyanalytics_prog', 'id', $conditions, MUST_EXIST);
                $DB->update_record('local_bunnyanalytics_prog', $record);
            }
        } else {
            $DB->update_record('local_bunnyanalytics_prog', $record);
        }

        return ['percentwatched' => (float) $record->percentwatched, 'completed' => (bool) $record->completed];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'percentwatched' => new external_value(PARAM_FLOAT, 'Unique percent of the video watched'),
            'completed' => new external_value(PARAM_BOOL, 'Whether the completion threshold is reached'),
        ]);
    }
}
