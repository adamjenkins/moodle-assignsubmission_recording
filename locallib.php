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
 * Library class for the recording submission plugin.
 *
 * @package    assignsubmission_recording
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** @var string File area used to store submitted recordings. */
define('ASSIGNSUBMISSION_RECORDING_FILEAREA', 'submissions_recording');

/**
 * Library class for the recording submission plugin extending the submission plugin base class.
 *
 * @package    assignsubmission_recording
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_submission_recording extends assign_submission_plugin {
    /** @var string Allow audio recordings only. */
    const MODE_AUDIO = 'audio';

    /** @var string Allow video recordings only. */
    const MODE_VIDEO = 'video';

    /** @var string Allow both audio and video recordings. */
    const MODE_BOTH = 'both';

    /** @var int Default maximum recording length in seconds. */
    const DEFAULT_MAX_DURATION = 120;

    /** @var int Default audio bitrate in bits per second. */
    const DEFAULT_AUDIO_BITRATE = 128000;

    /** @var int Default video bitrate in bits per second. */
    const DEFAULT_VIDEO_BITRATE = 2500000;

    /**
     * Return the plugin name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', 'assignsubmission_recording');
    }

    /**
     * Return the recording submission record for a submission, or null.
     *
     * @param int $submissionid
     * @return \stdClass|null
     */
    private function get_recording_submission(int $submissionid): ?\stdClass {
        global $DB;

        if (empty($submissionid)) {
            return null;
        }

        $record = $DB->get_record('assignsubmission_recording', ['submission' => $submissionid]);

        return $record ?: null;
    }

    /**
     * Add the per-assignment settings form elements.
     *
     * @param MoodleQuickForm $mform the form to add elements to
     * @return void
     */
    public function get_settings(MoodleQuickForm $mform) {
        $modedefault = $this->get_config('mode') ?: self::MODE_BOTH;
        $maxdurationdefault = $this->get_config('maxduration');
        if ($maxdurationdefault === false) {
            $maxdurationdefault = self::DEFAULT_MAX_DURATION;
        }

        $mform->addElement(
            'select',
            'assignsubmission_recording_mode',
            get_string('mode', 'assignsubmission_recording'),
            $this->get_mode_menu()
        );
        $mform->setType('assignsubmission_recording_mode', PARAM_ALPHA);
        $mform->setDefault('assignsubmission_recording_mode', $modedefault);
        $mform->addHelpButton('assignsubmission_recording_mode', 'mode', 'assignsubmission_recording');
        $mform->hideIf('assignsubmission_recording_mode', 'assignsubmission_recording_enabled', 'notchecked');

        $mform->addElement(
            'duration',
            'assignsubmission_recording_maxduration',
            get_string('maxduration', 'assignsubmission_recording'),
            ['optional' => false, 'defaultunit' => 1]
        );
        $mform->setType('assignsubmission_recording_maxduration', PARAM_INT);
        $mform->setDefault('assignsubmission_recording_maxduration', (int) $maxdurationdefault);
        $mform->addHelpButton('assignsubmission_recording_maxduration', 'maxduration', 'assignsubmission_recording');
        $mform->hideIf('assignsubmission_recording_maxduration', 'assignsubmission_recording_enabled', 'notchecked');
    }

    /**
     * Save the per-assignment settings.
     *
     * @param \stdClass $data the submitted form data
     * @return bool
     */
    public function save_settings(stdClass $data) {
        $mode = $data->assignsubmission_recording_mode ?? self::MODE_BOTH;
        if (!in_array($mode, [self::MODE_AUDIO, self::MODE_VIDEO, self::MODE_BOTH], true)) {
            $mode = self::MODE_BOTH;
        }
        $this->set_config('mode', $mode);

        $maxduration = (int) ($data->assignsubmission_recording_maxduration ?? self::DEFAULT_MAX_DURATION);
        if ($maxduration < 0) {
            $maxduration = self::DEFAULT_MAX_DURATION;
        }
        $this->set_config('maxduration', $maxduration);

        return true;
    }

    /**
     * Add the recorder form elements to the student submission form.
     *
     * @param \stdClass|null $submission the current submission or null for a new one
     * @param MoodleQuickForm $mform the form
     * @param \stdClass $data form data (may be pre-populated for re-submissions)
     * @return bool true if elements were added
     */
    public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data) {
        global $PAGE, $USER;

        $submissionid = $submission ? $submission->id : 0;

        // Prepare the draft file area — this creates a new draft area and copies in any existing files.
        $draftitemid = 0;
        file_prepare_draft_area(
            $draftitemid,
            $this->assignment->get_context()->id,
            'assignsubmission_recording',
            ASSIGNSUBMISSION_RECORDING_FILEAREA,
            $submissionid,
            ['subdirs' => 0, 'maxfiles' => 1]
        );

        // Load any existing recording and convert stored @@PLUGINFILE@@ refs to draftfile URLs.
        $existingtext = '';
        if ($submissionid) {
            $recordingsubmission = $this->get_recording_submission($submissionid);
            if ($recordingsubmission && !empty($recordingsubmission->recordingtext)) {
                $existingtext = file_rewrite_pluginfile_urls(
                    $recordingsubmission->recordingtext,
                    'draftfile.php',
                    context_user::instance($USER->id)->id,
                    'user',
                    'draft',
                    $draftitemid
                );
            }
        }

        // Hidden field: draft file area item id.
        $mform->addElement('hidden', 'assignsubmission_recording_itemid', $draftitemid);
        $mform->setType('assignsubmission_recording_itemid', PARAM_INT);

        // Hidden field: the recording embed HTML (written by JS after upload).
        $mform->addElement('hidden', 'assignsubmission_recording_text', $existingtext);
        $mform->setType('assignsubmission_recording_text', PARAM_RAW);

        // Placeholder div into which the AMD module renders the recorder UI.
        $mform->addElement('html', '<div data-region="assignsubmission-recording-recorder" class="mb-3"></div>');

        // Initialise the frontend recorder.
        $mode = $this->get_mode();
        $maxduration = $this->get_max_duration();

        $PAGE->requires->js_call_amd('assignsubmission_recording/recorder', 'init', [[
            'contextid'       => $this->assignment->get_context()->id,
            'submissionid'    => $submissionid,
            'mode'            => $mode,
            'maxduration'     => $maxduration,
            'audiobitrate'    => self::get_audio_bitrate(),
            'videobitrate'    => self::get_video_bitrate(),
            'allowswitchcamera' => self::allow_switch_camera(),
            'existingtext'    => $existingtext,
        ]]);

        return true;
    }

    /**
     * Save the student's recording submission.
     *
     * @param \stdClass $submission the submission record
     * @param \stdClass $data the submitted form data
     * @return bool
     */
    public function save(stdClass $submission, stdClass $data) {
        global $DB;

        $draftitemid = (int) ($data->assignsubmission_recording_itemid ?? 0);
        $text = $data->assignsubmission_recording_text ?? '';

        // Move files from draft area to submission area and rewrite embed URLs to @@PLUGINFILE@@.
        $text = file_save_draft_area_files(
            $draftitemid,
            $this->assignment->get_context()->id,
            'assignsubmission_recording',
            ASSIGNSUBMISSION_RECORDING_FILEAREA,
            $submission->id,
            ['subdirs' => 0, 'maxfiles' => 1],
            $text
        );

        $recordingsubmission = $this->get_recording_submission($submission->id);

        if ($recordingsubmission) {
            $recordingsubmission->recordingtext   = $text;
            $recordingsubmission->recordingformat = FORMAT_HTML;
            $DB->update_record('assignsubmission_recording', $recordingsubmission);
        } else {
            $recordingsubmission = (object) [
                'assignment'      => $this->assignment->get_instance()->id,
                'submission'      => $submission->id,
                'recordingtext'   => $text,
                'recordingformat' => FORMAT_HTML,
            ];
            $DB->insert_record('assignsubmission_recording', $recordingsubmission);
        }

        return true;
    }

    /**
     * Display a summary of the recording in the submission status table.
     *
     * @param \stdClass $submission the submission record
     * @param bool $showviewlink set to true if a "view more" link should be shown
     * @return string HTML
     */
    public function view_summary(stdClass $submission, &$showviewlink) {
        $recordingsubmission = $this->get_recording_submission($submission->id);
        if (!$recordingsubmission || empty($recordingsubmission->recordingtext)) {
            return '';
        }

        $showviewlink = false;

        return $this->render_recording($recordingsubmission->recordingtext, $submission->id);
    }

    /**
     * Display the full recording in the submission view.
     *
     * @param \stdClass $submission the submission record
     * @return string HTML
     */
    public function view(stdClass $submission) {
        $recordingsubmission = $this->get_recording_submission($submission->id);
        if (!$recordingsubmission || empty($recordingsubmission->recordingtext)) {
            return '';
        }

        return $this->render_recording($recordingsubmission->recordingtext, $submission->id);
    }

    /**
     * Rewrite @@PLUGINFILE@@ URLs in embed HTML and return it ready to output.
     *
     * @param string $text the stored embed HTML containing @@PLUGINFILE@@ placeholders
     * @param int $submissionid the submission id used as the file area item id
     * @return string HTML safe to output
     */
    private function render_recording(string $text, int $submissionid): string {
        $text = file_rewrite_pluginfile_urls(
            $text,
            'pluginfile.php',
            $this->assignment->get_context()->id,
            'assignsubmission_recording',
            ASSIGNSUBMISSION_RECORDING_FILEAREA,
            $submissionid
        );

        return format_text($text, FORMAT_HTML, ['context' => $this->assignment->get_context()]);
    }

    /**
     * Whether the submission is empty (no recording stored).
     *
     * @param \stdClass $submission the submission record
     * @return bool
     */
    public function is_empty(stdClass $submission) {
        $recordingsubmission = $this->get_recording_submission($submission->id);

        if (!$recordingsubmission || empty($recordingsubmission->recordingtext)) {
            return true;
        }

        return !preg_match('/<\s*(audio|video)[^>]*>/i', $recordingsubmission->recordingtext);
    }

    /**
     * Whether the form data about to be saved is empty (no recording submitted).
     *
     * @param \stdClass $data the submitted form data
     * @return bool
     */
    public function submission_is_empty(stdClass $data) {
        $text = $data->assignsubmission_recording_text ?? '';

        return !preg_match('/<\s*(audio|video)[^>]*>/i', (string) $text);
    }

    /**
     * Return the list of file areas this plugin uses.
     *
     * @return array filearea name => description
     */
    public function get_file_areas() {
        return [ASSIGNSUBMISSION_RECORDING_FILEAREA => $this->get_name()];
    }

    /**
     * Delete all submission records for this assignment instance.
     *
     * @return bool
     */
    public function delete_instance() {
        global $DB;

        $DB->delete_records(
            'assignsubmission_recording',
            ['assignment' => $this->assignment->get_instance()->id]
        );

        return true;
    }

    /**
     * Remove a single submission record.
     *
     * @param \stdClass $submission the submission record
     * @return bool
     */
    public function remove(stdClass $submission) {
        global $DB;

        $submissionid = $submission ? $submission->id : 0;
        if ($submissionid) {
            $DB->delete_records('assignsubmission_recording', ['submission' => $submissionid]);
        }

        return true;
    }

    /**
     * Copy a submission (used when a student opts to base a resubmission on their previous attempt).
     *
     * @param \stdClass $sourcesubmission the source submission
     * @param \stdClass $destsubmission the destination submission
     * @return bool
     */
    public function copy_submission(stdClass $sourcesubmission, stdClass $destsubmission) {
        global $DB;

        // Copy stored recording files.
        $contextid = $this->assignment->get_context()->id;
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $contextid,
            'assignsubmission_recording',
            ASSIGNSUBMISSION_RECORDING_FILEAREA,
            $sourcesubmission->id,
            'id',
            false
        );
        foreach ($files as $file) {
            $fs->create_file_from_storedfile(['itemid' => $destsubmission->id], $file);
        }

        // Copy the DB record.
        $recordingsubmission = $this->get_recording_submission($sourcesubmission->id);
        if ($recordingsubmission) {
            unset($recordingsubmission->id);
            $recordingsubmission->submission = $destsubmission->id;
            $DB->insert_record('assignsubmission_recording', $recordingsubmission);
        }

        return true;
    }

    /**
     * Return the plugin configuration for external functions.
     *
     * @return array
     */
    public function get_config_for_external() {
        return (array) $this->get_config();
    }



    /**
     * Return the allowed recording mode for this assignment.
     *
     * @return string one of the MODE_* constants
     */
    private function get_mode(): string {
        $mode = $this->get_config('mode');

        if (!$mode || !in_array($mode, [self::MODE_AUDIO, self::MODE_VIDEO, self::MODE_BOTH], true)) {
            return self::MODE_BOTH;
        }

        return $mode;
    }

    /**
     * Return the maximum recording length for this assignment in seconds.
     *
     * @return int seconds, or 0 for no limit
     */
    private function get_max_duration(): int {
        $value = $this->get_config('maxduration');

        if ($value === false || $value === null) {
            return self::DEFAULT_MAX_DURATION;
        }

        return max(0, (int) $value);
    }

    /**
     * Return the site-wide audio bitrate in bits per second.
     *
     * @return int
     */
    private static function get_audio_bitrate(): int {
        $bitrate = (int) get_config('assignsubmission_recording', 'audiobitrate');

        return $bitrate > 0 ? $bitrate : self::DEFAULT_AUDIO_BITRATE;
    }

    /**
     * Return the site-wide video bitrate in bits per second.
     *
     * @return int
     */
    private static function get_video_bitrate(): int {
        $bitrate = (int) get_config('assignsubmission_recording', 'videobitrate');

        return $bitrate > 0 ? $bitrate : self::DEFAULT_VIDEO_BITRATE;
    }

    /**
     * Whether the "Switch camera" button should be offered during video preview.
     *
     * @return bool
     */
    private static function allow_switch_camera(): bool {
        return (bool) get_config('assignsubmission_recording', 'allowswitchcamera');
    }

    /**
     * Return the list of selectable recording modes for the settings form.
     *
     * @return array mode value => label
     */
    private function get_mode_menu(): array {
        return [
            self::MODE_BOTH  => get_string('mode_both', 'assignsubmission_recording'),
            self::MODE_AUDIO => get_string('mode_audio', 'assignsubmission_recording'),
            self::MODE_VIDEO => get_string('mode_video', 'assignsubmission_recording'),
        ];
    }
}
