<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_bunnyanalytics;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');

/**
 * Minimal client for the Bunny Stream API (https://video.bunnycdn.com).
 *
 * The API key is a Stream *library* key (Stream > your library > API), and never leaves the server.
 *
 * @package    local_bunnyanalytics
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {

    /** @var string API base URL. */
    const BASE = 'https://video.bunnycdn.com';

    /** @var int Library id. */
    protected $libraryid;

    /** @var string Library API key. */
    protected $apikey;

    /**
     * Constructor, reads plugin settings.
     */
    public function __construct() {
        $this->libraryid = (int) get_config('local_bunnyanalytics', 'libraryid');
        $this->apikey = trim((string) get_config('local_bunnyanalytics', 'apikey'));
    }

    /**
     * Whether a library id and key are set.
     *
     * @return bool
     */
    public function is_configured(): bool {
        return $this->libraryid > 0 && $this->apikey !== '';
    }

    /**
     * Configured library id.
     *
     * @return int
     */
    public function get_library_id(): int {
        return $this->libraryid;
    }

    /**
     * Sends a GET request and decodes the JSON response.
     *
     * @param string $path
     * @param array $query
     * @return array
     */
    protected function request(string $path, array $query = []): array {
        $url = self::BASE . $path . ($query ? '?' . http_build_query($query, '', '&') : '');
        $curl = new \curl();
        $curl->setHeader(['AccessKey: ' . $this->apikey, 'Accept: application/json']);
        $response = $curl->get($url, [], ['CURLOPT_TIMEOUT' => 30]);
        $info = $curl->get_info();
        $code = (int) ($info['http_code'] ?? 0);

        if ($curl->get_errno() || $code >= 400 || $code === 0) {
            throw new \moodle_exception('apierror', 'local_bunnyanalytics', '',
                "HTTP {$code}: " . \core_text::substr((string) $response, 0, 200));
        }
        $data = json_decode((string) $response, true);
        if (!is_array($data)) {
            throw new \moodle_exception('apierror', 'local_bunnyanalytics', '', 'Invalid JSON response');
        }
        return $data;
    }

    /**
     * Lists videos in the library, one page at a time.
     *
     * @param int $page 1-based page number
     * @param int $perpage
     * @return array with keys totalItems, items
     */
    public function list_videos(int $page, int $perpage = 100): array {
        return $this->request("/library/{$this->libraryid}/videos", [
            'page' => $page,
            'itemsPerPage' => $perpage,
            'orderBy' => 'date',
        ]);
    }

    /**
     * Statistics for one video (views/watch time charts, country data, engagement score).
     *
     * @param string $videoguid
     * @param int $days how many days back
     * @return array
     */
    public function get_video_statistics(string $videoguid, int $days = 365): array {
        $days = max(1, min(365, $days));
        return $this->request("/library/{$this->libraryid}/statistics", [
            'videoGuid' => $videoguid,
            'dateFrom' => gmdate('Y-m-d\TH:i:s\Z', time() - $days * DAYSECS),
        ]);
    }
}
