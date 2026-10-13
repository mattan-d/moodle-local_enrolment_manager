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
 * Hebrew language strings for local_enrolment_manager.
 *
 * @package    local_enrolment_manager
 * @copyright  2026 CentricApp LTD (Dev Team) <dev@centricapp.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'ניהול רישום';
$string['enrolment_manager:view'] = 'צפייה ברישומים מושהים בקורס';
$string['privacy:metadata'] = 'תוסף ניהול הרישום אינו שומר מידע אישי. הוא מציג רשומות רישום קיימות של הקורס.';

$string['pageheading'] = 'משתמשים בסטטוס רישום מושהה';
$string['pageintro'] = 'רישומים בקורס שסטטוס המשתמש בהם הוא מושהה. רישום מושהה אינו מעניק גישה לקורס. רישום שרק פג תוקפו או שטרם התחיל אינו מופיע כאן.';
$string['nousers'] = 'אין בקורס משתמשים רשומים בסטטוס מושהה.';
$string['nosearchresults'] = 'לא נמצאו רישומים מושהים התואמים לחיפוש.';
$string['searchplaceholder'] = 'שם, דוא״ל או שם משתמש';
$string['suspendedcount'] = 'רישומים מושהים: {$a}';
$string['col_user'] = 'משתמש';
$string['col_roles'] = 'תפקידים';
$string['col_method'] = 'שיטת רישום';
$string['col_period'] = 'תקופת רישום';
$string['col_updated'] = 'עודכן לאחרונה';
$string['col_access'] = 'גישה לקורס';
$string['col_actions'] = 'פעולות';
$string['statussuspended'] = 'מושהה';
$string['accountsuspended'] = 'חשבון המשתמש מושהה';
$string['accessblocked'] = 'אין גישה';
$string['accessviaother'] = 'יש רישום פעיל אחר';
$string['noroles'] = 'ללא תפקיד';
$string['periodnostart'] = 'ללא תאריך התחלה';
$string['periodnoend'] = 'ללא תאריך סיום';
$string['instancdisabled'] = 'שיטת הרישום כבויה';
$string['activate'] = 'הפעל מחדש';
$string['cannotactivate'] = 'לא ניתן לשנות את הרישום הזה ידנית';
$string['confirmactivate'] = 'להפעיל מחדש את הרישום של {$a}?';
$string['activated'] = 'הרישום של {$a} הופעל מחדש.';
$string['activatefailed'] = 'לא ניתן להפעיל מחדש את הרישום.';
$string['unenrol'] = 'הסרת רישום';
$string['confirmunenrol'] = 'להסיר את {$a} מהקורס? הרישום המושהה יימחק.';
$string['unenrolled'] = 'הרישום של {$a} הוסר מהקורס.';
$string['unenrolfailed'] = 'לא ניתן להסיר את הרישום.';
$string['cannotchange'] = 'לא ניתן לשנות או להסיר את הרישום הזה ידנית';
$string['notsuspended'] = 'הרישום הזה אינו בסטטוס מושהה.';
$string['invaliduserenrolment'] = 'הרישום הזה אינו שייך לקורס.';
$string['backtocourse'] = 'חזרה לקורס';

$string['syncheading'] = 'סנכרון רישום מקורס אב';
$string['syncintro'] = 'כשהתזמון פעיל, cron מסנכרן את הרישום מקורס האב. משתמש שהרישום שלו מושהה מוסר מהקורס הזה. משתמש פעיל בקורס האב נשאר רשום או נוסף. נדרש ש-cron של האתר ירוץ.';
$string['syncparents'] = 'קורסי אב מקושרים';
$string['syncnoparents'] = 'לקורס הזה אין קישור רישום לקורס אב.';
$string['syncinterval'] = 'תזמון סנכרון';
$string['syncinterval_0'] = 'ללא תזמון';
$string['syncinterval_15'] = 'כל 15 דקות';
$string['syncinterval_60'] = 'כל שעה';
$string['syncinterval_360'] = 'כל 6 שעות';
$string['syncinterval_1440'] = 'פעם ביום';
$string['syncsave'] = 'שמירת תזמון';
$string['syncsaved'] = 'תזמון הסנכרון נשמר.';
$string['syncnow'] = 'סנכרון עכשיו';
$string['confirmsyncnow'] = 'להריץ עכשיו סנכרון? משתמשים בסטטוס מושהה יוסרו מהקורס הזה.';
$string['syncdone'] = 'סנכרון הרישום מקורסי האב הסתיים.';
$string['synclastdone'] = 'סנכרון אחרון: {$a}';
$string['syncnever'] = 'הסנכרון עדיין לא רץ.';
$string['syncinvalidinterval'] = 'תזמון לא תקין.';
$string['task_sync_parent'] = 'סנכרון רישום מקורסי אב';
$string['parentdisabled'] = 'שיטת הקישור כבויה';
