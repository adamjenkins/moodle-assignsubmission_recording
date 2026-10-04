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

namespace assignsubmission_recording\tests;

/**
 * Shared fixtures for the recording submission plugin's PHPUnit tests.
 *
 * @package    assignsubmission_recording
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait recording_test_helper {
    /**
     * Create an assignment with the recording submission plugin enabled.
     *
     * @param \stdClass $course the course
     * @param string $mode one of the assign_submission_recording::MODE_* values
     * @param int $maxduration maximum recording length in seconds (0 = no limit)
     * @param array $params extra assignment generator parameters
     * @return \assign
     */
    protected function create_recording_assign(
        \stdClass $course,
        string $mode = 'both',
        int $maxduration = 120,
        array $params = []
    ): \assign {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        require_once($CFG->dirroot . '/mod/assign/submission/recording/locallib.php');

        $instance = $this->getDataGenerator()->create_module('assign', $params + [
            'course' => $course->id,
            'assignsubmission_onlinetext_enabled' => 0,
            'assignsubmission_file_enabled' => 0,
            'assignsubmission_recording_enabled' => 1,
            'assignsubmission_recording_mode' => $mode,
            'assignsubmission_recording_maxduration' => $maxduration,
        ]);
        $cm = get_coursemodule_from_instance('assign', $instance->id);
        $context = \context_module::instance($cm->id);

        return new \assign($context, $cm, $course);
    }

    /**
     * Return bytes that finfo detects as video/webm (an EBML header with DocType "webm").
     *
     * @param int $size total length in bytes
     * @return string
     */
    protected function webm_bytes(int $size): string {
        $header = "\x1A\x45\xDF\xA3\x9F\x42\x86\x81\x01\x42\xF7\x81\x01\x42\xF2\x81\x04"
            . "\x42\xF3\x81\x08\x42\x82\x84webm\x42\x87\x81\x04\x42\x85\x81\x02";
        return $header . str_repeat("\0", max(0, $size - strlen($header)));
    }

    /**
     * Write content to a temporary file and return its path.
     *
     * @param string $content the file content
     * @return string path
     */
    protected function make_temp_file(string $content): string {
        $path = make_request_directory() . '/upload.tmp';
        file_put_contents($path, $content);
        return $path;
    }

    /**
     * Create a file in the current user's draft area.
     *
     * @param int $draftitemid the draft item id
     * @param string $filename the file name
     * @param string $content the file content
     * @return \stored_file
     */
    protected function create_draft_file(int $draftitemid, string $filename, string $content): \stored_file {
        global $USER;

        return get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
    }

    /**
     * Save a recording submission for a user through the plugin's own save() path.
     *
     * @param \assign $assign the assignment
     * @param \stdClass $user the student (becomes the current user)
     * @param string $tag 'audio' or 'video'
     * @param int $size recording size in bytes
     * @return array [\assign_submission_recording $plugin, \stdClass $submission]
     */
    protected function create_recording_submission(
        \assign $assign,
        \stdClass $user,
        string $tag = 'audio',
        int $size = 2000
    ): array {
        $this->setUser($user);
        $submission = $assign->get_user_submission($user->id, true);

        $draftitemid = file_get_unused_draft_itemid();
        $filename = $tag . '.webm';
        $this->create_draft_file($draftitemid, $filename, $this->webm_bytes($size));
        $url = \moodle_url::make_draftfile_url($draftitemid, '/', $filename)->out(false);

        $plugin = $assign->get_submission_plugin_by_type('recording');
        $data = (object) [
            'assignsubmission_recording_itemid' => $draftitemid,
            'assignsubmission_recording_text' => "<{$tag} controls><source src=\"{$url}\"></{$tag}>",
        ];
        $this->assertTrue($plugin->save($submission, $data), (string) $plugin->get_error());

        return [$plugin, $submission];
    }
}
