<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_bunnyanalytics;

/**
 * Helpers for "which parts of the video has this student actually watched".
 *
 * Segments are [start, end] pairs in seconds. Overlapping ranges are merged, so rewatching
 * the same minute does not inflate the watched percentage.
 *
 * @package    local_bunnyanalytics
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class segments {

    /**
     * Merges new segments into existing ones.
     *
     * @param array $existing list of [start, end]
     * @param array $new list of ['start' => x, 'end' => y] or [x, y]
     * @param float $duration video length, 0 if unknown
     * @return array merged list of [start, end]
     */
    public static function merge(array $existing, array $new, float $duration = 0): array {
        $all = [];
        foreach (array_merge($existing, $new) as $seg) {
            $start = (float) ($seg['start'] ?? $seg[0] ?? 0);
            $end = (float) ($seg['end'] ?? $seg[1] ?? 0);
            $start = max(0, $start);
            if ($duration > 0) {
                $end = min($end, $duration);
            }
            if ($end - $start > 0) {
                $all[] = [$start, $end];
            }
        }
        usort($all, fn($a, $b) => $a[0] <=> $b[0]);

        $merged = [];
        foreach ($all as $seg) {
            $last = count($merged) - 1;
            // Treat gaps of up to 1 second as continuous viewing.
            if ($last >= 0 && $seg[0] <= $merged[$last][1] + 1) {
                $merged[$last][1] = max($merged[$last][1], $seg[1]);
            } else {
                $merged[] = $seg;
            }
        }
        return array_map(fn($s) => [round($s[0], 1), round($s[1], 1)], $merged);
    }

    /**
     * Total seconds covered by merged segments.
     *
     * @param array $segments list of [start, end]
     * @return float
     */
    public static function total(array $segments): float {
        $total = 0.0;
        foreach ($segments as $seg) {
            $total += max(0, $seg[1] - $seg[0]);
        }
        return $total;
    }
}
