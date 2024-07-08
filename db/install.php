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

function xmldb_urait_install() {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/mod/lti/locallib.php');
    require_once($CFG->dirroot . '/mod/urait/locallib.php');

    if (!$DB->record_exists('lti_types', ['name' => 'Образовательная платформа «Юрайт»'])) {
        $uraittype = (object)[
            'lti_typename' => 'Образовательная платформа «Юрайт»',
            'lti_toolurl' => 'https://urait.ru',
            'lti_description' => '',
            'lti_ltiversion' => '1.3.0',
            'lti_keytype' => 'JWK_KEYSET',
            'lti_publickeyset' => 'https://urait.ru/lti13/jwks',
            'lti_initiatelogin' => URAIT_DEFAULT_INITIATELOGIN,
            'lti_redirectionuris' => 'https://urait.ru/lti13/launch',
            'lti_customparameters' => '',
            'lti_coursevisible' => '0',
            'typeid' => 0,
            'lti_launchcontainer' => '3',
            'lti_contentitem' => '0',
            'oldicon' => '',
            'lti_secureicon' => '',
            'lti_coursecategories' => '',
            'ltiservice_gradesynchronization' => '1',
            'ltiservice_memberships' => '0',
            'ltiservice_toolsettings' => '0',
            'lti_sendname' => '1',
            'lti_sendemailaddr' => '1',
            'lti_acceptgrades' => '1',
            'lti_organizationid_default' => 'SITEID',
            'lti_organizationid' => '',
            'lti_organizationurl' => '',
            'tab' => '',
            'course' => SITEID,
        ];

        $type = new \stdClass();
        $type->state = LTI_TOOL_STATE_CONFIGURED;
        lti_load_type_if_cartridge($uraittype);
        urait_add_type($type, $uraittype);
    }
}
