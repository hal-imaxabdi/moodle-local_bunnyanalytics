<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_bunnyanalytics\task;

use local_bunnyanalytics\api;

/**
 * Pulls video titles and aggregate stats (views, watch time, engagement) from Bunny once a day.
 *
 * @package    local_bunnyanalytics
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fetch_stats extends \core\task\scheduled_task {

    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskfetchstats', 'local_bunnyanalytics');
    }

    /**
     * Runs the task.
     */
    public function execute() {
        global $DB;

        $api = new api();
        if (!$api->is_configured()) {
            mtrace('Bunny analytics: library id or API key not set, skipping.');
            return;
        }
        $days = (int) get_config('local_bunnyanalytics', 'statsdays') ?: 365;
        $perpage = 100;
        $page = 1;
        $count = 0;

        do {
            $result = $api->list_videos($page, $perpage);
            $items = $result['items'] ?? [];
            $total = (int) ($result['totalItems'] ?? 0);

            foreach ($items as $video) {
                $guid = strtolower((string) ($video['guid'] ?? ''));
                if ($guid === '') {
                    continue;
                }
                $engagement = null;
                try {
                    $stats = $api->get_video_statistics($guid, $days);
                    if (isset($stats['engagementScore']) && is_numeric($stats['engagementScore'])) {
                        $engagement = round((float) $stats['engagementScore'], 2);
                    }
                } catch (\moodle_exception $e) {
                    mtrace("  Could not fetch statistics for {$guid}: " . $e->getMessage());
                }

                $row = (object) [
                    'libraryid' => $api->get_library_id(),
                    'videoguid' => $guid,
                    'title' => \core_text::substr((string) ($video['title'] ?? ''), 0, 255),
                    'length' => (int) ($video['length'] ?? 0),
                    'bunnyviews' => (int) ($video['views'] ?? 0),
                    'totalwatchtime' => (int) ($video['totalWatchTime'] ?? 0),
                    'avgwatchtime' => (int) ($video['averageWatchTime'] ?? 0),
                    'engagementscore' => $engagement,
                    'timefetched' => time(),
                ];
                if ($id = $DB->get_field('local_bunnyanalytics_videos', 'id', ['videoguid' => $guid])) {
                    $row->id = $id;
                    $DB->update_record('local_bunnyanalytics_videos', $row);
                } else {
                    $DB->insert_record('local_bunnyanalytics_videos', $row);
                }
                $count++;
            }
            $page++;
        } while (!empty($items) && ($page - 1) * $perpage < $total);

        mtrace("Bunny analytics: updated {$count} videos.");
    }
}
