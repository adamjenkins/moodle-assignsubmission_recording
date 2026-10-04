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
 * Tests for the access rules behind assignsubmission_recording_pluginfile().
 *
 * @package    assignsubmission_recording
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(file_access::class)]
#[\PHPUnit\Framework\Attributes\CoversFunction('assignsubmission_recording_pluginfile')]
final class file_access_test extends \advanced_testcase {
    use recording_test_helper;

    /** @var \stdClass the course */
    private \stdClass $course;

    /** @var \stdClass the student who owns the submission */
    private \stdClass $owner;

    /** @var \assign the assignment */
    private \assign $assign;

    /** @var \stdClass the owner's submission */
    private \stdClass $submission;

    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/assign/submission/recording/lib.php');

        $this->course = $this->getDataGenerator()->create_course();
        $this->owner = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->assign = $this->create_recording_assign($this->course);
        [, $this->submission] = $this->create_recording_submission($this->assign, $this->owner, 'audio');
    }

    /**
     * Resolve a request for a file in the owner's submission as the current user.
     *
     * @param string $filename the requested file name
     * @param bool $forcedownload whether the request asks for a download
     * @param int|null $itemid submission id override
     * @return array|null
     */
    private function resolve(string $filename = 'audio.webm', bool $forcedownload = false, ?int $itemid = null): ?array {
        return file_access::get_servable_file(
            $this->assign->get_context(),
            $this->course,
            $this->assign->get_course_module(),
            'submissions_recording',
            [$itemid ?? $this->submission->id, $filename],
            $forcedownload
        );
    }

    public function test_owner_gets_media_inline(): void {
        $this->setUser($this->owner);

        $result = $this->resolve();

        $this->assertNotNull($result);
        $this->assertSame('audio.webm', $result['file']->get_filename());
        $this->assertFalse($result['forcedownload']);
    }

    public function test_requested_download_is_honoured(): void {
        $this->setUser($this->owner);

        $this->assertTrue($this->resolve('audio.webm', true)['forcedownload']);
    }

    public function test_non_media_file_is_forced_to_download(): void {
        // A file that reached the submission area by another route than upload.php.
        get_file_storage()->create_file_from_string([
            'contextid' => $this->assign->get_context()->id,
            'component' => 'assignsubmission_recording',
            'filearea' => 'submissions_recording',
            'itemid' => $this->submission->id,
            'filepath' => '/',
            'filename' => 'evil.html',
        ], '<script>alert(1)</script>');
        $this->setUser($this->owner);

        $result = $this->resolve('evil.html');

        $this->assertNotNull($result);
        $this->assertSame('text/html', $result['file']->get_mimetype());
        $this->assertTrue($result['forcedownload']);
    }

    public function test_teacher_can_view_student_recording(): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($teacher);

        $this->assertNotNull($this->resolve());
    }

    public function test_other_student_cannot_view_recording(): void {
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($other);

        $this->assertNull($this->resolve());
    }

    public function test_submission_from_another_assignment_is_refused(): void {
        // The owner's submission in a second assignment, requested through the first one's context.
        $otherassign = $this->create_recording_assign($this->course);
        [, $othersubmission] = $this->create_recording_submission($otherassign, $this->owner, 'audio');
        // Put a file at the requested path so that only the assignment check can refuse it.
        get_file_storage()->create_file_from_string([
            'contextid' => $this->assign->get_context()->id,
            'component' => 'assignsubmission_recording',
            'filearea' => 'submissions_recording',
            'itemid' => $othersubmission->id,
            'filepath' => '/',
            'filename' => 'audio.webm',
        ], $this->webm_bytes(2000));
        $this->setUser($this->owner);

        $this->assertNull($this->resolve('audio.webm', false, (int) $othersubmission->id));
    }

    public function test_missing_file_is_refused(): void {
        $this->setUser($this->owner);

        $this->assertNull($this->resolve('nosuchfile.webm'));
    }

    public function test_pluginfile_refuses_other_file_areas_and_contexts(): void {
        $this->setUser($this->owner);
        $cm = $this->assign->get_course_module();
        $args = [$this->submission->id, 'audio.webm'];

        $this->assertFalse(assignsubmission_recording_pluginfile(
            $this->course,
            $cm,
            $this->assign->get_context(),
            'submissions_onlinetext',
            $args,
            false
        ));
        $this->assertFalse(assignsubmission_recording_pluginfile(
            $this->course,
            $cm,
            \context_course::instance($this->course->id),
            'submissions_recording',
            $args,
            false
        ));
        $this->assertNull(file_access::get_servable_file(
            $this->assign->get_context(),
            $this->course,
            $cm,
            'submissions_onlinetext',
            $args
        ));
    }
}
