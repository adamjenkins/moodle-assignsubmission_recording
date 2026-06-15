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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/locallib.php');

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;
use core_privacy\local\request\contextlist;
use mod_assign\privacy\assign_plugin_request_data;

/**
 * Privacy provider for the recording submission plugin.
 *
 * @package    assignsubmission_recording
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \mod_assign\privacy\assignsubmission_provider,
    \mod_assign\privacy\assignsubmission_user_provider {
    /**
     * Return meta data about this plugin.
     *
     * @param collection $collection a list of information to add to
     * @return collection the updated collection
     */
    public static function get_metadata(collection $collection): collection {
        $detail = [
            'assignment' => 'privacy:metadata:assignmentid',
            'submission' => 'privacy:metadata:submissionpurpose',
            'recordingtext' => 'privacy:metadata:textpurpose',
        ];
        $collection->add_database_table(
            'assignsubmission_recording',
            $detail,
            'privacy:metadata:tablepurpose'
        );
        $collection->link_subsystem('core_files', 'privacy:metadata:filepurpose');

        return $collection;
    }

    /**
     * Covered by mod_assign's query on assign_submissions.
     *
     * @param int $userid the user ID
     * @param contextlist $contextlist the context list to add to
     * @return void
     */
    public static function get_context_for_userid_within_submission(int $userid, contextlist $contextlist) {
        // Already fetched from mod_assign.
    }

    /**
     * Covered by the mod_assign provider.
     *
     * @param \mod_assign\privacy\useridlist $useridlist the user id list
     * @return void
     */
    public static function get_student_user_ids(\mod_assign\privacy\useridlist $useridlist) {
        // No need.
    }

    /**
     * Not required — no user records are created without an assign_submission row.
     *
     * @param \core_privacy\local\request\userlist $userlist the user list
     * @return void
     */
    public static function get_userids_from_context(\core_privacy\local\request\userlist $userlist) {
        // Not required.
    }

    /**
     * Export all user data for this plugin.
     *
     * @param assign_plugin_request_data $exportdata data needed to export
     * @return void
     */
    public static function export_submission_user_data(assign_plugin_request_data $exportdata) {
        if ($exportdata->get_user() !== null) {
            return;
        }

        $submission = $exportdata->get_pluginobject();
        $context = $exportdata->get_context();

        global $DB;
        $record = $DB->get_record(
            'assignsubmission_recording',
            ['submission' => $submission->id]
        );

        if (!$record || empty($record->recordingtext)) {
            return;
        }

        $currentpath = $exportdata->get_subcontext();
        $currentpath[] = get_string('privacy:path', 'assignsubmission_recording');

        $submissiondata = new \stdClass();
        $submissiondata->text = writer::with_context($context)->rewrite_pluginfile_urls(
            $currentpath,
            'assignsubmission_recording',
            'submissions_recording',
            $submission->id,
            $record->recordingtext
        );

        writer::with_context($context)
            ->export_area_files($currentpath, 'assignsubmission_recording', 'submissions_recording', $submission->id)
            ->export_data($currentpath, $submissiondata);
    }

    /**
     * Delete all user data for the given context.
     *
     * @param assign_plugin_request_data $requestdata the deletion criteria
     * @return void
     */
    public static function delete_submission_for_context(assign_plugin_request_data $requestdata) {
        global $DB;

        $fs = get_file_storage();
        $fs->delete_area_files(
            $requestdata->get_context()->id,
            'assignsubmission_recording',
            'submissions_recording'
        );

        $DB->delete_records(
            'assignsubmission_recording',
            ['assignment' => $requestdata->get_assignid()]
        );
    }

    /**
     * Delete user data for a specific user and context.
     *
     * @param assign_plugin_request_data $deletedata the deletion criteria
     * @return void
     */
    public static function delete_submission_for_userid(assign_plugin_request_data $deletedata) {
        global $DB;

        $submissionid = $deletedata->get_pluginobject()->id;

        $fs = get_file_storage();
        $fs->delete_area_files(
            $deletedata->get_context()->id,
            'assignsubmission_recording',
            'submissions_recording',
            $submissionid
        );

        $DB->delete_records(
            'assignsubmission_recording',
            ['assignment' => $deletedata->get_assignid(), 'submission' => $submissionid]
        );
    }

    /**
     * Delete data for a set of users within a context.
     *
     * @param assign_plugin_request_data $deletedata the deletion criteria
     * @return void
     */
    public static function delete_submissions(assign_plugin_request_data $deletedata) {
        global $DB;

        if (empty($deletedata->get_submissionids())) {
            return;
        }

        $fs = get_file_storage();
        [$sql, $params] = $DB->get_in_or_equal($deletedata->get_submissionids(), SQL_PARAMS_NAMED);
        $fs->delete_area_files_select(
            $deletedata->get_context()->id,
            'assignsubmission_recording',
            'submissions_recording',
            $sql,
            $params
        );

        $params['assignid'] = $deletedata->get_assignid();
        $DB->delete_records_select(
            'assignsubmission_recording',
            "assignment = :assignid AND submission $sql",
            $params
        );
    }
}
