<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Admin settings for local_bunnyanalytics.
 *
 * @package    local_bunnyanalytics
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_bunnyanalytics', get_string('pluginname', 'local_bunnyanalytics'));
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configcheckbox('local_bunnyanalytics/enabled',
            get_string('enabled', 'local_bunnyanalytics'),
            get_string('enabled_desc', 'local_bunnyanalytics'), 1));

        $settings->add(new admin_setting_configtext('local_bunnyanalytics/libraryid',
            get_string('libraryid', 'local_bunnyanalytics'),
            get_string('libraryid_desc', 'local_bunnyanalytics'), '', PARAM_INT));

        $settings->add(new admin_setting_configpasswordunmask('local_bunnyanalytics/apikey',
            get_string('apikey', 'local_bunnyanalytics'),
            get_string('apikey_desc', 'local_bunnyanalytics'), ''));

        $settings->add(new admin_setting_configtext('local_bunnyanalytics/playerjsurl',
            get_string('playerjsurl', 'local_bunnyanalytics'),
            get_string('playerjsurl_desc', 'local_bunnyanalytics'),
            'https://assets.mediadelivery.net/playerjs/playerjs-latest.min.js', PARAM_URL));

        $settings->add(new admin_setting_configtext('local_bunnyanalytics/completionthreshold',
            get_string('completionthreshold', 'local_bunnyanalytics'),
            get_string('completionthreshold_desc', 'local_bunnyanalytics'), 90, PARAM_INT));

        $settings->add(new admin_setting_configtext('local_bunnyanalytics/flushinterval',
            get_string('flushinterval', 'local_bunnyanalytics'),
            get_string('flushinterval_desc', 'local_bunnyanalytics'), 15, PARAM_INT));

        $settings->add(new admin_setting_configcheckbox('local_bunnyanalytics/storerawevents',
            get_string('storerawevents', 'local_bunnyanalytics'),
            get_string('storerawevents_desc', 'local_bunnyanalytics'), 1));

        $settings->add(new admin_setting_configtext('local_bunnyanalytics/statsdays',
            get_string('statsdays', 'local_bunnyanalytics'),
            get_string('statsdays_desc', 'local_bunnyanalytics'), 365, PARAM_INT));
    }
}

// Site-wide report, visible to anyone with the capability at system level (admins, managers).
$ADMIN->add('reports', new admin_externalpage('local_bunnyanalytics_report',
    get_string('reporttitle', 'local_bunnyanalytics'),
    new moodle_url('/local/bunnyanalytics/index.php'),
    'local/bunnyanalytics:viewreports'));
