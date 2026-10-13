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

    /** @var bool True while an explicit sync should drop suspended parent enrolments. */
    private static $forcing = false;

    /** @var int Nesting of unenrol calls, so a parent-link re-add can be removed once. */
    private static $depth = 0;

    /** @var array<int,int> Suspended meta enrolments waiting to be removed, keyed by user_enrolments id. */
    private static $pending = [];

    /** @var bool True once the end-of-request removal is registered. */
    private static $flushhooked = false;

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
     * Whether this course is set to sync on a schedule.
     *
     * @param int $courseid
     * @return bool
     */
    public static function is_enabled(int $courseid): bool {
        $schedule = self::get_schedule($courseid);
        return !empty($schedule->enabled) && (int)$schedule->intervalmin > 0;
    }

    /**
     * Copy active enrolments from parent courses, and remove suspended ones.
     *
     * A user who is suspended in a linked parent course is unenrolled from this
     * course instead of being kept with a suspended enrolment.
     *
     * @param int $courseid Child course id
     */
    public static function sync_course(int $courseid): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/enrol/meta/locallib.php');
        self::$forcing = true;
        try {
            enrol_meta_sync($courseid);
            $removed = self::remove_suspended($courseid);
        } finally {
            self::$forcing = false;
        }
        mtrace('Removed ' . $removed . ' suspended parent enrolments from course ' . $courseid);

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

    /**
     * Drop a suspended parent-link enrolment when sync is on, or during an explicit sync.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $enrolmethod
     */
    public static function remove_if_suspended_link(int $courseid, int $userid, string $enrolmethod): void {
        global $DB;

        // An explicit sync removes these rows after the meta sync finishes, including group membership.
        if ($enrolmethod !== 'meta' || self::$forcing || self::$depth > 0) {
            return;
        }
        if (!self::is_enabled($courseid)) {
            return;
        }

        $enrolment = $DB->get_record_sql(
            "SELECT ue.id, ue.enrolid, ue.status
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :courseid
                AND e.enrol = :enrol
                AND ue.userid = :userid
                AND ue.status = :suspended",
            [
                'courseid' => $courseid,
                'enrol' => 'meta',
                'userid' => $userid,
                'suspended' => ENROL_USER_SUSPENDED,
            ]
        );
        if (!$enrolment) {
            return;
        }

        self::$pending[(int)$enrolment->id] = $userid;
        if (!self::$flushhooked) {
            self::$flushhooked = true;
            \core_shutdown_manager::register_function([self::class, 'flush_pending']);
        }
    }

    /**
     * Remove parent-link enrolments that were suspended by a later meta sync.
     */
    public static function flush_pending(): void {
        global $DB;

        $pending = self::$pending;
        self::$pending = [];
        foreach ($pending as $ueid => $userid) {
            $enrolment = $DB->get_record('user_enrolments', ['id' => $ueid, 'userid' => $userid]);
            if (!$enrolment || (int)$enrolment->status !== ENROL_USER_SUSPENDED) {
                continue;
            }
            self::unenrol_meta((int)$enrolment->enrolid, (int)$userid);
        }
    }

    /**
     * Unenrol every user whose parent-link enrolment in this course is suspended.
     *
     * @param int $courseid
     * @return int Number of enrolments removed
     */
    public static function remove_suspended(int $courseid): int {
        global $DB;

        $rows = $DB->get_records_sql(
            "SELECT ue.id, ue.userid, ue.enrolid
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :courseid
                AND e.enrol = :enrol
                AND ue.status = :suspended",
            [
                'courseid' => $courseid,
                'enrol' => 'meta',
                'suspended' => ENROL_USER_SUSPENDED,
            ]
        );

        $removed = 0;
        foreach ($rows as $row) {
            if (self::unenrol_meta((int)$row->enrolid, (int)$row->userid)) {
                $removed++;
            }
        }
        return $removed;
    }

    /**
     * Remove one meta enrolment.
     *
     * @param int $enrolid
     * @param int $userid
     * @return bool
     */
    protected static function unenrol_meta(int $enrolid, int $userid): bool {
        global $DB;

        if (self::$depth > 1) {
            return false;
        }

        $instance = $DB->get_record('enrol', ['id' => $enrolid, 'enrol' => 'meta']);
        $plugin = $instance ? enrol_get_plugin('meta') : null;
        if (!$instance || !$plugin) {
            return false;
        }

        self::$depth++;
        try {
            $plugin->unenrol_user($instance, $userid);
        } finally {
            self::$depth--;
        }

        return !$DB->record_exists('user_enrolments', ['enrolid' => $enrolid, 'userid' => $userid]);
    }
}
