<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Site-wide admin settings for assignsubmission_recording.
 *
 * @package    assignsubmission_recording
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Whether this submission plugin is enabled by default on new assignments.
$settings->add(new admin_setting_configcheckbox(
    'assignsubmission_recording/default',
    new lang_string('default', 'assignsubmission_recording'),
    new lang_string('default_help', 'assignsubmission_recording'),
    0
));

// Recording quality heading.
$settings->add(new admin_setting_heading(
    'assignsubmission_recording/qualityheader',
    get_string('qualityheader', 'assignsubmission_recording'),
    get_string('qualityheader_desc', 'assignsubmission_recording')
));

// Audio bitrate.
$audiobitrates = [24000, 32000, 48000, 64000, 96000, 128000, 160000, 192000, 256000, 320000];
$audiobitrateoptions = [];
foreach ($audiobitrates as $rate) {
    $audiobitrateoptions[$rate] = get_string('kbrate', 'assignsubmission_recording', $rate / 1000);
}
$settings->add(new admin_setting_configselect(
    'assignsubmission_recording/audiobitrate',
    get_string('audiobitrate', 'assignsubmission_recording'),
    get_string('audiobitrate_desc', 'assignsubmission_recording'),
    128000,
    $audiobitrateoptions
));

// Video bitrate.
$settings->add(new admin_setting_configtext(
    'assignsubmission_recording/videobitrate',
    get_string('videobitrate', 'assignsubmission_recording'),
    get_string('videobitrate_desc', 'assignsubmission_recording'),
    2500000,
    PARAM_INT,
    8
));

// Allow camera switching during video preview.
$settings->add(new admin_setting_configcheckbox(
    'assignsubmission_recording/allowswitchcamera',
    get_string('allowswitchcamera', 'assignsubmission_recording'),
    get_string('allowswitchcamera_desc', 'assignsubmission_recording'),
    0
));
