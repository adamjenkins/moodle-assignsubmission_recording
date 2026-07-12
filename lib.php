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
    global $DB, $CFG;

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

    $itemid = (int) array_shift($args);
    $record = $DB->get_record(
        'assign_submission',
        ['id' => $itemid],
        'userid, assignment, groupid',
        MUST_EXIST
    );

    require_once($CFG->dirroot . '/mod/assign/locallib.php');
    $assign = new assign($context, $cm, $course);

    if ($assign->get_instance()->id != $record->assignment) {
        return false;
    }

    if (
        $assign->get_instance()->teamsubmission
        && !$assign->can_view_group_submission($record->groupid)
    ) {
        return false;
    }

    if (
        !$assign->get_instance()->teamsubmission
        && !$assign->can_view_submission($record->userid)
    ) {
        return false;
    }

    $relativepath = implode('/', $args);
    $fullpath = "/{$context->id}/assignsubmission_recording/$filearea/$itemid/$relativepath";

    $fs = get_file_storage();
    if (!($file = $fs->get_file_by_hash(sha1($fullpath))) || $file->is_directory()) {
        return false;
    }

    // Recordings play inline as <audio>/<video>, but only when the stored file is
    // genuinely audio/video. Anything else (e.g. a non-media file that slipped past
    // upload.php's content check by some other route) is forced to download instead
    // of being rendered inline, matching core assignsubmission_file's behaviour.
    $ismedia = (bool) preg_match('#^(audio|video)/#', $file->get_mimetype());
    send_stored_file($file, 0, 0, !$ismedia, $options);
}
