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

/**
 * Decides whether a stored recording may be served, and how.
 *
 * The access rules live here rather than inline in the pluginfile callback so
 * that they can be unit tested: send_stored_file() ends the request.
 *
 * @package    assignsubmission_recording
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_access {
    /** @var string The only file area this plugin serves. */
    public const FILEAREA = 'submissions_recording';

    /**
     * Resolve a pluginfile request to a stored file the current user may see.
     *
     * The caller must already have called require_login() for the course module.
     *
     * @param \context $context the context from the pluginfile URL
     * @param \stdClass $course the course record
     * @param \stdClass|\cm_info $cm the course module
     * @param string $filearea the requested file area
     * @param array $args remaining URL path components (itemid first)
     * @param bool $forcedownload whether the request itself asked for a download
     * @return array|null ['file' => \stored_file, 'forcedownload' => bool], or null to refuse
     */
    public static function get_servable_file(
        \context $context,
        $course,
        $cm,
        string $filearea,
        array $args,
        bool $forcedownload = false
    ): ?array {
        global $CFG, $DB;

        if ($context->contextlevel != CONTEXT_MODULE || $filearea !== self::FILEAREA) {
            return null;
        }

        $itemid = (int) array_shift($args);
        $record = $DB->get_record('assign_submission', ['id' => $itemid], 'userid, assignment, groupid');
        if (!$record) {
            return null;
        }

        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $assign = new \assign($context, $cm, $course);

        // The submission must belong to the assignment the URL's context points at.
        if ($assign->get_instance()->id != $record->assignment) {
            return null;
        }

        if ($assign->get_instance()->teamsubmission) {
            if (!$assign->can_view_group_submission($record->groupid)) {
                return null;
            }
        } else if (!$assign->can_view_submission($record->userid)) {
            return null;
        }

        $relativepath = implode('/', $args);
        $fullpath = "/{$context->id}/assignsubmission_recording/" . self::FILEAREA . "/{$itemid}/{$relativepath}";

        $file = get_file_storage()->get_file_by_hash(sha1($fullpath));
        if (!$file || $file->is_directory()) {
            return null;
        }

        // Recordings play inline as <audio>/<video>, but only when the stored file is
        // genuinely audio/video. Anything else (e.g. a non-media file that reached the
        // draft area by some route other than upload.php) is forced to download instead
        // of being rendered inline, matching core assignsubmission_file's behaviour.
        $ismedia = (bool) preg_match('#^(audio|video)/#', $file->get_mimetype());

        return [
            'file' => $file,
            'forcedownload' => $forcedownload || !$ismedia,
        ];
    }
}
