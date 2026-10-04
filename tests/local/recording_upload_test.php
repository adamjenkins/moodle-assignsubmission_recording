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

use assignsubmission_recording\tests\recording_test_helper;

/**
 * Tests for the server-side rules applied to an uploaded recording (upload.php).
 *
 * @package    assignsubmission_recording
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(recording_upload::class)]
final class recording_upload_test extends \advanced_testcase {
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
        // A small, known bitrate so the duration-derived size ceiling stays well below
        // PHP's upload_max_filesize on the test runner.
        set_config('audiobitrate', 24000, 'assignsubmission_recording');
    }

    /**
     * Return the files in the current user's draft area.
     *
     * @param int $draftitemid the draft item id
     * @return \stored_file[]
     */
    private function draft_files(int $draftitemid): array {
        global $USER;
        return get_file_storage()->get_area_files(
            \context_user::instance($USER->id)->id,
            'user',
            'draft',
            $draftitemid,
            'id',
            false
        );
    }

    /**
     * Assert that store() throws a moodle_exception with the given error code and stores nothing.
     *
     * @param string $errorcode the expected error code
     * @param \assign $assign the assignment
     * @param string $mediatype the client-reported media type
     * @param string $content the uploaded content
     */
    private function assert_rejected(string $errorcode, \assign $assign, string $mediatype, string $content): void {
        $draftitemid = file_get_unused_draft_itemid();
        try {
            recording_upload::store($assign->get_context(), $draftitemid, $mediatype, $this->make_temp_file($content));
            $this->fail("Expected the upload to be rejected with '{$errorcode}'");
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode);
        }
        $this->assertEmpty($this->draft_files($draftitemid));
    }

    public function test_store_accepts_recording_into_own_draft_area(): void {
        $assign = $this->create_recording_assign($this->course);
        $this->setUser($this->student);
        $draftitemid = file_get_unused_draft_itemid();

        $result = recording_upload::store(
            $assign->get_context(),
            $draftitemid,
            'audio',
            $this->make_temp_file($this->webm_bytes(4000))
        );

        $this->assertSame('audio.webm', $result['filename']);
        $this->assertStringContainsString('/draftfile.php/', $result['url']);
        $files = $this->draft_files($draftitemid);
        $this->assertCount(1, $files);
        $file = reset($files);
        $this->assertSame('audio.webm', $file->get_filename());
        $this->assertEquals($this->student->id, $file->get_userid());
    }

    public function test_store_rejects_non_media_content(): void {
        $assign = $this->create_recording_assign($this->course);
        $this->setUser($this->student);

        $this->assert_rejected('recordingnotallowed', $assign, 'audio', '<html><body><script>alert(1)</script></body></html>');
    }

    public function test_store_requires_submit_capability(): void {
        $assign = $this->create_recording_assign($this->course);
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(\required_capability_exception::class);
        recording_upload::store(
            $assign->get_context(),
            file_get_unused_draft_itemid(),
            'audio',
            $this->make_temp_file($this->webm_bytes(4000))
        );
    }

    public function test_store_enforces_site_upload_limit(): void {
        global $CFG;
        $CFG->maxbytes = 3000;
        $assign = $this->create_recording_assign($this->course, 'both', 0);
        $this->setUser($this->student);

        $this->assert_rejected('uploadfailed', $assign, 'audio', $this->webm_bytes(4000));
    }

    public function test_store_rejects_mediatype_outside_assignment_mode(): void {
        $assign = $this->create_recording_assign($this->course, 'audio');
        $this->setUser($this->student);

        $this->assert_rejected('recordingnotallowed', $assign, 'video', $this->webm_bytes(4000));
    }

    public function test_store_rejects_unknown_mediatype(): void {
        $assign = $this->create_recording_assign($this->course, 'both');
        $this->setUser($this->student);

        $this->assert_rejected('recordingnotallowed', $assign, 'document', $this->webm_bytes(4000));
    }

    public function test_store_rejects_recording_longer_than_maxduration(): void {
        // 5 seconds at 24 kb/s is about 15 kB of audio; the ceiling (with headroom and the
        // container allowance) is about 292 kB, so 500 kB is well over it.
        $assign = $this->create_recording_assign($this->course, 'audio', 5);
        $this->setUser($this->student);

        $this->assert_rejected('recordingtoolong', $assign, 'audio', $this->webm_bytes(500000));
    }

    public function test_store_accepts_recording_within_maxduration(): void {
        $assign = $this->create_recording_assign($this->course, 'audio', 5);
        $this->setUser($this->student);
        $draftitemid = file_get_unused_draft_itemid();

        recording_upload::store($assign->get_context(), $draftitemid, 'audio', $this->make_temp_file($this->webm_bytes(15000)));

        $this->assertCount(1, $this->draft_files($draftitemid));
    }

    public function test_store_has_no_duration_ceiling_when_maxduration_is_zero(): void {
        $assign = $this->create_recording_assign($this->course, 'audio', 0);
        $this->setUser($this->student);
        $draftitemid = file_get_unused_draft_itemid();

        recording_upload::store($assign->get_context(), $draftitemid, 'audio', $this->make_temp_file($this->webm_bytes(500000)));

        $this->assertCount(1, $this->draft_files($draftitemid));
    }
}
