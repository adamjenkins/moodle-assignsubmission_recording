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
 * Restore subplugin for assignsubmission_recording.
 *
 * @package    assignsubmission_recording
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Provides the information needed to restore recording submissions.
 *
 * @package    assignsubmission_recording
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_assignsubmission_recording_subplugin extends restore_subplugin {
    /**
     * Returns the paths handled by this subplugin at submission level.
     *
     * @return restore_path_element[]
     */
    protected function define_submission_subplugin_structure() {
        $paths = [];
        $elename = $this->get_namefor('submission');
        $elepath = $this->get_pathfor('/submission_recording');
        $paths[] = new restore_path_element($elename, $elepath);
        return $paths;
    }

    /**
     * Processes one assignsubmission_recording element from the backup XML.
     *
     * @param array|object $data
     */
    public function process_assignsubmission_recording_submission($data) {
        global $DB;

        $data = (object) $data;
        $data->assignment = $this->get_new_parentid('assign');
        $oldsubmissionid  = $data->submission;
        $data->submission = $this->get_mappingid('submission', $data->submission);

        $DB->insert_record('assignsubmission_recording', $data);

        $this->add_related_files(
            'assignsubmission_recording',
            'submissions_recording',
            'submission',
            null,
            $oldsubmissionid
        );
    }
}
