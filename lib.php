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
 * Library callbacks for local_enrolment_manager.
 *
 * @package    local_enrolment_manager
 * @copyright  2026 CentricApp LTD (Dev Team) <dev@centricapp.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add the enrolment manager link to the course administration menu.
 *
 * @param settings_navigation $settingsnav
 * @param context $context
 */
function local_enrolment_manager_extend_settings_navigation(settings_navigation $settingsnav, context $context): void {
    global $PAGE;

    if (!$context instanceof context_course) {
        return;
    }

    if ((int)$context->instanceid === (int)SITEID) {
        return;
    }

    if (!has_capability('local/enrolment_manager:view', $context)) {
        return;
    }

    $coursenode = $settingsnav->find('courseadmin', navigation_node::TYPE_COURSE);
    if (!$coursenode) {
        return;
    }

    $url = new moodle_url('/local/enrolment_manager/index.php', ['id' => $context->instanceid]);
    $node = $coursenode->add(
        get_string('pluginname', 'local_enrolment_manager'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_enrolment_manager',
        new pix_icon('i/users', '')
    );

    if ($PAGE->has_set_url() && $PAGE->url->compare($url, URL_MATCH_BASE)) {
        $node->make_active();
    }
}
