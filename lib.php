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
 * Moodle hooks for the recording submission plugin.
 *
 * @package    assignsubmission_recording
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Serves stored recording files to authorised users.
 *
 * @param mixed $course course or id of the course
 * @param mixed $cm course module or id of the course module
 * @param context $context the module context
 * @param string $filearea the file area name
 * @param array $args remaining URL path components
 * @param bool $forcedownload whether to force download
 * @param array $options additional options
 * @return bool false if file not found; does not return if found
 */
function assignsubmission_recording_pluginfile(
    $course,
    $cm,
    context $context,
    $filearea,
    $args,
    $forcedownload,
    array $options = []
) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    // Hard-coded rather than the ASSIGNSUBMISSION_RECORDING_FILEAREA constant from
    // locallib.php: this lib.php can be include_once'd directly by core's pluginfile
    // dispatcher before mod/assign/locallib.php (and thus assign_submission_plugin,
    // our locallib.php's parent class) has been loaded.
    if ($filearea !== 'submissions_recording') {
        return false;
    }

    require_login($course, false, $cm);

    // Access rules and the inline-versus-download decision live in file_access so
    // that they are unit tested; send_stored_file() ends the request.
    $servable = \assignsubmission_recording\local\file_access::get_servable_file(
        $context,
        $course,
        $cm,
        $filearea,
        $args,
        (bool) $forcedownload
    );
    if (!$servable) {
        return false;
    }

    send_stored_file($servable['file'], 0, 0, $servable['forcedownload'], $options);
}
