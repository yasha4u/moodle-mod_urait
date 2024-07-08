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
 * Страница со списком экземпляров плагина ЮРАЙТ в курсе
 *
 * @package     mod_urait
 * @copyright   2024 LMS-Service {@link https://lms-service.ru/}
 * @author      Valeriy Tyulin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once("../../config.php");
require_once($CFG->dirroot.'/mod/urait/lib.php');

$id = required_param('id', PARAM_INT);   // Course id.

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

require_login($course);
$PAGE->set_pagelayout('incourse');

$params = array(
    'context' => context_course::instance($course->id)
);

$PAGE->set_url('/mod/urait/index.php', array('id' => $course->id));
$pagetitle = strip_tags($course->shortname.': '.get_string("modulenamepluralformatted", "urait"));
$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

// Print the main part of the page.
echo $OUTPUT->heading(get_string("modulenamepluralformatted", "urait"));

// Get all the appropriate data.
if (! $basicuraits = get_all_instances_in_course("urait", $course)) {
    notice(get_string('nouraits', 'urait'), "../../course/view.php?id=$course->id");
    die;
}

// Print the list of instances (your module will probably extend this).
$timenow = time();
$strname = get_string("name");
$usesections = course_format_uses_sections($course->format);

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($usesections) {
    $strsectionname = get_string('sectionname', 'format_'.$course->format);
    $table->head  = array ($strsectionname, $strname);
    $table->align = array ("center", "left");
} else {
    $table->head  = array ($strname);
}

foreach ($basicuraits as $basicurait) {
    if (!$basicurait->visible) {
        // Show dimmed if the mod is hidden.
        $link = "<a class=\"dimmed\" href=\"view.php?id=$basicurait->coursemodule\">$basicurait->name</a>";
    } else {
        // Show normal if the mod is visible.
        $link = "<a href=\"view.php?id=$basicurait->coursemodule\">$basicurait->name</a>";
    }

    if ($usesections) {
        $table->data[] = array (get_section_name($course, $basicurait->section), $link);
    } else {
        $table->data[] = array ($link);
    }
}

echo "<br />";

echo html_writer::table($table);

// Finish the page.
echo $OUTPUT->footer();

