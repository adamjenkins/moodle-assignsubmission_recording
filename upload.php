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
 * Stores a RecordRTC recording into the user's draft file area for the assignment submission.
 *
 * The recorded blob is uploaded here from the browser, saved into the draft area
 * referenced by the submission form, and a draftfile URL is returned so the
 * client can embed it in the hidden recording text field. mod_assign then moves
 * the file to the submission area when the form is submitted.
 *
 * @package    assignsubmission_recording
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once(__DIR__ . '/locallib.php');

require_login();
require_sesskey();

$draftitemid = required_param('itemid', PARAM_INT);
$contextid   = required_param('contextid', PARAM_INT);
$mediatype   = required_param('mediatype', PARAM_ALPHA);

$context = context::instance_by_id($contextid, MUST_EXIST);

if ($context->contextlevel != CONTEXT_MODULE) {
    throw new moodle_exception('invalidcontext', 'error');
}

// Resolve the assignment behind this module context.
$cm = get_coursemodule_from_id('assign', $context->instanceid, 0, false, MUST_EXIST);
require_login($cm->course, false, $cm);

// The user must have permission to submit to this assignment.
require_capability('mod/assign:submit', $context);

// Load the plugin config to check the allowed recording mode.
$course = get_course($cm->course);
$assign = new assign($context, $cm, $course);
$plugin = $assign->get_plugin_by_type('assignsubmission', 'recording');

if ($plugin) {
    $mode = $plugin->get_config('mode') ?: assign_submission_recording::MODE_BOTH;

    if (
        ($mode === assign_submission_recording::MODE_AUDIO && $mediatype !== 'audio')
        || ($mode === assign_submission_recording::MODE_VIDEO && $mediatype !== 'video')
    ) {
        throw new moodle_exception('recordingnotallowed', 'assignsubmission_recording');
    }
}

if (!isset($_FILES['recording']) || !is_uploaded_file($_FILES['recording']['tmp_name'])) {
    throw new moodle_exception('norecordingfound', 'assignsubmission_recording');
}

if (!empty($_FILES['recording']['error'])) {
    throw new moodle_exception('uploadfailed', 'assignsubmission_recording');
}

$tmpname = $_FILES['recording']['tmp_name'];

// The recorder's client-side maxduration auto-stop is advisory only; enforce the
// course/site upload size limit server-side as well.
$maxbytes = get_max_upload_file_size($CFG->maxbytes, $course->maxbytes);
if ($maxbytes > 0 && $_FILES['recording']['size'] > $maxbytes) {
    throw new moodle_exception('uploadfailed', 'assignsubmission_recording');
}

// Identify the real content type from the file's contents rather than trusting the
// client-supplied filename/extension or Content-Type header, which are attacker
// controlled. This is the container formats the AMD recorder actually produces
// (webm/mp4/ogg); anything else is rejected outright.
$allowedmimetypes = [
    'audio/webm'      => 'webm',
    'video/webm'      => 'webm',
    'audio/mp4'       => 'mp4',
    'video/mp4'       => 'mp4',
    'audio/ogg'       => 'ogg',
    'video/ogg'       => 'ogg',
    'application/ogg' => 'ogg',
];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$detectedmimetype = $finfo ? finfo_file($finfo, $tmpname) : false;
if ($finfo) {
    finfo_close($finfo);
}

if (!$detectedmimetype || !isset($allowedmimetypes[$detectedmimetype])) {
    throw new moodle_exception('recordingnotallowed', 'assignsubmission_recording');
}

$fs = get_file_storage();
$usercontext = context_user::instance($USER->id);

// Server-generated filename: the extension comes from the detected mimetype above,
// never from the client-supplied name, so a spoofed extension cannot smuggle an
// executable file type past this check.
$filename = ($mediatype === 'video' ? 'video' : 'audio') . '.' . $allowedmimetypes[$detectedmimetype];
$filename = $fs->get_unused_filename($usercontext->id, 'user', 'draft', $draftitemid, '/', $filename);

$filerecord = (object) [
    'contextid' => $usercontext->id,
    'component' => 'user',
    'filearea'  => 'draft',
    'itemid'    => $draftitemid,
    'filepath'  => '/',
    'filename'  => $filename,
    'userid'    => $USER->id,
];

$storedfile = $fs->create_file_from_pathname($filerecord, $tmpname);

$url = moodle_url::make_draftfile_url($draftitemid, '/', $filename)->out(false);

echo json_encode([
    'url'      => $url,
    'filename' => $filename,
    'mimetype' => $storedfile->get_mimetype(),
]);
