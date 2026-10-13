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
 * Scheduled sync of enrolments from linked parent courses.
 *
 * @package    local_enrolment_manager
 * @copyright  2026 CentricApp LTD (Dev Team) <dev@centricapp.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_enrolment_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Stores a per-course cron interval and runs Moodle meta enrolment sync.
 */
class parentsync {

    /** @var int[] Allowed intervals in minutes. Zero disables the schedule. */
    public const INTERVALS = [0, 15, 60, 360, 1440];

    /**
     * Meta-linked parent courses for a child course.
     *
     * @param int $courseid Child course id
     * @return \stdClass[]
     */
    public static function get_parents(int $courseid): array {
        global $DB;

        $sql = "SELECT e.id, e.status, e.customint1 AS parentid, c.fullname, c.shortname
                  FROM {enrol} e
                  JOIN {course} c ON c.id = e.customint1
                 WHERE e.courseid = :courseid
                   AND e.enrol = :enrol
              ORDER BY c.fullname, e.id";

        return $DB->get_records_sql($sql, [
            'courseid' => $courseid,
            'enrol' => 'meta',
        ]);
    }

    /**
     * Schedule row for a course, or a disabled default.
     *
     * @param int $courseid
     * @return \stdClass
     */
    public static function get_schedule(int $courseid): \stdClass {
        global $DB;

        $record = $DB->get_record('local_em_sync', ['courseid' => $courseid]);
        if ($record) {
            return $record;
        }

        return (object)[
            'courseid' => $courseid,
            'enabled' => 0,
            'intervalmin' => 0,
            'lastrun' => 0,
            'timemodified' => 0,
        ];
    }

    /**
     * Save the cron interval for a course.
     *
     * @param int $courseid
     * @param int $intervalmin One of INTERVALS
     */
    public static function save_schedule(int $courseid, int $intervalmin): void {
        global $DB;

        if (!in_array($intervalmin, self::INTERVALS, true)) {
            throw new \invalid_parameter_exception('Invalid sync interval');
        }

        $now = time();
        $record = $DB->get_record('local_em_sync', ['courseid' => $courseid]);
        if ($record) {
            $record->enabled = $intervalmin > 0 ? 1 : 0;
            $record->intervalmin = $intervalmin;
            $record->timemodified = $now;
            $DB->update_record('local_em_sync', $record);
            return;
        }

        $DB->insert_record('local_em_sync', (object)[
            'courseid' => $courseid,
            'enabled' => $intervalmin > 0 ? 1 : 0,
            'intervalmin' => $intervalmin,
            'lastrun' => 0,
            'timemodified' => $now,
        ]);
    }

    /**
     * Copy enrolment changes from parent courses into this course.
     *
     * @param int $courseid Child course id
     */
    public static function sync_course(int $courseid): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/enrol/meta/locallib.php');
        enrol_meta_sync($courseid);

        $now = time();
        $record = $DB->get_record('local_em_sync', ['courseid' => $courseid]);
        if ($record) {
            $record->lastrun = $now;
            $record->timemodified = $now;
            $DB->update_record('local_em_sync', $record);
            return;
        }

        $DB->insert_record('local_em_sync', (object)[
            'courseid' => $courseid,
            'enabled' => 0,
            'intervalmin' => 0,
            'lastrun' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Sync every course whose saved interval has elapsed.
     */
    public static function run_due(): void {
        global $DB;

        $now = time();
        $records = $DB->get_records('local_em_sync', ['enabled' => 1]);
        foreach ($records as $record) {
            $interval = (int)$record->intervalmin;
            if ($interval <= 0) {
                continue;
            }
            $lastrun = (int)$record->lastrun;
            if ($lastrun > 0 && ($lastrun + ($interval * 60)) > $now) {
                continue;
            }
            mtrace('Syncing parent enrolments for course ' . $record->courseid);
            self::sync_course((int)$record->courseid);
        }
    }
}
