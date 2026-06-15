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

$string['allowswitchcamera'] = 'Allow switching cameras';
$string['allowswitchcamera_desc'] = 'When enabled, a "Switch camera" button is shown while previewing a video recording.';
$string['audiobitrate'] = 'Audio bitrate';
$string['audiobitrate_desc'] = 'Quality of recorded audio. Applies to both audio-only and video recordings.';
$string['cancelpreview'] = 'Cancel';
$string['default'] = 'Enabled by default';
$string['default_help'] = 'If set, this submission method will be enabled by default for all new assignments.';
$string['enabled'] = 'Recording submission';
$string['enabled_help'] = 'If enabled, students submit a recorded audio or video clip instead of text.';
$string['errornopermission'] = 'Could not access your microphone or camera. Please grant permission and try again.';
$string['errorunsupported'] = 'Recording is not supported by this browser.';
$string['erroruploadfailed'] = 'The recording could not be uploaded. Please try again.';
$string['existingrecording'] = 'Your current recording';
$string['kbrate'] = '{$a} kb/s';
$string['maxduration'] = 'Maximum recording length';
$string['maxduration_help'] = 'The longest a single recording may be. Recording stops automatically when this length is reached. Set to 0 seconds for no limit.';
$string['maxlength'] = 'Maximum length: {$a}';
$string['mode'] = 'Allowed recording type';
$string['mode_audio'] = 'Audio only';
$string['mode_both'] = 'Audio or video';
$string['mode_help'] = 'Choose whether students may submit audio, video, or either.';
$string['mode_video'] = 'Video only';
$string['norecordingfound'] = 'No recording was received.';
$string['nosubmission'] = 'Nothing has been submitted for this assignment';
$string['pluginname'] = 'Recording submission';
$string['previewready'] = 'Camera ready. Click "Start recording" when you are ready — the camera is not recording yet.';
$string['privacy:metadata'] = 'The Recording submission plugin stores each student\'s recorded audio or video submission alongside its embed HTML in the assignsubmission_recording table.';
$string['privacy:metadata:assignmentid'] = 'Assignment ID';
$string['privacy:metadata:filepurpose'] = 'The recorded audio or video file attached to the submission.';
$string['privacy:metadata:submissionpurpose'] = 'The submission ID that links to submissions for the user.';
$string['privacy:metadata:tablepurpose'] = 'Stores the recording submission for each attempt.';
$string['privacy:metadata:textpurpose'] = 'The embed HTML stored for this attempt of the assignment.';
$string['privacy:path'] = 'Recording submission';
$string['qualityheader'] = 'Recording quality';
$string['qualityheader_desc'] = 'These settings control the quality (and file size) of recordings, by setting the bitrates requested from the browser\'s recorder.';
$string['recordaudio'] = 'Record audio';
$string['recorded'] = 'Recording ready. You can submit it or record again.';
$string['recorderintro'] = 'Record your submission. Click the button below to start recording audio or video.';
$string['recording'] = 'Recording… click "Stop recording" when you have finished.';
$string['recordingnotallowed'] = 'That type of recording is not allowed for this assignment.';
$string['recordingstopped'] = 'Recording stopped automatically — the maximum length was reached.';
$string['recordvideo'] = 'Record video';
$string['rerecord'] = 'Record again';
$string['startrecording'] = 'Start recording';
$string['stoprecording'] = 'Stop recording';
$string['switchcamera'] = 'Switch camera';
$string['timeremaininglabel'] = 'Time remaining';
$string['uploadfailed'] = 'The recording upload failed.';
$string['uploading'] = 'Uploading your recording…';
$string['videobitrate'] = 'Video bitrate';
$string['videobitrate_desc'] = 'Quality of recorded video. Applies to video recordings only.';
