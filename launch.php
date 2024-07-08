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
 * This file contains all necessary code to view a lti activity instance
 *
 * @package mod_urait
 * @copyright 2024 LMS-Service {@link https://lms-service.ru/}
 * @author Daniil Romanov
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/lti/lib.php');
require_once($CFG->dirroot.'/mod/lti/locallib.php');
require_once($CFG->dirroot.'/mod/urait/lib.php');
require_once($CFG->dirroot.'/mod/urait/locallib.php');

$cmid = required_param('id', PARAM_INT); // Course Module ID.
$triggerview = optional_param('triggerview', 1, PARAM_BOOL);
$action = optional_param('action', '', PARAM_TEXT);
$foruserid = optional_param('user', 0, PARAM_INT);

$cm = get_coursemodule_from_id('urait', $cmid, 0, false, MUST_EXIST);
$urait = $DB->get_record('urait', array('id' => $cm->instance), '*', MUST_EXIST);

$typeid = $urait->typeid;

if (empty($typeid) && ($tool = lti_get_tool_by_url_match($urait->toolurl))) {
    $typeid = $tool->id;
}

if ($typeid) {
    $config = lti_get_type_config($typeid);
    $missingtooltype = empty($config);
    if (!$missingtooltype) {
        $config = urait_get_type_type_config($typeid);
        if ($config->lti_ltiversion === LTI_VERSION_1P3) {
            if (!isset($SESSION->lti_initiatelogin_status)) {
                $msgtype = 'basic-lti-launch-request';
                if ($action === 'gradeReport') {
                    $msgtype = 'LtiSubmissionReviewRequest';
                }
                echo urait_initiate_login($cm->course, $cmid, $urait, $config, $msgtype, '', '', $foruserid);
                exit;
            } else {
                unset($SESSION->lti_initiatelogin_status);
            }
        }
    }
}

$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/lti:view', $context);

if (!empty($missingtooltype)) {
    $PAGE->set_url(new moodle_url('/mod/urait/launch.php'));
    $PAGE->set_context($context);
    $PAGE->set_secondary_active_tab('modulepage');
    $PAGE->set_pagelayout('incourse');
    echo $OUTPUT->header();
    throw new moodle_exception('tooltypenotfounderror', 'mod_lti');
}

// Completion and trigger events.
if ($triggerview) {
    urait_view($urait, $course, $cm, $context);
}

urait_launch_tool($urait, $foruserid);