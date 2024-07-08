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
 * Utility code for LTI service handling.
 *
 * @package mod_urait
 * @copyright 2024 LMS-Service {@link https://lms-service.ru/}
 * @author Daniil Romanov
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/mod/lti/OAuthBody.php');
require_once($CFG->dirroot.'/mod/lti/locallib.php');
require_once($CFG->dirroot.'/mod/urait/locallib.php');

use moodle\mod\lti as lti;

define('ULTI_ITEM_TYPE', 'mod');
define('ULTI_ITEM_MODULE', 'urait');
define('ULTI_SOURCE', 'mod/urait');

function urait_get_response_xml($codemajor, $description, $messageref, $messagetype) {
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><imsx_POXEnvelopeResponse />');
    $xml->addAttribute('xmlns', 'http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0');

    $headerinfo = $xml->addChild('imsx_POXHeader')->addChild('imsx_POXResponseHeaderInfo');

    $headerinfo->addChild('imsx_version', 'V1.0');
    $headerinfo->addChild('imsx_messageIdentifier', (string)mt_rand());

    $statusinfo = $headerinfo->addChild('imsx_statusInfo');
    $statusinfo->addchild('imsx_codeMajor', $codemajor);
    $statusinfo->addChild('imsx_severity', 'status');
    $statusinfo->addChild('imsx_description', $description);
    $statusinfo->addChild('imsx_messageRefIdentifier', $messageref);
    $incomingtype = str_replace('Response', 'Request', $messagetype);
    $statusinfo->addChild('imsx_operationRefIdentifier', $incomingtype);

    $xml->addChild('imsx_POXBody')->addChild($messagetype);

    return $xml;
}

function urait_update_grade($ltiinstance, $userid, $launchid, $gradeval) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    $params = array();
    $params['itemname'] = $ltiinstance->name;

    $gradeval = $gradeval * floatval($ltiinstance->grade);

    $grade = new stdClass();
    $grade->userid   = $userid;
    $grade->rawgrade = $gradeval;

    $status = grade_update(ULTI_SOURCE, $ltiinstance->course, ULTI_ITEM_TYPE, ULTI_ITEM_MODULE, $ltiinstance->id, 0, $grade, $params);

    $record = $DB->get_record('lti_submission', array('ltiid' => $ltiinstance->id, 'userid' => $userid,
        'launchid' => $launchid), 'id');
    if ($record) {
        $id = $record->id;
    } else {
        $id = null;
    }

    if (!empty($id)) {
        $DB->update_record('lti_submission', array(
            'id' => $id,
            'dateupdated' => time(),
            'gradepercent' => $gradeval,
            'state' => 2
        ));
    } else {
        $DB->insert_record('lti_submission', array(
            'ltiid' => $ltiinstance->id,
            'userid' => $userid,
            'datesubmitted' => time(),
            'dateupdated' => time(),
            'gradepercent' => $gradeval,
            'originalgrade' => $gradeval,
            'launchid' => $launchid,
            'state' => 1
        ));
    }

    return $status == GRADE_UPDATE_OK;
}

function urait_read_grade($ltiinstance, $userid) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $grades = grade_get_grades($ltiinstance->course, ULTI_ITEM_TYPE, ULTI_ITEM_MODULE, $ltiinstance->id, $userid);

    $ltigrade = floatval($ltiinstance->grade);

    if (!empty($ltigrade) && isset($grades) && isset($grades->items[0]) && is_array($grades->items[0]->grades)) {
        foreach ($grades->items[0]->grades as $agrade) {
            $grade = $agrade->grade;
            if (isset($grade)) {
                return $grade / $ltigrade;
            }
        }
    }
}

function urait_delete_grade($ltiinstance, $userid) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $grade = new stdClass();
    $grade->userid   = $userid;
    $grade->rawgrade = null;

    $status = grade_update(ULTI_SOURCE, $ltiinstance->course, ULTI_ITEM_TYPE, ULTI_ITEM_MODULE, $ltiinstance->id, 0, $grade);

    return $status == GRADE_UPDATE_OK;
}

function urait_verify_message($key, $sharedsecrets, $body, $headers = null) {
    foreach ($sharedsecrets as $secret) {
        $signaturefailed = false;

        try {
            lti\handle_oauth_body_post($key, $secret, $body, $headers);
        } catch (Exception $e) {
            debugging('LTI message verification failed: '.$e->getMessage());
            $signaturefailed = true;
        }

        if (!$signaturefailed) {
            return $secret; // Return the secret used to sign the message).
        }
    }

    return false;
}

/**
 * Validate source ID from external request
 *
 * @param object $ltiinstance
 * @param object $parsed
 * @throws Exception
 */
function urait_verify_sourcedid($ltiinstance, $parsed) {
    $sourceid = lti_build_sourcedid($parsed->instanceid, $parsed->userid,
        $ltiinstance->servicesalt, $parsed->typeid, $parsed->launchid);

    if ($sourceid->hash != $parsed->sourcedidhash) {
        throw new Exception('SourcedId hash not valid');
    }
}

/**
 * Extend the LTI services through the ltisource plugins
 *
 * @param stdClass $data LTI request data
 * @return bool
 * @throws coding_exception
 */
function urait_extend_lti_services($data) {
    $plugins = get_plugin_list_with_function('uraitsource', $data->messagetype);
    if (!empty($plugins)) {
        // There can only be one.
        if (count($plugins) > 1) {
            throw new coding_exception('More than one ltisource plugin handler found');
        }
        $data->xml = new SimpleXMLElement($data->body);
        $callback = current($plugins);
        call_user_func($callback, $data);

        return true;
    }
    return false;
}
