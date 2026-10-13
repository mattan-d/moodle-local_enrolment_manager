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
 * English language strings for local_enrolment_manager.
 *
 * @package    local_enrolment_manager
 * @copyright  2026 CentricApp LTD (Dev Team) <dev@centricapp.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Enrolment manager';
$string['enrolment_manager:view'] = 'View suspended course enrolments';
$string['privacy:metadata'] = 'The enrolment manager plugin does not store personal data. It displays existing course enrolment records.';

$string['pageheading'] = 'Users with a suspended enrolment';
$string['pageintro'] = 'Enrolments in this course whose status is suspended. A suspended enrolment does not give access to the course. An enrolment that has only expired or not started yet is not listed here.';
$string['nousers'] = 'No enrolled users have a suspended enrolment status.';
$string['nosearchresults'] = 'No suspended enrolments match this search.';
$string['searchplaceholder'] = 'Name, email or username';
$string['suspendedcount'] = 'Suspended enrolments: {$a}';
$string['col_user'] = 'User';
$string['col_roles'] = 'Roles';
$string['col_method'] = 'Enrolment method';
$string['col_period'] = 'Enrolment period';
$string['col_updated'] = 'Last updated';
$string['col_access'] = 'Course access';
$string['col_actions'] = 'Actions';
$string['statussuspended'] = 'Suspended';
$string['accountsuspended'] = 'User account is suspended';
$string['accessblocked'] = 'No access';
$string['accessviaother'] = 'Another enrolment is still active';
$string['noroles'] = 'No role';
$string['periodnostart'] = 'No start date';
$string['periodnoend'] = 'No end date';
$string['instancdisabled'] = 'Enrolment method is disabled';
$string['activate'] = 'Reactivate';
$string['cannotactivate'] = 'This enrolment cannot be changed manually';
$string['confirmactivate'] = 'Reactivate the enrolment for {$a}?';
$string['activated'] = 'The enrolment for {$a} was reactivated.';
$string['activatefailed'] = 'The enrolment could not be reactivated.';
$string['unenrol'] = 'Remove enrolment';
$string['confirmunenrol'] = 'Remove {$a} from this course? This deletes the suspended enrolment.';
$string['unenrolled'] = '{$a} was removed from the course.';
$string['unenrolfailed'] = 'The enrolment could not be removed.';
$string['cannotchange'] = 'This enrolment cannot be changed or removed manually';
$string['notsuspended'] = 'This enrolment is not suspended.';
$string['invaliduserenrolment'] = 'This enrolment does not belong to the course.';
$string['backtocourse'] = 'Back to course';

$string['syncheading'] = 'Sync enrolments from a parent course';
$string['syncintro'] = 'The schedule runs through cron. An enrolment change in a linked parent course (a new enrolment, a suspension, or a removal) is applied to this course as well. The site cron has to be running.';
$string['syncparents'] = 'Linked parent courses';
$string['syncnoparents'] = 'This course is not linked to a parent course.';
$string['syncinterval'] = 'Sync schedule';
$string['syncinterval_0'] = 'No schedule';
$string['syncinterval_15'] = 'Every 15 minutes';
$string['syncinterval_60'] = 'Every hour';
$string['syncinterval_360'] = 'Every 6 hours';
$string['syncinterval_1440'] = 'Once a day';
$string['syncsave'] = 'Save schedule';
$string['syncsaved'] = 'The sync schedule was saved.';
$string['syncnow'] = 'Sync now';
$string['confirmsyncnow'] = 'Sync enrolments from the parent courses now?';
$string['syncdone'] = 'Enrolment sync from the parent courses finished.';
$string['synclastdone'] = 'Last sync: {$a}';
$string['syncnever'] = 'Sync has not run yet.';
$string['syncinvalidinterval'] = 'That schedule is not valid.';
$string['task_sync_parent'] = 'Sync enrolments from parent courses';
$string['parentdisabled'] = 'Link method is disabled';
