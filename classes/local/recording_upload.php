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

namespace assignsubmission_recording\local;

use moodle_exception;

/**
 * Validates an uploaded recording and stores it in the current user's draft area.
 *
 * upload.php handles the request (login, sesskey, parameters, the uploaded-file
 * checks) and hands the temporary file to store(). Every server-side rule about
 * what may be stored lives here so that it is unit tested.
 *
 * @package    assignsubmission_recording
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recording_upload {
    /**
     * Content types the recorder produces, mapped to the stored file extension.
     *
     * The type is detected from the file's contents, never taken from the
     * client-supplied filename or Content-Type header.
     */
    public const ALLOWED_MIMETYPES = [
        'audio/webm'      => 'webm',
        'video/webm'      => 'webm',
        'audio/mp4'       => 'mp4',
        'video/mp4'       => 'mp4',
        'audio/ogg'       => 'ogg',
        'video/ogg'       => 'ogg',
        'application/ogg' => 'ogg',
    ];

    /**
     * Validate a recording and store it in the current user's draft area.
     *
     * @param \context_module $context the assignment's module context
     * @param int $draftitemid the draft area item id from the submission form
     * @param string $mediatype 'audio' or 'video', as reported by the recorder
     * @param string $tmppath path of the uploaded temporary file
     * @return array url, filename and mimetype of the stored draft file
     * @throws moodle_exception when the recording is not acceptable
     */
    public static function store(\context_module $context, int $draftitemid, string $mediatype, string $tmppath): array {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        require_once($CFG->dirroot . '/mod/assign/submission/recording/locallib.php');

        // The user must have permission to submit to this assignment.
        require_capability('mod/assign:submit', $context);

        [$course, $cm] = get_course_and_cm_from_cmid($context->instanceid, 'assign');
        $assign = new \assign($context, $cm, $course);
        /** @var \assign_submission_recording|null $plugin */
        $plugin = $assign->get_plugin_by_type('assignsubmission', 'recording');

        if (!in_array($mediatype, ['audio', 'video'], true)) {
            throw new moodle_exception('recordingnotallowed', 'assignsubmission_recording');
        }

        if ($plugin) {
            $mode = $plugin->get_mode();
            if (
                ($mode === \assign_submission_recording::MODE_AUDIO && $mediatype !== 'audio')
                || ($mode === \assign_submission_recording::MODE_VIDEO && $mediatype !== 'video')
            ) {
                throw new moodle_exception('recordingnotallowed', 'assignsubmission_recording');
            }
        }

        $size = (int) filesize($tmppath);

        // Enforce the course/site upload size limit server-side.
        $maxbytes = get_max_upload_file_size($CFG->maxbytes, $course->maxbytes);
        if ($maxbytes > 0 && $size > $maxbytes) {
            throw new moodle_exception('uploadfailed', 'assignsubmission_recording');
        }

        // Enforce the assignment's maximum recording length server-side as a size ceiling
        // (the recorder's auto-stop is only a browser convenience). In an audio-only
        // assignment the ceiling is the audio one, so a video cannot be passed off as audio
        // beyond a few seconds; save() also rejects a <video> embed there.
        if ($plugin) {
            $maxrecordingbytes = $plugin->get_max_recording_bytes($mediatype);
            if ($maxrecordingbytes > 0 && $size > $maxrecordingbytes) {
                throw new moodle_exception('recordingtoolong', 'assignsubmission_recording');
            }
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedmimetype = $finfo ? finfo_file($finfo, $tmppath) : false;
        if ($finfo) {
            finfo_close($finfo);
        }

        if (!$detectedmimetype || !isset(self::ALLOWED_MIMETYPES[$detectedmimetype])) {
            throw new moodle_exception('recordingnotallowed', 'assignsubmission_recording');
        }

        $fs = get_file_storage();
        $usercontext = \context_user::instance($USER->id);

        // Server-generated filename: the extension comes from the detected mimetype above,
        // never from the client-supplied name, so a spoofed extension cannot smuggle an
        // executable file type past this check.
        $filename = ($mediatype === 'video' ? 'video' : 'audio') . '.' . self::ALLOWED_MIMETYPES[$detectedmimetype];
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

        $storedfile = $fs->create_file_from_pathname($filerecord, $tmppath);

        return [
            'url'      => \moodle_url::make_draftfile_url($draftitemid, '/', $filename)->out(false),
            'filename' => $filename,
            'mimetype' => $storedfile->get_mimetype(),
        ];
    }
}
