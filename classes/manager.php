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
 * Suspended course enrolment queries and status changes.
 *
 * @package    local_enrolment_manager
 * @copyright  2026 CentricApp LTD (Dev Team) <dev@centricapp.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_enrolment_manager;

use context;
use core_user\fields;

defined('MOODLE_INTERNAL') || die();

/**
 * Loads suspended user enrolments for one course.
 */
class manager {

    /** @var int Rows per page. */
    public const PERPAGE = 50;

    /**
     * Count and fetch suspended enrolment rows for a course.
     *
     * A row is one user_enrolments record whose status is suspended.
     * Users with another still-active enrolment are included, and the
     * activeenrolments property reports that.
     *
     * @param int $courseid
     * @param context $context
     * @param string $search
     * @param int $page Zero-based page index
     * @return array{total:int, records:\stdClass[]}
     */
    public static function get_suspended_enrolments(int $courseid, context $context, string $search, int $page): array {
        global $DB;

        [$where, $params, $joins] = self::suspended_filter($courseid, $context, $search);

        $countsql = "SELECT COUNT(1)
                       FROM {user_enrolments} ue
                       JOIN {enrol} e ON e.id = ue.enrolid
                       JOIN {user} u ON u.id = ue.userid
                            {$joins}
                      WHERE {$where}";
        $total = $DB->count_records_sql($countsql, $params);

        $userfields = fields::for_identity($context)->with_name()->with_userpic();
        ['selects' => $fieldselect, 'joins' => $fieldjoins, 'params' => $fieldparams] =
            (array)$userfields->get_sql('u', true, '', '', false);
        $params += $fieldparams;

        $now = round(time(), -2);
        $params['activestatus'] = ENROL_USER_ACTIVE;
        $params['enabledstatus'] = ENROL_INSTANCE_ENABLED;
        $params['now1'] = $now;
        $params['now2'] = $now;

        $activesql = "SELECT COUNT(1)
                        FROM {user_enrolments} uea
                        JOIN {enrol} ea ON ea.id = uea.enrolid
                       WHERE uea.userid = u.id
                         AND ea.courseid = e.courseid
                         AND uea.status = :activestatus
                         AND ea.status = :enabledstatus
                         AND uea.timestart < :now1
                         AND (uea.timeend = 0 OR uea.timeend > :now2)";

        [$sort, $sortparams] = users_order_by_sql('u');
        $params += $sortparams;

        $sql = "SELECT ue.id AS ueid,
                       ue.status,
                       ue.timestart,
                       ue.timeend,
                       ue.timemodified,
                       e.id AS instanceid,
                       e.enrol,
                       e.name AS enrolname,
                       e.status AS enrolstatus,
                       e.courseid,
                       u.suspended AS accountsuspended,
                       ({$activesql}) AS activeenrolments,
                       {$fieldselect}
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {user} u ON u.id = ue.userid
                       {$fieldjoins}
                 WHERE {$where}
              ORDER BY {$sort}, ue.id";

        $records = $DB->get_records_sql($sql, $params, $page * self::PERPAGE, self::PERPAGE);
        $records = self::annotate($courseid, $context, $records);

        return [
            'total' => $total,
            'records' => $records,
        ];
    }

    /**
     * Load one enrolment and confirm it belongs to the course.
     *
     * @param int $courseid
     * @param int $ueid user_enrolments.id
     * @return \stdClass|null
     */
    public static function get_enrolment(int $courseid, int $ueid): ?\stdClass {
        global $DB;

        $sql = "SELECT ue.*
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.id = :ueid
                   AND e.courseid = :courseid";
        $record = $DB->get_record_sql($sql, ['ueid' => $ueid, 'courseid' => $courseid]);
        return $record ?: null;
    }

    /**
     * Whether the current user may change this enrolment's status.
     *
     * Uses the enrol plugin's own manage rules so synchronised methods
     * that forbid manual edits stay read-only.
     *
     * @param \stdClass $instance enrol instance
     * @param context $context course context
     * @return bool
     */
    public static function can_activate(\stdClass $instance, context $context): bool {
        $plugin = enrol_get_plugin($instance->enrol);
        if (!$plugin || !$plugin->allow_manage($instance)) {
            return false;
        }
        return has_capability('enrol/' . $instance->enrol . ':manage', $context);
    }

    /**
     * Set a suspended enrolment back to active.
     *
     * Caller must already have checked the course, the suspended status,
     * and can_activate().
     *
     * @param \stdClass $course
     * @param \stdClass $userenrolment user_enrolments record
     * @return bool
     */
    public static function activate(\stdClass $course, \stdClass $userenrolment): bool {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/enrol/locallib.php');

        $manager = new \course_enrolment_manager($PAGE, $course);
        $data = (object)[
            'status' => ENROL_USER_ACTIVE,
            'timestart' => $userenrolment->timestart,
            'timeend' => $userenrolment->timeend,
        ];
        return $manager->edit_enrolment($userenrolment, $data);
    }

    /**
     * Shared WHERE clause for suspended enrolments in a course.
     *
     * @param int $courseid
     * @param context $context
     * @param string $search
     * @return array{0:string,1:array,2:string} SQL where, params, extra joins
     */
    protected static function suspended_filter(int $courseid, context $context, string $search): array {
        $extrafields = fields::get_identity_fields($context, false);
        [$searchsql, $params] = users_search_sql($search, 'u', USER_SEARCH_CONTAINS, $extrafields);

        $params['courseid'] = $courseid;
        $params['suspendedstatus'] = ENROL_USER_SUSPENDED;

        $where = "e.courseid = :courseid
              AND ue.status = :suspendedstatus
              AND {$searchsql}";

        return [$where, $params, ''];
    }

    /**
     * Add display names, course roles, and whether reactivation is allowed.
     *
     * @param int $courseid
     * @param context $context
     * @param \stdClass[] $records
     * @return \stdClass[]
     */
    protected static function annotate(int $courseid, context $context, array $records): array {
        global $DB;

        if (!$records) {
            return [];
        }

        $instances = $DB->get_records('enrol', ['courseid' => $courseid]);
        $userids = [];
        foreach ($records as $record) {
            $userids[] = (int)$record->id;
        }
        $roles = self::load_role_names($userids, $context);

        foreach ($records as $record) {
            $instance = $instances[$record->instanceid] ?? null;
            $plugin = $instance ? enrol_get_plugin($instance->enrol) : null;
            if ($plugin && $instance) {
                $record->methodname = $plugin->get_instance_name($instance);
            } else {
                $record->methodname = s($record->enrol);
            }
            $record->rolenames = $roles[(int)$record->id] ?? '';
            $record->canactivate = $instance && self::can_activate($instance, $context);
        }

        return $records;
    }

    /**
     * Localised role names assigned in the course context, keyed by user id.
     *
     * @param int[] $userids
     * @param context $context
     * @return array<int,string>
     */
    protected static function load_role_names(array $userids, context $context): array {
        global $DB;

        $userids = array_values(array_unique(array_filter($userids)));
        if (!$userids) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $inparams['contextid'] = $context->id;
        $sql = "SELECT ra.id, ra.userid, r.id AS roleid, r.name, r.shortname, r.sortorder
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE ra.contextid = :contextid
                   AND ra.userid {$insql}
              ORDER BY r.sortorder ASC";
        $rows = $DB->get_records_sql($sql, $inparams);

        $byuser = [];
        foreach ($rows as $row) {
            $role = (object)[
                'id' => $row->roleid,
                'name' => $row->name,
                'shortname' => $row->shortname,
                'sortorder' => $row->sortorder,
            ];
            $byuser[(int)$row->userid][] = role_get_name($role, $context);
        }

        $result = [];
        foreach ($byuser as $userid => $names) {
            $result[$userid] = implode(', ', $names);
        }
        return $result;
    }
}
