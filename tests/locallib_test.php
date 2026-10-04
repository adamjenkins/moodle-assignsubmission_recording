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

namespace assignsubmission_recording;

use assignsubmission_recording\tests\recording_test_helper;

/**
 * Tests for the recording submission plugin class (save, rendering, empty checks).
 *
 * @package    assignsubmission_recording
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\assign_submission_recording::class)]
final class locallib_test extends \advanced_testcase {
    use recording_test_helper;

    /** @var \stdClass the course */
    private \stdClass $course;

    /** @var \stdClass an enrolled student */
    private \stdClass $student;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        set_config('audiobitrate', 24000, 'assignsubmission_recording');
    }

    /**
     * Try to save a submission whose draft area holds one recording of the given size.
     *
     * @param \assign $assign the assignment
     * @param string $tag the embed tag written into the hidden text field
     * @param int $size the size of the draft recording in bytes
     * @return array [bool $saved, \assign_submission_recording $plugin, \stdClass $submission]
     */
    private function try_save(\assign $assign, string $tag, int $size = 2000): array {
        $this->setUser($this->student);
        $submission = $assign->get_user_submission($this->student->id, true);
        $draftitemid = file_get_unused_draft_itemid();
        $this->create_draft_file($draftitemid, 'rec.webm', $this->webm_bytes($size));
        $url = \moodle_url::make_draftfile_url($draftitemid, '/', 'rec.webm')->out(false);

        $plugin = $assign->get_submission_plugin_by_type('recording');
        $saved = $plugin->save($submission, (object) [
            'assignsubmission_recording_itemid' => $draftitemid,
            'assignsubmission_recording_text' => "<{$tag} controls><source src=\"{$url}\"></{$tag}>",
        ]);

        return [$saved, $plugin, $submission];
    }

    /**
     * Count the files stored in a submission's recording area.
     *
     * @param \assign $assign the assignment
     * @param \stdClass $submission the submission
     * @return int
     */
    private function count_submission_files(\assign $assign, \stdClass $submission): int {
        return count(get_file_storage()->get_area_files(
            $assign->get_context()->id,
            'assignsubmission_recording',
            'submissions_recording',
            $submission->id,
            'id',
            false
        ));
    }

    public function test_save_stores_embed_and_moves_file(): void {
        global $DB;
        $assign = $this->create_recording_assign($this->course, 'audio');

        [$saved, $plugin, $submission] = $this->try_save($assign, 'audio');

        $this->assertTrue($saved);
        $record = $DB->get_record('assignsubmission_recording', ['submission' => $submission->id], '*', MUST_EXIST);
        $this->assertStringContainsString('@@PLUGINFILE@@/rec.webm', $record->recordingtext);
        $this->assertEquals(1, $this->count_submission_files($assign, $submission));
        $this->assertFalse($plugin->is_empty($submission));
    }

    public function test_save_rejects_video_embed_in_audio_only_assignment(): void {
        global $DB;
        $assign = $this->create_recording_assign($this->course, 'audio');

        [$saved, $plugin, $submission] = $this->try_save($assign, 'video');

        $this->assertFalse($saved);
        $this->assertSame(get_string('recordingnotallowed', 'assignsubmission_recording'), $plugin->get_error());
        $this->assertFalse($DB->record_exists('assignsubmission_recording', ['submission' => $submission->id]));
        $this->assertEquals(0, $this->count_submission_files($assign, $submission));
    }

    public function test_save_rejects_audio_embed_in_video_only_assignment(): void {
        global $DB;
        $assign = $this->create_recording_assign($this->course, 'video');

        [$saved, , $submission] = $this->try_save($assign, 'audio');

        $this->assertFalse($saved);
        $this->assertFalse($DB->record_exists('assignsubmission_recording', ['submission' => $submission->id]));
    }

    public function test_save_accepts_either_embed_when_both_are_allowed(): void {
        $assign = $this->create_recording_assign($this->course, 'both');

        [$saved] = $this->try_save($assign, 'video');

        $this->assertTrue($saved);
    }

    public function test_save_rejects_draft_recording_longer_than_maxduration(): void {
        global $DB;
        // A file that reached the draft area without upload.php (e.g. a repository upload).
        $assign = $this->create_recording_assign($this->course, 'audio', 5);

        [$saved, $plugin, $submission] = $this->try_save($assign, 'audio', 500000);

        $this->assertFalse($saved);
        $this->assertSame(get_string('recordingtoolong', 'assignsubmission_recording'), $plugin->get_error());
        $this->assertFalse($DB->record_exists('assignsubmission_recording', ['submission' => $submission->id]));
        $this->assertEquals(0, $this->count_submission_files($assign, $submission));
    }

    public function test_save_holds_audio_embed_to_audio_ceiling_when_both_are_allowed(): void {
        set_config('videobitrate', 1000000, 'assignsubmission_recording');
        $assign = $this->create_recording_assign($this->course, 'both', 5);
        $plugin = $assign->get_submission_plugin_by_type('recording');
        $size = 500000;
        // Larger than a 5-second audio recording may be, but within the video ceiling.
        $this->assertGreaterThan($plugin->get_max_recording_bytes('audio'), $size);
        $this->assertLessThan($plugin->get_max_recording_bytes('video'), $size);

        [$saved, $plugin] = $this->try_save($assign, 'audio', $size);
        $this->assertFalse($saved);
        $this->assertSame(get_string('recordingtoolong', 'assignsubmission_recording'), $plugin->get_error());

        [$saved] = $this->try_save($assign, 'video', $size);
        $this->assertTrue($saved);
    }

    public function test_max_recording_bytes(): void {
        set_config('videobitrate', 1000000, 'assignsubmission_recording');
        $assign = $this->create_recording_assign($this->course, 'both', 10);
        $plugin = $assign->get_submission_plugin_by_type('recording');

        $audio = $plugin->get_max_recording_bytes('audio');
        $video = $plugin->get_max_recording_bytes('video');

        // Never below the nominal size of a full-length recording at the configured bitrate.
        $this->assertGreaterThanOrEqual(10 * 24000 / 8, $audio);
        $this->assertGreaterThanOrEqual(10 * (1000000 + 24000) / 8, $video);
        $this->assertGreaterThan($audio, $video);

        $unlimited = $this->create_recording_assign($this->course, 'both', 0);
        $this->assertSame(0, $unlimited->get_submission_plugin_by_type('recording')->get_max_recording_bytes('video'));
    }

    public function test_view_purifies_stored_embed(): void {
        global $DB;
        $assign = $this->create_recording_assign($this->course);
        [$plugin, $submission] = $this->create_recording_submission($assign, $this->student, 'audio');
        $DB->set_field(
            'assignsubmission_recording',
            'recordingtext',
            '<audio controls><source src="@@PLUGINFILE@@/audio.webm"></audio><script>alert(1)</script>'
                . '<img src="x" onerror="alert(2)">',
            ['submission' => $submission->id]
        );

        $html = $plugin->view($submission);

        $this->assertStringContainsString('<audio', $html);
        $this->assertStringContainsString('/pluginfile.php/', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }

    public function test_submission_is_empty_without_media_embed(): void {
        $assign = $this->create_recording_assign($this->course);
        $plugin = $assign->get_submission_plugin_by_type('recording');

        $this->assertTrue($plugin->submission_is_empty((object) ['assignsubmission_recording_text' => '<p>hello</p>']));
        $this->assertFalse($plugin->submission_is_empty((object) ['assignsubmission_recording_text' => '<audio></audio>']));
    }
}
