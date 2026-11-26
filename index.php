<?php

/**
 * Video analytics report.
 *
 * - No params: site overview (one row per course).
 * - courseid: videos in that course.
 * - courseid + video: every learner's activity on that video.
 *
 * @package    local_bunnyanalytics
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_bunnyanalytics\report;

$courseid = optional_param('courseid', 0, PARAM_INT);
$videoguid = strtolower(optional_param('video', '', PARAM_ALPHANUMEXT));
$download = optional_param('download', '', PARAM_ALPHA);

$component = 'local_bunnyanalytics';
$urlparams = array_filter(['courseid' => $courseid, 'video' => $videoguid]);
$url = new moodle_url('/local/bunnyanalytics/index.php', $urlparams);
$PAGE->set_url($url);

if ($courseid) {
    $course = get_course($courseid);
    require_login($course);
    $context = context_course::instance($courseid);
    require_capability('local/bunnyanalytics:viewreports', $context);
    $PAGE->set_pagelayout('report');
    $PAGE->set_heading(format_string($course->fullname));
} else {
    require_login();
    $context = context_system::instance();
    require_capability('local/bunnyanalytics:viewreports', $context);
    $PAGE->set_context($context);
    $PAGE->set_pagelayout('admin');
    $PAGE->set_heading(get_string('reporttitle', $component));
}
$PAGE->set_title(get_string('reporttitle', $component));

$pct = fn($v) => $v === null ? '–' : format_float((float) $v, 1) . '%';
$when = fn($t) => empty($t) ? '–' : userdate($t, get_string('strftimedatetimeshort', 'core_langconfig'));
$num = fn($v) => $v === null ? '–' : (string) (int) $v;

// Build headers + plain rows (for download) and HTML rows (for display).
$headers = [];
$plain = [];
$html = [];
$intro = '';
$filename = 'video-analytics';

if (!$courseid) {
    // Site overview.
    $headers = [get_string('course'), get_string('col_videos', $component), get_string('col_viewers', $component),
        get_string('col_views', $component), get_string('col_avgpercent', $component),
        get_string('col_completions', $component), get_string('col_lastwatched', $component)];
    foreach (report::site_summary() as $r) {
        $row = [format_string($r->fullname), $num($r->videos), $num($r->viewers), $num($r->views),
            $pct($r->avgpercent), $num($r->completions), $when($r->lastwatched)];
        $plain[] = $row;
        $row[0] = html_writer::link(new moodle_url('/local/bunnyanalytics/index.php', ['courseid' => $r->courseid]), $row[0]);
        $html[] = $row;
    }
    $intro = get_string('intro_site', $component);

} else if ($videoguid === '') {
    // Course: one row per video.
    $learners = report::count_learners($courseid);
    $headers = [get_string('col_video', $component), get_string('col_started', $component),
        get_string('col_views', $component), get_string('col_avgpercent', $component),
        get_string('col_completions', $component), get_string('col_pauses', $component),
        get_string('col_replays', $component), get_string('col_rewinds', $component),
        get_string('col_bunnyviews', $component), get_string('col_engagement', $component),
        get_string('col_lastwatched', $component)];
    foreach (report::course_summary($courseid) as $r) {
        $started = $learners ? "{$r->viewers} / {$learners}" : (string) $r->viewers;
        $row = [s($r->title), $started, $num($r->views), $pct($r->avgpercent),
            $num($r->completions), $num($r->pauses), $num($r->replays), $num($r->rewinds),
            $num($r->bunnyviews), $r->engagementscore === null ? '–' : format_float($r->engagementscore, 0),
            $when($r->lastwatched)];
        $plain[] = $row;
        $row[0] = html_writer::link(new moodle_url('/local/bunnyanalytics/index.php',
            ['courseid' => $courseid, 'video' => $r->videoguid]), $row[0]);
        $html[] = $row;
    }
    $intro = get_string('intro_course', $component, $learners);
    $filename .= '-course' . $courseid;

} else {
    // Video: one row per learner.
    $info = report::video_info([$videoguid])[$videoguid] ?? null;
    $title = ($info && $info->title !== '') ? $info->title : $videoguid;
    $headers = [get_string('fullname'), get_string('col_status', $component), get_string('col_percent', $component),
        get_string('col_timewatched', $component), get_string('col_views', $component),
        get_string('col_pauses', $component), get_string('col_replays', $component),
        get_string('col_rewinds', $component), get_string('col_firstwatched', $component),
        get_string('col_lastwatched', $component)];
    foreach (report::video_detail($courseid, $videoguid) as $r) {
        if ($r->timefirst === null) {
            $status = get_string('status_notstarted', $component);
        } else if ($r->completed) {
            $status = get_string('status_completed', $component);
        } else {
            $status = get_string('status_inprogress', $component);
        }
        $row = [fullname($r), $status, $pct($r->percentwatched),
            $r->secondswatched === null ? '–' : format_time((int) round($r->secondswatched)),
            $num($r->views), $num($r->pauses), $num($r->replays), $num($r->rewinds),
            $when($r->timefirst), $when($r->timelast)];
        $plain[] = $row;
        $html[] = $row;
    }
    $intro = get_string('intro_video', $component, s($title));
    $filename .= '-course' . $courseid . '-' . clean_filename($title);
}

if ($download) {
    \core\dataformat::download_data($filename, $download, $headers, $plain);
    die();
}

echo $OUTPUT->header();

if ($courseid && $videoguid) {
    echo html_writer::div(html_writer::link(new moodle_url('/local/bunnyanalytics/index.php', ['courseid' => $courseid]),
        '← ' . get_string('backtovideos', $component)), 'mb-3');
}
echo $OUTPUT->heading(get_string('reporttitle', $component));
echo html_writer::tag('p', $intro);

if (!$html) {
    echo $OUTPUT->notification(get_string('nodata', $component), \core\output\notification::NOTIFY_INFO);
} else {
    $table = new html_table();
    $table->head = $headers;
    $table->data = $html;
    $table->attributes['class'] = 'generaltable table-sm';
    echo html_writer::div(html_writer::table($table), 'table-responsive');
    echo $OUTPUT->download_dataformat_selector(get_string('downloadas', 'table'), $PAGE->url, 'download', $urlparams);
}

echo html_writer::tag('p', get_string('footnote', $component), ['class' => 'text-muted small mt-3']);
echo $OUTPUT->footer();
