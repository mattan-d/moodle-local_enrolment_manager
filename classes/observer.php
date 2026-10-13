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
 * Removes suspended parent-link enrolments while a course sync is active.
 *
 * @package    local_enrolment_manager
 * @copyright  2026 CentricApp LTD (Dev Team) <dev@centricapp.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_enrolment_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Keeps a scheduled course from retaining suspended enrolments copied from a parent course.
 */
class observer {

    /**
     * A parent link just enrolled someone. Drop the row when it is suspended.
     *
     * @param \core\event\user_enrolment_created $event
     */
    public static function user_enrolment_created(\core\event\user_enrolment_created $event): void {
        self::remove_suspended_link($event);
    }

    /**
     * A parent link just changed. Drop the row when the new status is suspended.
     *
     * @param \core\event\user_enrolment_updated $event
     */
    public static function user_enrolment_updated(\core\event\user_enrolment_updated $event): void {
        self::remove_suspended_link($event);
    }

    /**
     * @param \core\event\user_enrolment_created|\core\event\user_enrolment_updated $event
     */
    protected static function remove_suspended_link($event): void {
        $enrol = $event->other['enrol'] ?? '';
        parentsync::remove_if_suspended_link((int)$event->courseid, (int)$event->relateduserid, (string)$enrol);
    }
}
