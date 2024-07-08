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
 * @package mod_urait
 * @copyright 2024 LMS-Service {@link https://lms-service.ru/}
 * @author Daniil Romanov
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $instance An object from the form in mod.html
 * @return int The id of the newly inserted basiclti record
 **/
function urait_add_instance($urait, $mform) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/lti/locallib.php');
    require_once($CFG->dirroot.'/mod/urait/locallib.php');

    lti_load_tool_if_cartridge($urait);

    $utypeid = $DB->get_field('lti_types', 'id', ['name' => 'Образовательная платформа «Юрайт»']);

    $urait->timecreated = time();
    $urait->timemodified = $urait->timecreated;
    $urait->servicesalt = uniqid('', true);
    $urait->typeid = $utypeid;

    lti_force_type_config_settings($urait, lti_get_type_config_by_instance($urait));

    if (empty($urait->typeid) && isset($urait->urlmatchedtypeid)) {
        $urait->typeid = $urait->urlmatchedtypeid;
    }

    $urait->ltiid = 0;
    $uraitid = $DB->insert_record('urait', $urait);
    $urait->id = $uraitid;

    if (isset($urait->instructorchoiceacceptgrades) && $urait->instructorchoiceacceptgrades == LTI_SETTING_ALWAYS) {
        if (!isset($urait->cmidnumber)) {
            $urait->cmidnumber = '';
        }
        urait_grade_item_update($urait);
    }

    $services = urait_get_services();
    foreach ($services as $service) {
        $service->instance_added($urait);
    }

    $completiontimeexpected = !empty($urait->completionexpected) ? $urait->completionexpected : null;
    \core_completion\api::update_completion_date_event($urait->coursemodule, 'urait', $uraitid, $completiontimeexpected);

    return $uraitid;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will update an existing instance with new data.
 *
 * @param object $instance An object from the form in mod.html
 * @return boolean Success/Fail
 **/
function urait_update_instance($urait, $mform) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/lti/locallib.php');
    require_once($CFG->dirroot.'/mod/urait/locallib.php');

    lti_load_tool_if_cartridge($urait);

    $urait->timemodified = time();
    $urait->id = $urait->instance;

    if (!isset($urait->showtitlelaunch)) {
        $urait->showtitlelaunch = 0;
    }

    if (!isset($urait->showdescriptionlaunch)) {
        $urait->showdescriptionlaunch = 0;
    }

    lti_force_type_config_settings($urait, lti_get_type_config_by_instance($urait));

    if (isset($urait->instructorchoiceacceptgrades) && $urait->instructorchoiceacceptgrades == LTI_SETTING_ALWAYS) {
        urait_grade_item_update($urait);
    } else {
        // Instance is no longer accepting grades from Provider, set grade to "No grade" value 0.
        $urait->grade = 0;
        $urait->instructorchoiceacceptgrades = 0;

        urait_grade_item_delete($urait);
    }

    if ($urait->typeid == 0 && isset($urait->urlmatchedtypeid)) {
        $urait->typeid = $urait->urlmatchedtypeid;
    }

    $uraitresult = $DB->update_record('urait', $urait);

    $services = urait_get_services();
    foreach ($services as $service) {
        $service->instance_updated($urait);
    }

    $completiontimeexpected = !empty($urait->completionexpected) ? $urait->completionexpected : null;
    \core_completion\api::update_completion_date_event($urait->coursemodule, 'urait', $urait->id, $completiontimeexpected);

    return $uraitresult;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 **/
function urait_delete_instance($id) {
    global $DB, $CFG;

    require_once($CFG->dirroot.'/mod/urait/locallib.php');
    require_once($CFG->libdir.'/gradelib.php');

    if (!$basicurait = $DB->get_record('urait', ["id" => $id])) {
        return false;
    }

    grade_update('mod/urait', $basicurait->course,
        'mod', 'urait', $basicurait->id, 0,
        null, array('deleted' => 1));

    $cm = get_coursemodule_from_instance('urait', $id);
    \core_completion\api::update_completion_date_event($cm->id, 'urait', $id, null);

    $typeid = $DB->get_field('lti_types', 'id', ['name' => 'Образовательная платформа «Юрайт»']);
    \mod_lti\external\delete_course_tool_type::execute($typeid);

    // We must delete the module record after we delete the grade item.
    $DB->delete_records("urait", ["id" => $basicurait->id]);

    // We must delete the module record after we delete the grade item.
    if ($DB->delete_records("urait", ["id" => $basicurait->id]) ) {
        $services = urait_get_services();

        foreach ($services as $service) {
            $service->instance_deleted($id);
        }
        return true;
    }

    return true;
}

/**
 * List of features supported in URL module
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know or string for the module purpose.
 */
function urait_supports($feature) {
    switch ($feature) {
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_INTRO:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_GRADE_OUTCOMES:
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;

        default:
            return null;
    }
}

/**
 * Must return an array of grades for a given instance of this module,
 * indexed by user.  It also returns a maximum allowed grade.
 *
 * Example:
 *    $return->grades = array of grades;
 *    $return->maxgrade = maximum allowed grade;
 *
 *    return $return;
 *
 * @param int $basicuraitid ID of an instance of this module
 * @return mixed Null or object with an array of grades and with the maximum grade
 **/
function urait_grades($basicuraitid) {
    return null;
}

/**
 * Create grade item for given basicurait
 *
 * @param $basicurait
 * @param null $grades
 * @return int
 */
function urait_grade_item_update($basicurait, $grades = null) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');
    require_once($CFG->dirroot.'/mod/lti/servicelib.php');

    if (!lti_accepts_grades($basicurait)) {
        return 0;
    }

    $params = ['itemname' => $basicurait->name, 'idnumber' => $basicurait->cmidnumber];

    if ($basicurait->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $basicurait->grade;
        $params['grademin']  = 0;

    } else if ($basicurait->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$basicurait->grade;

    } else {
        $params['gradetype'] = GRADE_TYPE_TEXT; // Allow text comments only.
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update(
        'mod/urait',
        $basicurait->course,
        'mod',
        'urait',
        $basicurait->id,
        0,
        $grades,
        $params
    );
}

/**
 * Update activity grades
 *
 * @param $basicurait
 * @param int $userid
 * @param bool $nullifnone
 */
function urait_update_grades($basicurait, $userid=0, $nullifnone=true) {
    global $CFG;
    require_once($CFG->dirroot.'/mod/lti/servicelib.php');
    // LTI doesn't have its own grade table so the only thing to do is update the grade item.
    if (lti_accepts_grades($basicurait)) {
        urait_grade_item_update($basicurait);
    }
}

/**
 * Delete grade item for given basicurait
 *
 * @param $basicurait
 * @return int
 */
function urait_grade_item_delete($basicurait) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update(
        'mod/urait',
        $basicurait->course, 'mod',
        'urait',
        $basicurait->id,
        0,
        null,
        ['deleted' => 1]
    );
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param $urait
 * @param $course
 * @param $cm
 * @param $context
 * @throws coding_exception
 */
function urait_view($urait, $course, $cm, $context) {

    // Trigger course_module_viewed event.
    $params = [
        'context' => $context,
        'objectid' => $urait->id
    ];

    $event = \mod_lti\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('urait', $urait);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}
