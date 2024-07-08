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

namespace mod_urait\task;

defined('MOODLE_INTERNAL') || die();

use core\task\scheduled_task;

/**
 * Class restore_urait_type_default_data
 * @package mod_urait\task
 */
class restore_urait_type_default_data extends scheduled_task {

    /**
     * @return \lang_string|string
     * @throws \coding_exception
     */
    public function get_name() {
        return get_string('uraitsetinitiatelogin', 'mod_urait');
    }

    public function execute() {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/lti/locallib.php');
        require_once($CFG->dirroot . '/mod/urait/locallib.php');

        $existingtypeid = $DB->get_field('lti_types', 'id', ['name' => 'Образовательная платформа «Юрайт»']);

        $uraittype = (object) [
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

        if ($existingtypeid) {
            $uraittype->id = $existingtypeid;
        }

        $type = new \stdClass();
        $type->state = LTI_TOOL_STATE_CONFIGURED;
        lti_load_type_if_cartridge($uraittype);
        $typeid = urait_add_type($type, $uraittype);

        if (!$existingtypeid) {
            $uraitobjs = $DB->get_records_menu('urait', [], '', 'id, ltiid');

            foreach ($uraitobjs as $uraitid => $ltiid) {
                $DB->update_record('urait', (object) ['id' => $uraitid, 'typeid' => $typeid]);
            }
        }
    }
}
