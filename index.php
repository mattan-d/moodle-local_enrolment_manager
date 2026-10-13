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
 * Course page listing users whose enrolment status is suspended.
 *
 * @package    local_enrolment_manager
 * @copyright  2026 CentricApp LTD (Dev Team) <dev@centricapp.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use core_user\fields;
use local_enrolment_manager\manager;
use local_enrolment_manager\parentsync;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$ueid = optional_param('ueid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);

$search = trim(core_text::substr($search, 0, 255));
if ($page < 0) {
    $page = 0;
}

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
$context = context_course::instance($course->id);

require_login($course);
require_capability('local/enrolment_manager:view', $context);

if ((int)$course->id === (int)SITEID) {
    throw new moodle_exception('invalidcourseid');
}

$listurl = new moodle_url('/local/enrolment_manager/index.php', ['id' => $course->id]);
if ($search !== '') {
    $listurl->param('search', $search);
}
if ($page > 0) {
    $listurl->param('page', $page);
}

$PAGE->set_url($listurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'local_enrolment_manager'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('pluginname', 'local_enrolment_manager'), $listurl);

if ($action === 'saveschedule') {
    require_sesskey();
    $interval = optional_param('interval', 0, PARAM_INT);
    if (!in_array($interval, parentsync::INTERVALS, true)) {
        redirect($listurl, get_string('syncinvalidinterval', 'local_enrolment_manager'), null,
            \core\output\notification::NOTIFY_ERROR);
    }
    parentsync::save_schedule($course->id, $interval);
    redirect($listurl, get_string('syncsaved', 'local_enrolment_manager'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'syncnow') {
    $parents = parentsync::get_parents($course->id);
    if (!$parents) {
        redirect($listurl, get_string('syncnoparents', 'local_enrolment_manager'), null,
            \core\output\notification::NOTIFY_ERROR);
    }
    if ($confirm) {
        require_sesskey();
        parentsync::sync_course($course->id);
        redirect($listurl, get_string('syncdone', 'local_enrolment_manager'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    $confirmurl = new moodle_url($listurl, [
        'action' => 'syncnow',
        'confirm' => 1,
        'sesskey' => sesskey(),
    ]);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('syncheading', 'local_enrolment_manager'));
    echo $OUTPUT->confirm(
        get_string('confirmsyncnow', 'local_enrolment_manager'),
        $confirmurl,
        $listurl,
        ['continuestr' => get_string('syncnow', 'local_enrolment_manager')]
    );
    echo $OUTPUT->footer();
    exit;
}

if (($action === 'activate' || $action === 'unenrol') && $ueid) {
    $enrolment = manager::get_enrolment($course->id, $ueid);
    if (!$enrolment) {
        throw new moodle_exception('invaliduserenrolment', 'local_enrolment_manager', $listurl);
    }
    $enroluser = core_user::get_user($enrolment->userid, '*', MUST_EXIST);
    $instance = $DB->get_record('enrol', ['id' => $enrolment->enrolid], '*', MUST_EXIST);
    $name = fullname($enroluser, false, ['escape' => false]);
    $allowed = $action === 'activate'
        ? manager::can_activate($instance, $context)
        : manager::can_unenrol($instance, $enrolment, $context);
    $failstring = $action === 'activate' ? 'activatefailed' : 'unenrolfailed';

    if ((int)$enrolment->status !== ENROL_USER_SUSPENDED || !$allowed) {
        redirect($listurl, get_string($failstring, 'local_enrolment_manager'), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    if ($confirm) {
        require_sesskey();
        $ok = $action === 'activate'
            ? manager::activate($course, $enrolment)
            : manager::unenrol($course, $enrolment);
        if (!$ok) {
            redirect($listurl, get_string($failstring, 'local_enrolment_manager'), null,
                \core\output\notification::NOTIFY_ERROR);
        }
        $donestring = $action === 'activate' ? 'activated' : 'unenrolled';
        redirect($listurl, get_string($donestring, 'local_enrolment_manager', $name), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    $confirmurl = new moodle_url($listurl, [
        'action' => $action,
        'ueid' => $ueid,
        'confirm' => 1,
        'sesskey' => sesskey(),
    ]);
    $confirmstring = $action === 'activate' ? 'confirmactivate' : 'confirmunenrol';
    $continuestr = $action === 'unenrol' ? get_string('unenrol', 'local_enrolment_manager') : get_string('continue');

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('pageheading', 'local_enrolment_manager'));
    echo $OUTPUT->confirm(
        get_string($confirmstring, 'local_enrolment_manager', $name),
        $confirmurl,
        $listurl,
        ['continuestr' => $continuestr]
    );
    echo $OUTPUT->footer();
    exit;
}

$result = manager::get_suspended_enrolments($course->id, $context, $search, $page);
$total = $result['total'];
$records = $result['records'];

if ($total > 0 && $page > 0 && !$records) {
    $page = 0;
    $listurl->remove_params('page');
    $PAGE->set_url($listurl);
    $result = manager::get_suspended_enrolments($course->id, $context, $search, $page);
    $total = $result['total'];
    $records = $result['records'];
}

$identityfields = fields::for_identity($context)->get_required_fields();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_enrolment_manager'));
local_enrolment_manager_sync_section($course->id, $listurl);
echo $OUTPUT->heading(get_string('pageheading', 'local_enrolment_manager'), 3);
echo html_writer::div(get_string('pageintro', 'local_enrolment_manager'), 'mb-3');

$searchurl = new moodle_url('/local/enrolment_manager/index.php');
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => $searchurl->out(false),
    'class' => 'mb-3',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $course->id]);
echo html_writer::start_div('d-flex align-items-center');
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'search',
    'value' => $search,
    'class' => 'form-control mr-2',
    'placeholder' => get_string('searchplaceholder', 'local_enrolment_manager'),
    'aria-label' => get_string('search'),
]);
echo html_writer::tag('button', get_string('search'), [
    'type' => 'submit',
    'class' => 'btn btn-secondary',
]);
echo html_writer::end_div();
echo html_writer::end_tag('form');

if ($search !== '' && $total === 0) {
    echo $OUTPUT->notification(get_string('nosearchresults', 'local_enrolment_manager'), 'info');
} else if ($total === 0) {
    echo $OUTPUT->notification(get_string('nousers', 'local_enrolment_manager'), 'info');
} else {
    echo html_writer::div(get_string('suspendedcount', 'local_enrolment_manager', $total), 'mb-2');

    $table = new html_table();
    $table->attributes['class'] = 'generaltable table table-striped';
    $table->head = [
        get_string('col_user', 'local_enrolment_manager'),
        get_string('col_roles', 'local_enrolment_manager'),
        get_string('col_method', 'local_enrolment_manager'),
        get_string('col_period', 'local_enrolment_manager'),
        get_string('col_updated', 'local_enrolment_manager'),
        get_string('col_access', 'local_enrolment_manager'),
        get_string('col_actions', 'local_enrolment_manager'),
    ];
    $table->data = [];

    foreach ($records as $record) {
        $table->data[] = [
            local_enrolment_manager_user_cell($record, $course->id, $identityfields),
            $record->rolenames !== '' ? $record->rolenames : get_string('noroles', 'local_enrolment_manager'),
            local_enrolment_manager_method_cell($record),
            local_enrolment_manager_period_cell($record),
            $record->timemodified ? s(userdate($record->timemodified)) : '—',
            local_enrolment_manager_access_cell($record),
            local_enrolment_manager_action_cell($record, $listurl),
        ];
    }

    echo html_writer::div(html_writer::table($table), 'table-responsive');

    $pagerurl = new moodle_url('/local/enrolment_manager/index.php', ['id' => $course->id]);
    if ($search !== '') {
        $pagerurl->param('search', $search);
    }
    echo $OUTPUT->paging_bar($total, $page, manager::PERPAGE, $pagerurl);
}

echo html_writer::div(
    html_writer::link(new moodle_url('/course/view.php', ['id' => $course->id]),
        get_string('backtocourse', 'local_enrolment_manager')),
    'mt-3'
);
echo $OUTPUT->footer();

/**
 * Parent-course sync schedule and an immediate sync action.
 *
 * @param int $courseid
 * @param moodle_url $listurl
 */
function local_enrolment_manager_sync_section(int $courseid, moodle_url $listurl): void {
    global $OUTPUT;

    $parents = parentsync::get_parents($courseid);
    $schedule = parentsync::get_schedule($courseid);

    echo $OUTPUT->heading(get_string('syncheading', 'local_enrolment_manager'), 3);
    echo html_writer::div(get_string('syncintro', 'local_enrolment_manager'), 'mb-3');

    if (!$parents) {
        echo $OUTPUT->notification(get_string('syncnoparents', 'local_enrolment_manager'), 'info');
        return;
    }

    echo html_writer::tag('h4', get_string('syncparents', 'local_enrolment_manager'), ['class' => 'h5']);
    $items = [];
    foreach ($parents as $parent) {
        $name = html_writer::link(
            new moodle_url('/course/view.php', ['id' => $parent->parentid]),
            format_string($parent->fullname, true, ['escape' => false, 'context' => context_course::instance($courseid)])
        );
        if ((int)$parent->status !== ENROL_INSTANCE_ENABLED) {
            $name .= ' ' . html_writer::span(get_string('parentdisabled', 'local_enrolment_manager'), 'badge badge-secondary');
        }
        $items[] = $name;
    }
    echo html_writer::alist($items, ['class' => 'mb-3']);

    if (!empty($schedule->lastrun)) {
        echo html_writer::div(
            get_string('synclastdone', 'local_enrolment_manager', userdate($schedule->lastrun)),
            'mb-3 text-muted'
        );
    } else {
        echo html_writer::div(get_string('syncnever', 'local_enrolment_manager'), 'mb-3 text-muted');
    }

    $options = [];
    foreach (parentsync::INTERVALS as $minutes) {
        $options[$minutes] = get_string('syncinterval_' . $minutes, 'local_enrolment_manager');
    }

    $formurl = new moodle_url('/local/enrolment_manager/index.php');
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $formurl->out(false),
        'class' => 'mb-4',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'saveschedule']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::tag('label', get_string('syncinterval', 'local_enrolment_manager'), [
        'for' => 'sync-interval',
        'class' => 'mr-2',
    ]);
    echo html_writer::select(
        $options,
        'interval',
        (int)$schedule->intervalmin,
        false,
        ['id' => 'sync-interval', 'class' => 'custom-select w-auto d-inline-block mr-2']
    );
    echo html_writer::tag('button', get_string('syncsave', 'local_enrolment_manager'), [
        'type' => 'submit',
        'class' => 'btn btn-primary mr-2',
    ]);
    echo html_writer::link(
        new moodle_url($listurl, ['action' => 'syncnow']),
        get_string('syncnow', 'local_enrolment_manager'),
        ['class' => 'btn btn-secondary']
    );
    echo html_writer::end_tag('form');
}

/**
 * User name, picture, identity fields and account-suspended badge.
 *
 * @param \stdClass $record
 * @param int $courseid
 * @param string[] $identityfields
 * @return string
 */
function local_enrolment_manager_user_cell(\stdClass $record, int $courseid, array $identityfields): string {
    global $OUTPUT;

    $profileurl = new moodle_url('/user/view.php', ['id' => $record->id, 'course' => $courseid]);
    $name = fullname($record, false, ['escape' => false]);

    $html = html_writer::start_div('d-flex align-items-center');
    $html .= html_writer::div(
        $OUTPUT->user_picture($record, ['size' => 35, 'courseid' => $courseid, 'link' => true]),
        'mr-2'
    );
    $html .= html_writer::start_div();
    $html .= html_writer::link($profileurl, $name);
    $html .= ' ' . html_writer::span(get_string('statussuspended', 'local_enrolment_manager'), 'badge badge-warning');
    if (!empty($record->accountsuspended)) {
        $html .= ' ' . html_writer::span(get_string('accountsuspended', 'local_enrolment_manager'), 'badge badge-danger');
    }
    foreach ($identityfields as $field) {
        $value = $record->{$field} ?? '';
        if ($value === '' || $value === null) {
            continue;
        }
        $html .= html_writer::div(
            fields::get_display_name($field) . ': ' . s($value),
            'small text-muted'
        );
    }
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    return $html;
}

/**
 * Enrolment method label.
 *
 * @param \stdClass $record
 * @return string
 */
function local_enrolment_manager_method_cell(\stdClass $record): string {
    $html = $record->methodname;
    if ((int)$record->enrolstatus !== ENROL_INSTANCE_ENABLED) {
        $html .= html_writer::div(get_string('instancdisabled', 'local_enrolment_manager'), 'small text-muted');
    }
    return $html;
}

/**
 * Enrolment start and end.
 *
 * @param \stdClass $record
 * @return string
 */
function local_enrolment_manager_period_cell(\stdClass $record): string {
    $start = $record->timestart
        ? userdate($record->timestart)
        : get_string('periodnostart', 'local_enrolment_manager');
    $end = $record->timeend
        ? userdate($record->timeend)
        : get_string('periodnoend', 'local_enrolment_manager');
    return s($start) . html_writer::empty_tag('br') . s($end);
}

/**
 * Whether the user can still enter the course through another enrolment.
 *
 * @param \stdClass $record
 * @return string
 */
function local_enrolment_manager_access_cell(\stdClass $record): string {
    if ((int)$record->activeenrolments > 0) {
        return html_writer::span(get_string('accessviaother', 'local_enrolment_manager'), 'badge badge-info');
    }
    return html_writer::span(get_string('accessblocked', 'local_enrolment_manager'), 'badge badge-secondary');
}

/**
 * Reactivate and remove links for one suspended enrolment.
 *
 * @param \stdClass $record
 * @param moodle_url $listurl
 * @return string
 */
function local_enrolment_manager_action_cell(\stdClass $record, moodle_url $listurl): string {
    $actions = [];
    if (!empty($record->canactivate)) {
        $actions[] = html_writer::link(
            new moodle_url($listurl, ['action' => 'activate', 'ueid' => $record->ueid]),
            get_string('activate', 'local_enrolment_manager'),
            ['class' => 'btn btn-sm btn-secondary mr-1']
        );
    }
    if (!empty($record->canunenrol)) {
        $actions[] = html_writer::link(
            new moodle_url($listurl, ['action' => 'unenrol', 'ueid' => $record->ueid]),
            get_string('unenrol', 'local_enrolment_manager'),
            ['class' => 'btn btn-sm btn-outline-danger']
        );
    }
    if (!$actions) {
        return html_writer::span(get_string('cannotchange', 'local_enrolment_manager'), 'text-muted small');
    }
    return implode('', $actions);
}
