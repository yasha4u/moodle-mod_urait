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
 * Submits a request to administrators to add a tool configuration for the requested site.
 *
 * @package mod_urait
 * @copyright 2024 LMS-Service {@link https://lms-service.ru/}
 * @author Daniil Romanov
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/lti/lib.php');
require_once($CFG->dirroot.'/mod/lti/locallib.php');

$instanceid = required_param('instanceid', PARAM_INT);

$urait = $DB->get_record('urait', ['id' => $instanceid]);
$course = $DB->get_record('course', array('id' => $urait->course));
$cm = get_coursemodule_from_instance('urait', $urait->id, $urait->course, false, MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course);

require_sesskey();

require_capability('mod/lti:requesttooladd', context_course::instance($urait->course));

$baseurl = lti_get_domain_from_url($urait->toolurl);

$url = new moodle_url('/mod/urait/request_tool.php', array('instanceid' => $instanceid));
$PAGE->set_url($url);

$pagetitle = strip_tags($course->shortname);
$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);

$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($urait->name, true, array('context' => $context)));

// Add a tool type if one does not exist already.
if (!lti_get_tool_by_url_match($urait->toolurl, $urait->course, LTI_TOOL_STATE_ANY)) {
    // There are no tools (active, pending, or rejected) for the launch URL. Create a new pending tool.
    $tooltype = new stdClass();
    $toolconfig = new stdClass();

    $toolconfig->lti_toolurl = lti_get_domain_from_url($urait->toolurl);
    $toolconfig->lti_typename = $toolconfig->lti_toolurl;

    lti_add_type($tooltype, $toolconfig);

    echo get_string('lti_tool_request_added', 'lti');
} else {
    echo get_string('lti_tool_request_existing', 'lti');
}

echo $OUTPUT->footer();
