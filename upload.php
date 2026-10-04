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

// The user must have permission to submit to this assignment (re-checked in store()).
require_capability('mod/assign:submit', $context);

if (!isset($_FILES['recording']) || !is_uploaded_file($_FILES['recording']['tmp_name'])) {
    throw new moodle_exception('norecordingfound', 'assignsubmission_recording');
}

if (!empty($_FILES['recording']['error'])) {
    throw new moodle_exception('uploadfailed', 'assignsubmission_recording');
}

// Capability, recording mode, size limits and the content-type allowlist are all
// enforced in recording_upload::store().
$result = \assignsubmission_recording\local\recording_upload::store(
    $context,
    $draftitemid,
    $mediatype,
    $_FILES['recording']['tmp_name']
);

echo json_encode($result);
