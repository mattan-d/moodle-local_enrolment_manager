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
 * Cron task that syncs enrolments from parent courses.
 *
 * @package    local_enrolment_manager
 * @copyright  2026 CentricApp LTD (Dev Team) <dev@centricapp.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_enrolment_manager\task;

use local_enrolment_manager\parentsync;

defined('MOODLE_INTERNAL') || die();

/**
 * Runs parent-course enrolment sync for courses whose schedule is due.
 */
class sync_parent_enrolments extends \core\task\scheduled_task {

    /**
     * Task name shown in scheduled tasks admin.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_sync_parent', 'local_enrolment_manager');
    }

    /**
     * Sync due courses.
     */
    public function execute() {
        parentsync::run_due();
    }
}
