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

namespace assignsubmission_recording\privacy;

use assignsubmission_recording\tests\recording_test_helper;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;
use mod_assign\privacy\assign_plugin_request_data;

/**
 * Privacy provider tests for the recording submission plugin.
 *
 * @package    assignsubmission_recording
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \mod_assign\tests\provider_testcase {
    use recording_test_helper;

    /**
     * Whether a submission still has a recording row.
     *
     * @param \stdClass $submission the submission
     * @return bool
     */
    private function has_row(\stdClass $submission): bool {
        global $DB;
        return $DB->record_exists('assignsubmission_recording', ['submission' => $submission->id]);
    }

    /**
     * Number of stored files in a submission's recording area.
     *
     * @param \assign $assign the assignment
     * @param \stdClass $submission the submission
     * @return int
     */
    private function file_count(\assign $assign, \stdClass $submission): int {
        return count(get_file_storage()->get_area_files(
            $assign->get_context()->id,
            'assignsubmission_recording',
            'submissions_recording',
            $submission->id,
            'id',
            false
        ));
    }

    /**
     * Create a course, an assignment and recording submissions for three students.
     *
     * @return array [\assign $assign, \stdClass[] $students, \stdClass[] $submissions]
     */
    private function setup_submissions(): array {
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->create_recording_assign($course);
        $students = [];
        $submissions = [];
        foreach ([1, 2, 3] as $i) {
            $students[$i] = $this->getDataGenerator()->create_and_enrol($course, 'student');
            [, $submissions[$i]] = $this->create_recording_submission($assign, $students[$i], 'audio');
            $this->assertTrue($this->has_row($submissions[$i]));
            $this->assertEquals(1, $this->file_count($assign, $submissions[$i]));
        }
        return [$assign, $students, $submissions];
    }

    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('assignsubmission_recording'));
        $items = $collection->get_collection();

        $this->assertCount(2, $items);
        $table = $items[0];
        $this->assertSame('assignsubmission_recording', $table->get_name());
        $this->assertEquals(['assignment', 'submission', 'recordingtext'], array_keys($table->get_privacy_fields()));
        $this->assertSame('core_files', $items[1]->get_name());
    }

    public function test_export_submission_user_data(): void {
        $this->resetAfterTest();
        [$assign, , $submissions] = $this->setup_submissions();
        $context = $assign->get_context();

        $exportdata = new assign_plugin_request_data($context, $assign, $submissions[1], ['Attempt 1']);
        provider::export_submission_user_data($exportdata);

        $path = ['Attempt 1', get_string('privacy:path', 'assignsubmission_recording')];
        $writer = writer::with_context($context);
        $data = $writer->get_data($path);
        $this->assertStringContainsString('<audio', $data->text);
        $this->assertStringNotContainsString('@@PLUGINFILE@@', $data->text);
        $files = $writer->get_files($path);
        $this->assertArrayHasKey('audio.webm', $files);
        $this->assertInstanceOf(\stored_file::class, $files['audio.webm']);
        $this->assertEquals($submissions[1]->id, $files['audio.webm']->get_itemid());
    }

    public function test_delete_submission_for_context(): void {
        $this->resetAfterTest();
        [$assign, , $submissions] = $this->setup_submissions();

        provider::delete_submission_for_context(new assign_plugin_request_data($assign->get_context(), $assign));

        foreach ($submissions as $submission) {
            $this->assertFalse($this->has_row($submission));
            $this->assertEquals(0, $this->file_count($assign, $submission));
        }
    }

    public function test_delete_submission_for_userid(): void {
        $this->resetAfterTest();
        [$assign, $students, $submissions] = $this->setup_submissions();

        $deletedata = new assign_plugin_request_data($assign->get_context(), $assign, $submissions[1], [], $students[1]);
        provider::delete_submission_for_userid($deletedata);

        $this->assertFalse($this->has_row($submissions[1]));
        $this->assertEquals(0, $this->file_count($assign, $submissions[1]));
        foreach ([2, 3] as $i) {
            $this->assertTrue($this->has_row($submissions[$i]));
            $this->assertEquals(1, $this->file_count($assign, $submissions[$i]));
        }
    }

    public function test_delete_submissions(): void {
        $this->resetAfterTest();
        [$assign, $students, $submissions] = $this->setup_submissions();

        $deletedata = new assign_plugin_request_data($assign->get_context(), $assign);
        $deletedata->set_userids([$students[1]->id, $students[3]->id]);
        $deletedata->populate_submissions_and_grades();
        provider::delete_submissions($deletedata);

        foreach ([1, 3] as $i) {
            $this->assertFalse($this->has_row($submissions[$i]));
            $this->assertEquals(0, $this->file_count($assign, $submissions[$i]));
        }
        $this->assertTrue($this->has_row($submissions[2]));
        $this->assertEquals(1, $this->file_count($assign, $submissions[2]));
    }
}
