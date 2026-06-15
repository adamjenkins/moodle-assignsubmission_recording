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
 * Language strings for assignsubmission_recording.
 *
 * @package    assignsubmission_recording
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Recording submission';

// Admin settings — default enabled.
$string['default'] = 'Enabled by default';
$string['default_help'] = 'If set, this submission method will be enabled by default for all new assignments.';

// Per-assignment settings shown in the assignment form.
$string['enabled'] = 'Recording submission';
$string['enabled_help'] = 'If enabled, students submit a recorded audio or video clip instead of text.';

$string['mode'] = 'Allowed recording type';
$string['mode_help'] = 'Choose whether students may submit audio, video, or either.';
$string['mode_both'] = 'Audio or video';
$string['mode_audio'] = 'Audio only';
$string['mode_video'] = 'Video only';

$string['maxduration'] = 'Maximum recording length';
$string['maxduration_help'] = 'The longest a single recording may be. Recording stops automatically when this length is reached. Set to 0 seconds for no limit.';

// Site-wide quality settings.
$string['qualityheader'] = 'Recording quality';
$string['qualityheader_desc'] = 'These settings control the quality (and file size) of recordings, by setting the bitrates requested from the browser\'s recorder.';
$string['audiobitrate'] = 'Audio bitrate';
$string['audiobitrate_desc'] = 'Quality of recorded audio. Applies to both audio-only and video recordings.';
$string['videobitrate'] = 'Video bitrate';
$string['videobitrate_desc'] = 'Quality of recorded video. Applies to video recordings only.';
$string['kbrate'] = '{$a} kb/s';
$string['allowswitchcamera'] = 'Allow switching cameras';
$string['allowswitchcamera_desc'] = 'When enabled, a "Switch camera" button is shown while previewing a video recording.';

// Recorder interface.
$string['recorderintro'] = 'Record your submission. Click the button below to start recording audio or video.';
$string['recordaudio'] = 'Record audio';
$string['recordvideo'] = 'Record video';
$string['startrecording'] = 'Start recording';
$string['switchcamera'] = 'Switch camera';
$string['cancelpreview'] = 'Cancel';
$string['stoprecording'] = 'Stop recording';
$string['rerecord'] = 'Record again';
$string['previewready'] = 'Camera ready. Click "Start recording" when you are ready — the camera is not recording yet.';
$string['recording'] = 'Recording… click "Stop recording" when you have finished.';
$string['recordingstopped'] = 'Recording stopped automatically — the maximum length was reached.';
$string['maxlength'] = 'Maximum length: {$a}';
$string['timeremaininglabel'] = 'Time remaining';
$string['uploading'] = 'Uploading your recording…';
$string['recorded'] = 'Recording ready. You can submit it or record again.';

// Existing recording on re-submission.
$string['existingrecording'] = 'Your current recording';

// Submission summary.
$string['nosubmission'] = 'Nothing has been submitted for this assignment';

// Recorder errors (client-side).
$string['errornopermission'] = 'Could not access your microphone or camera. Please grant permission and try again.';
$string['errorunsupported'] = 'Recording is not supported by this browser.';
$string['erroruploadfailed'] = 'The recording could not be uploaded. Please try again.';

// Server-side errors.
$string['norecordingfound'] = 'No recording was received.';
$string['uploadfailed'] = 'The recording upload failed.';
$string['recordingnotallowed'] = 'That type of recording is not allowed for this assignment.';

// Privacy.
$string['privacy:metadata'] = 'The Recording submission plugin stores each student\'s recorded audio or video submission alongside its embed HTML in the assignsubmission_recording table.';
$string['privacy:metadata:assignmentid'] = 'Assignment ID';
$string['privacy:metadata:submissionpurpose'] = 'The submission ID that links to submissions for the user.';
$string['privacy:metadata:textpurpose'] = 'The embed HTML stored for this attempt of the assignment.';
$string['privacy:metadata:tablepurpose'] = 'Stores the recording submission for each attempt.';
$string['privacy:metadata:filepurpose'] = 'The recorded audio or video file attached to the submission.';
$string['privacy:path'] = 'Recording submission';
