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
 * This file contains the library of functions and constants for the lti module
 *
 * @package mod_urait
 * @copyright 2024 LMS-Service {@link https://lms-service.ru/}
 * @author Daniil Romanov
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

use mod_lti\helper;
use moodle\mod\lti as lti;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use mod_lti\local\ltiopenid\jwks_helper;
use mod_lti\local\ltiopenid\registration_helper;

global $CFG;
require_once($CFG->dirroot.'/mod/lti/OAuth.php');
require_once($CFG->libdir.'/weblib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot.'/mod/lti/locallib.php');
require_once($CFG->dirroot . '/mod/lti/TrivialStore.php');

define('URAIT_DEFAULT_INITIATELOGIN', 'https://urait.ru/lti13/login/URAIT_CONSUMER_KEY/URAIT_TOKEN');

/**
 * Generate the form for initiating a login request for an LTI 1.3 message
 *
 * @param $courseid
 * @param $cmid
 * @param $instance
 * @param $config
 * @param string $messagetype
 * @param string $title
 * @param string $text
 * @param int $foruserid
 * @return string
 */
function urait_initiate_login($courseid, $cmid, $instance, $config, $messagetype = 'basic-lti-launch-request',
                            $title = '', $text = '', $foruserid = 0) {

    $params = urait_build_login_request($courseid, $cmid, $instance, $config, $messagetype, $foruserid, $title, $text);
    $params['auth_url'] = '/mod/urait/auth.php';

    $initiatelogin = urait_update_credentials_relevance();

    $r = "<form action=\"" . $initiatelogin .
        "\" name=\"UraitInitiateLoginForm\" id=\"UraitInitiateLoginForm\" method=\"post\" " .
        "encType=\"application/x-www-form-urlencoded\">\n";

    foreach ($params as $key => $value) {
        $key = htmlspecialchars($key, ENT_COMPAT);
        $value = htmlspecialchars($value, ENT_COMPAT);
        $r .= "  <input type=\"hidden\" name=\"{$key}\" value=\"{$value}\"/>\n";
    }
    $r .= "</form>\n";

    $r .= "<script type=\"text/javascript\">\n" .
        "//<![CDATA[\n" .
        "document.UraitInitiateLoginForm.submit();\n" .
        "//]]>\n" .
        "</script>\n";

    return $r;
}

/**
 * Return the launch data required for opening the external tool.
 *
 * @param  stdClass $instance the external tool activity settings
 * @param  string $nonce  the nonce value to use (applies to LTI 1.3 only)
 * @return array the endpoint URL and parameters (including the signature)
 * @since  Moodle 3.0
 */
function urait_get_launch_data($instance, $nonce = '', $messagetype = 'basic-lti-launch-request', $foruserid = 0) {
    global $PAGE, $USER;
    $messagetype = $messagetype ? $messagetype : 'basic-lti-launch-request';
    $tool = lti_get_instance_type($instance);
    if ($tool) {
        $typeid = $tool->id;
        $ltiversion = $tool->ltiversion;
    } else {
        $typeid = null;
        $ltiversion = LTI_VERSION_1;
    }

    if ($typeid) {
        $typeconfig = lti_get_type_config($typeid);
    } else {
        // There is no admin configuration for this tool. Use configuration in the lti instance record plus some defaults.
        $typeconfig = (array)$instance;

        $typeconfig['sendname'] = $instance->instructorchoicesendname;
        $typeconfig['sendemailaddr'] = $instance->instructorchoicesendemailaddr;
        $typeconfig['customparameters'] = $instance->instructorcustomparameters;
        $typeconfig['acceptgrades'] = $instance->instructorchoiceacceptgrades;
        $typeconfig['allowroster'] = $instance->instructorchoiceallowroster;
        $typeconfig['forcessl'] = '0';
    }

    if (isset($tool->toolproxyid)) {
        $toolproxy = lti_get_tool_proxy($tool->toolproxyid);
        $key = $toolproxy->guid;
        $secret = $toolproxy->secret;
    } else {
        $toolproxy = null;
        if (!empty($instance->resourcekey)) {
            $key = $instance->resourcekey;
        } else if ($ltiversion === LTI_VERSION_1P3) {
            $key = $tool->clientid;
        } else if (!empty($typeconfig['resourcekey'])) {
            $key = $typeconfig['resourcekey'];
        } else {
            $key = '';
        }
        if (!empty($instance->password)) {
            $secret = $instance->password;
        } else if (!empty($typeconfig['password'])) {
            $secret = $typeconfig['password'];
        } else {
            $secret = '';
        }
    }

    $endpoint = !empty($instance->toolurl) ? $instance->toolurl : $typeconfig['toolurl'];
    $endpoint = trim($endpoint);

    // If the current request is using SSL and a secure tool URL is specified, use it.
    if (lti_request_is_using_ssl() && !empty($instance->securetoolurl)) {
        $endpoint = trim($instance->securetoolurl);
    }

    // If SSL is forced, use the secure tool url if specified. Otherwise, make sure https is on the normal launch URL.
    if (isset($typeconfig['forcessl']) && ($typeconfig['forcessl'] == '1')) {
        if (!empty($instance->securetoolurl)) {
            $endpoint = trim($instance->securetoolurl);
        }

        if ($endpoint !== '') {
            $endpoint = lti_ensure_url_is_https($endpoint);
        }
    } else if ($endpoint !== '' && !strstr($endpoint, '://')) {
        $endpoint = 'http://' . $endpoint;
    }

    $orgid = lti_get_organizationid($typeconfig);

    $course = $PAGE->course;
    $islti2 = isset($tool->toolproxyid);
    $allparams = urait_build_request($instance, $typeconfig, $course, $typeid, $islti2, $messagetype, $foruserid);
    if ($islti2) {
        $requestparams = lti_build_request_lti2($tool, $allparams);
    } else {
        $requestparams = $allparams;
    }
    $requestparams = array_merge($requestparams, lti_build_standard_message($instance, $orgid, $ltiversion, $messagetype));
    $customstr = '';
    if (isset($typeconfig['customparameters'])) {
        $customstr = $typeconfig['customparameters'];
    }
    $services = urait_get_services();
    foreach ($services as $service) {
        [$endpoint, $customstr] = $service->override_endpoint($messagetype,
            $endpoint, $customstr, $instance->course, $instance);
    }
    $requestparams = array_merge($requestparams, lti_build_custom_parameters($toolproxy, $tool, $instance, $allparams, $customstr,
        $instance->instructorcustomparameters, $islti2));

    $launchcontainer = lti_get_launch_container($instance, $typeconfig);
    $returnurlparams = array('course' => $course->id,
        'launch_container' => $launchcontainer,
        'instanceid' => $instance->id,
        'sesskey' => sesskey());

    // Add the return URL. We send the launch container along to help us avoid frames-within-frames when the user returns.
    $url = new \moodle_url('/mod/urait/return.php', $returnurlparams);
    $returnurl = $url->out(false);

    if (isset($typeconfig['forcessl']) && ($typeconfig['forcessl'] == '1')) {
        $returnurl = lti_ensure_url_is_https($returnurl);
    }

    $target = '';
    switch($launchcontainer) {
        case LTI_LAUNCH_CONTAINER_EMBED:
        case LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS:
            $target = 'iframe';
            break;
        case LTI_LAUNCH_CONTAINER_REPLACE_MOODLE_WINDOW:
            $target = 'frame';
            break;
        case LTI_LAUNCH_CONTAINER_WINDOW:
            $target = 'window';
            break;
    }

    if (!empty($target)) {
        $requestparams['launch_presentation_document_target'] = $target;
    }
    $requestparams['launch_presentation_return_url'] = $returnurl;

    // Add the parameters configured by the LTI services.
    if ($typeid && !$islti2) {
        $services = urait_get_services();
        foreach ($services as $service) {
            $serviceparameters = $service->get_launch_parameters('basic-lti-launch-request',
                $course->id, $USER->id , $typeid, $instance->id);
            foreach ($serviceparameters as $paramkey => $paramvalue) {
                $requestparams['custom_' . $paramkey] = urait_parse_custom_parameter($toolproxy, $tool, $requestparams, $paramvalue,
                    $islti2);
            }
        }
    }

    // Allow request params to be updated by sub-plugins.
    $plugins = core_component::get_plugin_list('uraitsource');
    foreach (array_keys($plugins) as $plugin) {
        $pluginparams = component_callback('uraitsource_'.$plugin, 'before_launch',
            array($instance, $endpoint, $requestparams), array());

        if (!empty($pluginparams) && is_array($pluginparams)) {
            $requestparams = array_merge($requestparams, $pluginparams);
        }
    }

    if ((!empty($key) && !empty($secret)) || ($ltiversion === LTI_VERSION_1P3)) {
        if ($ltiversion !== LTI_VERSION_1P3) {
            $parms = lti_sign_parameters($requestparams, $endpoint, 'POST', $key, $secret);
        } else {
            $parms = urait_sign_jwt($requestparams, $endpoint, $key, $typeid, $nonce);
        }

        $endpointurl = new \moodle_url($endpoint);
        $endpointparams = $endpointurl->params();

        // Strip querystring params in endpoint url from $parms to avoid duplication.
        if (!empty($endpointparams) && !empty($parms)) {
            foreach (array_keys($endpointparams) as $paramname) {
                if (isset($parms[$paramname])) {
                    unset($parms[$paramname]);
                }
            }
        }

    } else {
        // If no key and secret, do the launch unsigned.
        $returnurlparams['unsigned'] = '1';
        $parms = $requestparams;
    }

    return array($endpoint, $parms);
}

/**
 * This function builds the request that must be sent to the tool producer
 *
 * @param object    $instance       Basic LTI instance object
 * @param array     $typeconfig     Basic LTI tool configuration
 * @param object    $course         Course object
 * @param int|null  $typeid         Basic LTI tool ID
 * @param boolean   $islti2         True if an LTI 2 tool is being launched
 * @param string    $messagetype    LTI Message Type for this launch
 * @param int       $foruserid      User targeted by this launch
 *
 * @return array                    Request details
 */
function urait_build_request($instance, $typeconfig, $course, $typeid = null, $islti2 = false,
                           $messagetype = 'basic-lti-launch-request', $foruserid = 0) {
    global $USER, $CFG;

    if (empty($instance->cmid)) {
        $instance->cmid = 0;
    }

    $role = lti_get_ims_role($USER, $instance->cmid, $instance->course, $islti2);

    $requestparams = array(
        'user_id' => $USER->id,
        'lis_person_sourcedid' => $USER->idnumber,
        'roles' => $role,
        'context_id' => $course->id,
        'context_label' => trim(html_to_text($course->shortname, 0)),
        'context_title' => trim(html_to_text($course->fullname, 0)),
    );
    if ($foruserid) {
        $requestparams['for_user_id'] = $foruserid;
    }
    if ($messagetype) {
        $requestparams['lti_message_type'] = $messagetype;
    }
    if (!empty($instance->name)) {
        $requestparams['resource_link_title'] = trim(html_to_text($instance->name, 0));
    }
    if (!empty($instance->cmid)) {
        $intro = format_module_intro('urait', $instance, $instance->cmid);
        $intro = trim(html_to_text($intro, 0, false));

        // This may look weird, but this is required for new lines
        // so we generate the same OAuth signature as the tool provider.
        $intro = str_replace("\n", "\r\n", $intro);
        $requestparams['resource_link_description'] = $intro;
    }
    if (!empty($instance->id)) {
        $requestparams['resource_link_id'] = $instance->id;
    }
    if (!empty($instance->resource_link_id)) {
        $requestparams['resource_link_id'] = $instance->resource_link_id;
    }
    if ($course->format == 'site') {
        $requestparams['context_type'] = 'Group';
    } else {
        $requestparams['context_type'] = 'CourseSection';
        $requestparams['lis_course_section_sourcedid'] = $course->idnumber;
    }

    if (!empty($instance->id) && !empty($instance->servicesalt) && ($islti2 ||
            $typeconfig['acceptgrades'] == LTI_SETTING_ALWAYS ||
            ($typeconfig['acceptgrades'] == LTI_SETTING_DELEGATE && $instance->instructorchoiceacceptgrades == LTI_SETTING_ALWAYS))
    ) {
        $placementsecret = $instance->servicesalt;
        $sourcedid = json_encode(lti_build_sourcedid($instance->id, $USER->id, $placementsecret, $typeid));
        $requestparams['lis_result_sourcedid'] = $sourcedid;

        // Add outcome service URL.
        $serviceurl = new \moodle_url('/mod/urait/service.php');
        $serviceurl = $serviceurl->out();

        $forcessl = false;
        if (!empty($CFG->mod_lti_forcessl)) {
            $forcessl = true;
        }

        if ((isset($typeconfig['forcessl']) && ($typeconfig['forcessl'] == '1')) or $forcessl) {
            $serviceurl = lti_ensure_url_is_https($serviceurl);
        }

        $requestparams['lis_outcome_service_url'] = $serviceurl;
    }

    // Send user's name and email data if appropriate.
    if ($islti2 || $typeconfig['sendname'] == LTI_SETTING_ALWAYS ||
        ($typeconfig['sendname'] == LTI_SETTING_DELEGATE && isset($instance->instructorchoicesendname)
            && $instance->instructorchoicesendname == LTI_SETTING_ALWAYS)
    ) {
        $requestparams['lis_person_name_given'] = $USER->firstname;
        $requestparams['lis_person_name_family'] = $USER->lastname;
        $requestparams['lis_person_name_full'] = fullname($USER);
        $requestparams['ext_user_username'] = $USER->username;
    }

    if ($islti2 || $typeconfig['sendemailaddr'] == LTI_SETTING_ALWAYS ||
        ($typeconfig['sendemailaddr'] == LTI_SETTING_DELEGATE && isset($instance->instructorchoicesendemailaddr)
            && $instance->instructorchoicesendemailaddr == LTI_SETTING_ALWAYS)
    ) {
        $requestparams['lis_person_contact_email_primary'] = $USER->email;
    }

    return $requestparams;
}

/**
 * Parse a custom parameter to replace any substitution variables
 *
 * @param object    $toolproxy      Tool proxy instance object
 * @param object    $tool           Tool instance object
 * @param array     $params         LTI launch parameters
 * @param string    $value          Custom parameter value
 * @param boolean   $islti2         True if an LTI 2 tool is being launched
 *
 * @return string Parsed value of custom parameter
 */
function urait_parse_custom_parameter($toolproxy, $tool, $params, $value, $islti2) {
    global $USER, $COURSE;

    if ($value) {
        if (substr($value, 0, 1) == '\\') {
            $value = substr($value, 1);
        } else if (substr($value, 0, 1) == '$') {
            $value1 = substr($value, 1);
            $enabledcapabilities = lti_get_enabled_capabilities($tool);
            if (!$islti2 || in_array($value1, $enabledcapabilities)) {
                $capabilities = lti_get_capabilities();
                if (array_key_exists($value1, $capabilities)) {
                    $val = $capabilities[$value1];
                    if ($val) {
                        if (substr($val, 0, 1) != '$') {
                            $value = $params[$val];
                        } else {
                            $valarr = explode('->', substr($val, 1), 2);
                            $value = "{${$valarr[0]}->{$valarr[1]}}";
                            $value = str_replace('<br />' , ' ', $value);
                            $value = str_replace('<br>' , ' ', $value);
                            $value = format_string($value);
                        }
                    } else {
                        $value = lti_calculate_custom_parameter($value1);
                    }
                } else {
                    $val = $value;
                    $services = urait_get_services();
                    foreach ($services as $service) {
                        $service->set_tool_proxy($toolproxy);
                        $service->set_type($tool);
                        $value = $service->parse_value($val);
                        if ($val != $value) {
                            break;
                        }
                    }
                }
            }
        }
    }
    return $value;
}

/**
 * Initializes an array with the services supported by the LTI module
 *
 * @return array List of services
 */
function urait_get_services() {
    $services = [];
    $definedservices = core_component::get_plugin_list('uraitservice');

    foreach ($definedservices as $name => $location) {
        $classname = "\\uraitservice_{$name}\\local\\service\\{$name}";
        $services[] = new $classname();
    }
    return $services;
}

/**
 * @param $instance
 * @param int $foruserid
 */
function urait_launch_tool($instance, $foruserid=0) {
    list($endpoint, $parms) = urait_get_launch_data($instance, '', '', $foruserid);
    $debuglaunch = ( $instance->debuglaunch == 1 );

    $content = lti_post_launch_html($parms, $endpoint, $debuglaunch);

    echo $content;
}

function urait_build_content_item_selection_request($id, $course, moodle_url $returnurl, $title = '', $text = '', $mediatypes = [],
                                                  $presentationtargets = [], $autocreate = false, $multiple = true,
                                                  $unsigned = false, $canconfirm = false, $copyadvice = false, $nonce = '') {
    global $USER;

    $tool = lti_get_type($id);

    // Validate parameters.
    if (!$tool) {
        throw new moodle_exception('errortooltypenotfound', 'mod_lti');
    }
    if (!is_array($mediatypes)) {
        throw new coding_exception('The list of accepted media types should be in an array');
    }
    if (!is_array($presentationtargets)) {
        throw new coding_exception('The list of accepted presentation targets should be in an array');
    }

    // Check title. If empty, use the tool's name.
    if (empty($title)) {
        $title = $tool->name;
    }

    $typeconfig = lti_get_type_config($id);
    $key = '';
    $secret = '';
    $islti2 = false;
    $islti13 = false;
    if (isset($tool->toolproxyid)) {
        $islti2 = true;
        $toolproxy = lti_get_tool_proxy($tool->toolproxyid);
        $key = $toolproxy->guid;
        $secret = $toolproxy->secret;
    } else {
        $islti13 = $tool->ltiversion === LTI_VERSION_1P3;
        $toolproxy = null;
        if ($islti13 && !empty($tool->clientid)) {
            $key = $tool->clientid;
        } else if (!$islti13 && !empty($typeconfig['resourcekey'])) {
            $key = $typeconfig['resourcekey'];
        }
        if (!empty($typeconfig['password'])) {
            $secret = $typeconfig['password'];
        }
    }
    $tool->enabledcapability = '';
    if (!empty($typeconfig['enabledcapability_ContentItemSelectionRequest'])) {
        $tool->enabledcapability = $typeconfig['enabledcapability_ContentItemSelectionRequest'];
    }

    $tool->parameter = '';
    if (!empty($typeconfig['parameter_ContentItemSelectionRequest'])) {
        $tool->parameter = $typeconfig['parameter_ContentItemSelectionRequest'];
    }

    // Set the tool URL.
    if (!empty($typeconfig['toolurl_ContentItemSelectionRequest'])) {
        $toolurl = new moodle_url($typeconfig['toolurl_ContentItemSelectionRequest']);
    } else {
        $toolurl = new moodle_url($typeconfig['toolurl']);
    }

    // Check if SSL is forced.
    if (!empty($typeconfig['forcessl'])) {
        // Make sure the tool URL is set to https.
        if (strtolower($toolurl->get_scheme()) === 'http') {
            $toolurl->set_scheme('https');
        }
        // Make sure the return URL is set to https.
        if (strtolower($returnurl->get_scheme()) === 'http') {
            $returnurl->set_scheme('https');
        }
    }
    $toolurlout = $toolurl->out(false);

    // Get base request parameters.
    $instance = new stdClass();
    $instance->course = $course->id;
    $requestparams = urait_build_request($instance, $typeconfig, $course, $id, $islti2);

    // Get LTI2-specific request parameters and merge to the request parameters if applicable.
    if ($islti2) {
        $lti2params = lti_build_request_lti2($tool, $requestparams);
        $requestparams = array_merge($requestparams, $lti2params);
    }

    // Get standard request parameters and merge to the request parameters.
    $orgid = lti_get_organizationid($typeconfig);
    $standardparams = lti_build_standard_message(null, $orgid, $tool->ltiversion, 'ContentItemSelectionRequest');
    $requestparams = array_merge($requestparams, $standardparams);

    // Get custom request parameters and merge to the request parameters.
    $customstr = '';
    if (!empty($typeconfig['customparameters'])) {
        $customstr = $typeconfig['customparameters'];
    }
    $customparams = lti_build_custom_parameters($toolproxy, $tool, $instance, $requestparams, $customstr, '', $islti2);
    $requestparams = array_merge($requestparams, $customparams);

    // Add the parameters configured by the LTI services.
    if ($id && !$islti2) {
        $services = urait_get_services();
        foreach ($services as $service) {
            $serviceparameters = $service->get_launch_parameters('ContentItemSelectionRequest',
                $course->id, $USER->id , $id);
            foreach ($serviceparameters as $paramkey => $paramvalue) {
                $requestparams['custom_' . $paramkey] = urait_parse_custom_parameter($toolproxy, $tool, $requestparams, $paramvalue,
                    $islti2);
            }
        }
    }

    // Allow request params to be updated by sub-plugins.
    $plugins = core_component::get_plugin_list('uraitsource');
    foreach (array_keys($plugins) as $plugin) {
        $pluginparams = component_callback('uraitsource_' . $plugin, 'before_launch', [$instance, $toolurlout, $requestparams], []);

        if (!empty($pluginparams) && is_array($pluginparams)) {
            $requestparams = array_merge($requestparams, $pluginparams);
        }
    }

    if (!$islti13) {
        // Media types. Set to ltilink by default if empty.
        if (empty($mediatypes)) {
            $mediatypes = [
                'application/vnd.ims.lti.v1.ltilink',
            ];
        }
        $requestparams['accept_media_types'] = implode(',', $mediatypes);
    } else {
        // Only LTI links are currently supported.
        $requestparams['accept_types'] = 'ltiResourceLink';
    }

    // Presentation targets. Supports frame, iframe, window by default if empty.
    if (empty($presentationtargets)) {
        $presentationtargets = [
            'frame',
            'iframe',
            'window',
        ];
    }
    $requestparams['accept_presentation_document_targets'] = implode(',', $presentationtargets);

    // Other request parameters.
    $requestparams['accept_copy_advice'] = $copyadvice === true ? 'true' : 'false';
    $requestparams['accept_multiple'] = $multiple === true ? 'true' : 'false';
    $requestparams['accept_unsigned'] = $unsigned === true ? 'true' : 'false';
    $requestparams['auto_create'] = $autocreate === true ? 'true' : 'false';
    $requestparams['can_confirm'] = $canconfirm === true ? 'true' : 'false';
    $requestparams['content_item_return_url'] = $returnurl->out(false);
    $requestparams['title'] = $title;
    $requestparams['text'] = $text;
    if (!$islti13) {
        $signedparams = lti_sign_parameters($requestparams, $toolurlout, 'POST', $key, $secret);
    } else {
        $signedparams = urait_sign_jwt($requestparams, $toolurlout, $key, $id, $nonce);
    }
    $toolurlparams = $toolurl->params();

    // Strip querystring params in endpoint url from $signedparams to avoid duplication.
    if (!empty($toolurlparams) && !empty($signedparams)) {
        foreach (array_keys($toolurlparams) as $paramname) {
            if (isset($signedparams[$paramname])) {
                unset($signedparams[$paramname]);
            }
        }
    }

    // Check for params that should not be passed. Unset if they are set.
    $unwantedparams = [
        'resource_link_id',
        'resource_link_title',
        'resource_link_description',
        'launch_presentation_return_url',
        'lis_result_sourcedid',
    ];
    foreach ($unwantedparams as $param) {
        if (isset($signedparams[$param])) {
            unset($signedparams[$param]);
        }
    }

    // Prepare result object.
    $result = new stdClass();
    $result->params = $signedparams;
    $result->url = $toolurlout;

    return $result;
}

/**
 * Return the mapping for standard message parameters to JWT claim.
 *
 * @return array
 */
function urait_get_jwt_claim_mapping() {
    $mapping = [];
    $services = urait_get_services();
    foreach ($services as $service) {
        $mapping = array_merge($mapping, $service->get_jwt_claim_mappings());
    }
    $mapping = array_merge($mapping, [
        'accept_copy_advice' => [
            'suffix' => 'dl',
            'group' => 'deep_linking_settings',
            'claim' => 'accept_copy_advice',
            'isarray' => false,
            'type' => 'boolean'
        ],
        'accept_media_types' => [
            'suffix' => 'dl',
            'group' => 'deep_linking_settings',
            'claim' => 'accept_media_types',
            'isarray' => true
        ],
        'accept_multiple' => [
            'suffix' => 'dl',
            'group' => 'deep_linking_settings',
            'claim' => 'accept_multiple',
            'isarray' => false,
            'type' => 'boolean'
        ],
        'accept_presentation_document_targets' => [
            'suffix' => 'dl',
            'group' => 'deep_linking_settings',
            'claim' => 'accept_presentation_document_targets',
            'isarray' => true
        ],
        'accept_types' => [
            'suffix' => 'dl',
            'group' => 'deep_linking_settings',
            'claim' => 'accept_types',
            'isarray' => true
        ],
        'accept_unsigned' => [
            'suffix' => 'dl',
            'group' => 'deep_linking_settings',
            'claim' => 'accept_unsigned',
            'isarray' => false,
            'type' => 'boolean'
        ],
        'auto_create' => [
            'suffix' => 'dl',
            'group' => 'deep_linking_settings',
            'claim' => 'auto_create',
            'isarray' => false,
            'type' => 'boolean'
        ],
        'can_confirm' => [
            'suffix' => 'dl',
            'group' => 'deep_linking_settings',
            'claim' => 'can_confirm',
            'isarray' => false,
            'type' => 'boolean'
        ],
        'content_item_return_url' => [
            'suffix' => 'dl',
            'group' => 'deep_linking_settings',
            'claim' => 'deep_link_return_url',
            'isarray' => false
        ],
        'content_items' => [
            'suffix' => 'dl',
            'group' => '',
            'claim' => 'content_items',
            'isarray' => true
        ],
        'data' => [
            'suffix' => 'dl',
            'group' => 'deep_linking_settings',
            'claim' => 'data',
            'isarray' => false
        ],
        'text' => [
            'suffix' => 'dl',
            'group' => 'deep_linking_settings',
            'claim' => 'text',
            'isarray' => false
        ],
        'title' => [
            'suffix' => 'dl',
            'group' => 'deep_linking_settings',
            'claim' => 'title',
            'isarray' => false
        ],
        'lti_msg' => [
            'suffix' => 'dl',
            'group' => '',
            'claim' => 'msg',
            'isarray' => false
        ],
        'lti_log' => [
            'suffix' => 'dl',
            'group' => '',
            'claim' => 'log',
            'isarray' => false
        ],
        'lti_errormsg' => [
            'suffix' => 'dl',
            'group' => '',
            'claim' => 'errormsg',
            'isarray' => false
        ],
        'lti_errorlog' => [
            'suffix' => 'dl',
            'group' => '',
            'claim' => 'errorlog',
            'isarray' => false
        ],
        'context_id' => [
            'suffix' => '',
            'group' => 'context',
            'claim' => 'id',
            'isarray' => false
        ],
        'context_label' => [
            'suffix' => '',
            'group' => 'context',
            'claim' => 'label',
            'isarray' => false
        ],
        'context_title' => [
            'suffix' => '',
            'group' => 'context',
            'claim' => 'title',
            'isarray' => false
        ],
        'context_type' => [
            'suffix' => '',
            'group' => 'context',
            'claim' => 'type',
            'isarray' => true
        ],
        'for_user_id' => [
            'suffix' => '',
            'group' => 'for_user',
            'claim' => 'user_id',
            'isarray' => false
        ],
        'lis_course_offering_sourcedid' => [
            'suffix' => '',
            'group' => 'lis',
            'claim' => 'course_offering_sourcedid',
            'isarray' => false
        ],
        'lis_course_section_sourcedid' => [
            'suffix' => '',
            'group' => 'lis',
            'claim' => 'course_section_sourcedid',
            'isarray' => false
        ],
        'launch_presentation_css_url' => [
            'suffix' => '',
            'group' => 'launch_presentation',
            'claim' => 'css_url',
            'isarray' => false
        ],
        'launch_presentation_document_target' => [
            'suffix' => '',
            'group' => 'launch_presentation',
            'claim' => 'document_target',
            'isarray' => false
        ],
        'launch_presentation_height' => [
            'suffix' => '',
            'group' => 'launch_presentation',
            'claim' => 'height',
            'isarray' => false
        ],
        'launch_presentation_locale' => [
            'suffix' => '',
            'group' => 'launch_presentation',
            'claim' => 'locale',
            'isarray' => false
        ],
        'launch_presentation_return_url' => [
            'suffix' => '',
            'group' => 'launch_presentation',
            'claim' => 'return_url',
            'isarray' => false
        ],
        'launch_presentation_width' => [
            'suffix' => '',
            'group' => 'launch_presentation',
            'claim' => 'width',
            'isarray' => false
        ],
        'lis_person_contact_email_primary' => [
            'suffix' => '',
            'group' => null,
            'claim' => 'email',
            'isarray' => false
        ],
        'lis_person_name_family' => [
            'suffix' => '',
            'group' => null,
            'claim' => 'family_name',
            'isarray' => false
        ],
        'lis_person_name_full' => [
            'suffix' => '',
            'group' => null,
            'claim' => 'name',
            'isarray' => false
        ],
        'lis_person_name_given' => [
            'suffix' => '',
            'group' => null,
            'claim' => 'given_name',
            'isarray' => false
        ],
        'lis_person_sourcedid' => [
            'suffix' => '',
            'group' => 'lis',
            'claim' => 'person_sourcedid',
            'isarray' => false
        ],
        'user_id' => [
            'suffix' => '',
            'group' => null,
            'claim' => 'sub',
            'isarray' => false
        ],
        'user_image' => [
            'suffix' => '',
            'group' => null,
            'claim' => 'picture',
            'isarray' => false
        ],
        'roles' => [
            'suffix' => '',
            'group' => '',
            'claim' => 'roles',
            'isarray' => true
        ],
        'role_scope_mentor' => [
            'suffix' => '',
            'group' => '',
            'claim' => 'role_scope_mentor',
            'isarray' => false
        ],
        'deployment_id' => [
            'suffix' => '',
            'group' => '',
            'claim' => 'deployment_id',
            'isarray' => false
        ],
        'lti_message_type' => [
            'suffix' => '',
            'group' => '',
            'claim' => 'message_type',
            'isarray' => false
        ],
        'lti_version' => [
            'suffix' => '',
            'group' => '',
            'claim' => 'version',
            'isarray' => false
        ],
        'resource_link_description' => [
            'suffix' => '',
            'group' => 'resource_link',
            'claim' => 'description',
            'isarray' => false
        ],
        'resource_link_id' => [
            'suffix' => '',
            'group' => 'resource_link',
            'claim' => 'id',
            'isarray' => false
        ],
        'resource_link_title' => [
            'suffix' => '',
            'group' => 'resource_link',
            'claim' => 'title',
            'isarray' => false
        ],
        'tool_consumer_info_product_family_code' => [
            'suffix' => '',
            'group' => 'tool_platform',
            'claim' => 'product_family_code',
            'isarray' => false
        ],
        'tool_consumer_info_version' => [
            'suffix' => '',
            'group' => 'tool_platform',
            'claim' => 'version',
            'isarray' => false
        ],
        'tool_consumer_instance_contact_email' => [
            'suffix' => '',
            'group' => 'tool_platform',
            'claim' => 'contact_email',
            'isarray' => false
        ],
        'tool_consumer_instance_description' => [
            'suffix' => '',
            'group' => 'tool_platform',
            'claim' => 'description',
            'isarray' => false
        ],
        'tool_consumer_instance_guid' => [
            'suffix' => '',
            'group' => 'tool_platform',
            'claim' => 'guid',
            'isarray' => false
        ],
        'tool_consumer_instance_name' => [
            'suffix' => '',
            'group' => 'tool_platform',
            'claim' => 'name',
            'isarray' => false
        ],
        'tool_consumer_instance_url' => [
            'suffix' => '',
            'group' => 'tool_platform',
            'claim' => 'url',
            'isarray' => false
        ]
    ]);
    return $mapping;
}

/**
 * Update the database with a tool proxy instance
 *
 * @param object   $config    Tool proxy definition
 *
 * @return int  Record id number
 */
function urait_add_tool_proxy($config) {
    global $USER, $DB;

    $toolproxy = new \stdClass();
    if (isset($config->lti_registrationname)) {
        $toolproxy->name = trim($config->lti_registrationname);
    }
    if (isset($config->lti_registrationurl)) {
        $toolproxy->regurl = trim($config->lti_registrationurl);
    }
    if (isset($config->lti_capabilities)) {
        $toolproxy->capabilityoffered = implode("\n", $config->lti_capabilities);
    } else {
        $toolproxy->capabilityoffered = implode("\n", array_keys(lti_get_capabilities()));
    }
    if (isset($config->lti_services)) {
        $toolproxy->serviceoffered = implode("\n", $config->lti_services);
    } else {
        $func = function($s) {
            return $s->get_id();
        };
        $servicenames = array_map($func, urait_get_services());
        $toolproxy->serviceoffered = implode("\n", $servicenames);
    }
    if (isset($config->toolproxyid) && !empty($config->toolproxyid)) {
        $toolproxy->id = $config->toolproxyid;
        if (!isset($toolproxy->state) || ($toolproxy->state != LTI_TOOL_PROXY_STATE_ACCEPTED)) {
            $toolproxy->state = LTI_TOOL_PROXY_STATE_CONFIGURED;
            $toolproxy->guid = random_string();
            $toolproxy->secret = random_string();
        }
        $id = lti_update_tool_proxy($toolproxy);
    } else {
        $toolproxy->state = LTI_TOOL_PROXY_STATE_CONFIGURED;
        $toolproxy->timemodified = time();
        $toolproxy->timecreated = $toolproxy->timemodified;
        if (!isset($toolproxy->createdby)) {
            $toolproxy->createdby = $USER->id;
        }
        $toolproxy->guid = random_string();
        $toolproxy->secret = random_string();
        $id = $DB->insert_record('lti_tool_proxies', $toolproxy);
    }

    return $id;
}

/**
 * Prepares an LTI 1.3 login request
 *
 * @param int            $courseid  Course ID
 * @param int            $cmid        Course Module instance ID
 * @param stdClass|null  $instance  LTI instance
 * @param stdClass       $config    Tool type configuration
 * @param string         $messagetype   LTI message type
 * @param int            $foruserid Id of the user targeted by the launch
 * @param string         $title     Title of content item
 * @param string         $text      Description of content item
 * @return array Login request parameters
 */
function urait_build_login_request($courseid, $cmid, $instance, $config, $messagetype, $foruserid=0, $title = '', $text = '') {
    global $USER, $CFG, $SESSION;
    $ltihint = [];
    if (!empty($instance)) {
        $endpoint = !empty($instance->toolurl) ? $instance->toolurl : $config->lti_toolurl;
        $launchid = 'ltilaunch'.$instance->id.'_'.rand();
        $ltihint['cmid'] = $cmid;
        $SESSION->$launchid = "{$courseid},{$config->typeid},{$cmid},{$messagetype},{$foruserid},,";
    } else {
        $endpoint = $config->lti_toolurl;
        if (($messagetype === 'ContentItemSelectionRequest') && !empty($config->lti_toolurl_ContentItemSelectionRequest)) {
            $endpoint = $config->lti_toolurl_ContentItemSelectionRequest;
        }
        $launchid = "ltilaunch_$messagetype".rand();
        $SESSION->$launchid =
            "{$courseid},{$config->typeid},,{$messagetype},{$foruserid}," . base64_encode($title) . ',' . base64_encode($text);
    }
    $endpoint = trim($endpoint);
    $services = urait_get_services();
    foreach ($services as $service) {
        [$endpoint] = $service->override_endpoint($messagetype ?? 'basic-lti-launch-request', $endpoint, '', $courseid, $instance);
    }

    $ltihint['launchid'] = $launchid;
    // If SSL is forced make sure https is on the normal launch URL.
    if (isset($config->lti_forcessl) && ($config->lti_forcessl == '1')) {
        $endpoint = lti_ensure_url_is_https($endpoint);
    } else if (!strstr($endpoint, '://')) {
        $endpoint = 'http://' . $endpoint;
    }

    $params = array();
    $params['iss'] = $CFG->wwwroot;
    $params['target_link_uri'] = $endpoint;
    $params['login_hint'] = $USER->id;
    $params['lti_message_hint'] = json_encode($ltihint);
    $params['client_id'] = $config->lti_clientid;
    $params['lti_deployment_id'] = $config->typeid;
    return $params;
}

/**
 * Initializes an array with the scopes for services supported by the LTI module
 * and authorized for this particular tool instance.
 *
 * @param object $type  LTI tool type
 * @param array  $typeconfig  LTI tool type configuration
 *
 * @return array List of scopes
 */
function urait_get_permitted_service_scopes($type, $typeconfig) {

    $services = urait_get_services();
    $scopes = array();
    foreach ($services as $service) {
        $service->set_type($type);
        $service->set_typeconfig($typeconfig);
        $servicescopes = $service->get_permitted_scopes();
        if (!empty($servicescopes)) {
            $scopes = array_merge($scopes, $servicescopes);
        }
    }

    return $scopes;
}

function urait_sign_jwt($parms, $endpoint, $oauthconsumerkey, $typeid = 0, $nonce = '') {
    global $CFG;

    if (empty($typeid)) {
        $typeid = 0;
    }
    $messagetypemapping = lti_get_jwt_message_type_mapping();
    if (isset($parms['lti_message_type']) && array_key_exists($parms['lti_message_type'], $messagetypemapping)) {
        $parms['lti_message_type'] = $messagetypemapping[$parms['lti_message_type']];
    }
    if (isset($parms['roles'])) {
        $roles = explode(',', $parms['roles']);
        $newroles = array();
        foreach ($roles as $role) {
            if (strpos($role, 'urn:lti:role:ims/lis/') === 0) {
                $role = 'http://purl.imsglobal.org/vocab/lis/v2/membership#' . substr($role, 21);
            } else if (strpos($role, 'urn:lti:instrole:ims/lis/') === 0) {
                $role = 'http://purl.imsglobal.org/vocab/lis/v2/institution/person#' . substr($role, 25);
            } else if (strpos($role, 'urn:lti:sysrole:ims/lis/') === 0) {
                $role = 'http://purl.imsglobal.org/vocab/lis/v2/system/person#' . substr($role, 24);
            } else if ((strpos($role, '://') === false) && (strpos($role, 'urn:') !== 0)) {
                $role = "http://purl.imsglobal.org/vocab/lis/v2/membership#{$role}";
            }
            $newroles[] = $role;
        }
        $parms['roles'] = implode(',', $newroles);
    }

    $now = time();
    if (empty($nonce)) {
        $nonce = bin2hex(openssl_random_pseudo_bytes(10));
    }
    $claimmapping = urait_get_jwt_claim_mapping();
    $payload = array(
        'nonce' => $nonce,
        'iat' => $now,
        'exp' => $now + 60,
    );
    $payload['iss'] = $CFG->wwwroot;
    $payload['aud'] = $oauthconsumerkey;
    $payload[LTI_JWT_CLAIM_PREFIX . '/claim/deployment_id'] = strval($typeid);
    $payload[LTI_JWT_CLAIM_PREFIX . '/claim/target_link_uri'] = $endpoint;

    foreach ($parms as $key => $value) {
        $claim = LTI_JWT_CLAIM_PREFIX;
        if (array_key_exists($key, $claimmapping)) {
            $mapping = $claimmapping[$key];
            $type = $mapping["type"] ?? "string";
            if ($mapping['isarray']) {
                $value = explode(',', $value);
                sort($value);
            } else if ($type == 'boolean') {
                $value = isset($value) && ($value == 'true');
            }
            if (!empty($mapping['suffix'])) {
                $claim .= "-{$mapping['suffix']}";
            }
            $claim .= '/claim/';
            if (is_null($mapping['group'])) {
                $payload[$mapping['claim']] = $value;
            } else if (empty($mapping['group'])) {
                $payload["{$claim}{$mapping['claim']}"] = $value;
            } else {
                $claim .= $mapping['group'];
                $payload[$claim][$mapping['claim']] = $value;
            }
        } else if (strpos($key, 'custom_') === 0) {
            $payload["{$claim}/claim/custom"][substr($key, 7)] = $value;
        } else if (strpos($key, 'ext_') === 0) {
            $payload["{$claim}/claim/ext"][substr($key, 4)] = $value;
        }
    }

    $privatekey = \mod_lti\local\ltiopenid\jwks_helper::get_private_key();
    $jwt = JWT::encode($payload, $privatekey['key'], 'RS256', $privatekey['kid']);

    $newparms = array();
    $newparms['id_token'] = $jwt;

    return $newparms;
}

/**
 * This function builds the tab for a category of tool proxies
 *
 * @param object    $toolproxies    Tool proxy instance objects
 * @param string    $id             Category ID
 *
 * @return string                   HTML for tab
 */
function urait_get_tool_proxy_table($toolproxies, $id) {
    global $OUTPUT;

    if (!empty($toolproxies)) {
        $typename = get_string('typename', 'lti');
        $url = get_string('registrationurl', 'lti');
        $action = get_string('action', 'lti');
        $createdon = get_string('createdon', 'lti');

        $html = <<< EOD
        <div id="{$id}_tool_proxies_container" style="margin-top: 0.5em; margin-bottom: 0.5em">
            <table id="{$id}_tool_proxies">
                <thead>
                    <tr>
                        <th>{$typename}</th>
                        <th>{$url}</th>
                        <th>{$createdon}</th>
                        <th>{$action}</th>
                    </tr>
                </thead>
EOD;
        foreach ($toolproxies as $toolproxy) {
            $date = userdate($toolproxy->timecreated, get_string('strftimedatefullshort', 'core_langconfig'));
            $accept = get_string('register', 'lti');
            $update = get_string('update', 'lti');
            $delete = get_string('delete', 'lti');

            $baseurl = new \moodle_url('/mod/urait/registersettings.php', array(
                'action' => 'accept',
                'id' => $toolproxy->id,
                'sesskey' => sesskey(),
                'tab' => $id
            ));

            $registerurl = new \moodle_url('/mod/urait/register.php', array(
                'id' => $toolproxy->id,
                'sesskey' => sesskey(),
                'tab' => 'tool_proxy'
            ));

            $accepthtml = $OUTPUT->action_icon($registerurl,
                new \pix_icon('t/check', $accept, '', array('class' => 'iconsmall')), null,
                array('title' => $accept, 'class' => 'editing_accept'));

            $deleteaction = 'delete';

            if ($toolproxy->state != LTI_TOOL_PROXY_STATE_CONFIGURED) {
                $accepthtml = '';
            }

            if (($toolproxy->state == LTI_TOOL_PROXY_STATE_CONFIGURED) || ($toolproxy->state == LTI_TOOL_PROXY_STATE_PENDING)) {
                $delete = get_string('cancel', 'lti');
            }

            $updateurl = clone($baseurl);
            $updateurl->param('action', 'update');
            $updatehtml = $OUTPUT->action_icon($updateurl,
                new \pix_icon('t/edit', $update, '', array('class' => 'iconsmall')), null,
                array('title' => $update, 'class' => 'editing_update'));

            $deleteurl = clone($baseurl);
            $deleteurl->param('action', $deleteaction);
            $deletehtml = $OUTPUT->action_icon($deleteurl,
                new \pix_icon('t/delete', $delete, '', array('class' => 'iconsmall')), null,
                array('title' => $delete, 'class' => 'editing_delete'));
            $html .= <<< EOD
            <tr>
                <td>
                    {$toolproxy->name}
                </td>
                <td>
                    {$toolproxy->regurl}
                </td>
                <td>
                    {$date}
                </td>
                <td align="center">
                    {$accepthtml}{$updatehtml}{$deletehtml}
                </td>
            </tr>
EOD;
        }
        $html .= '</table></div>';
    } else {
        $html = get_string('no_' . $id, 'lti');
    }

    return $html;
}

/**
 * Returns the icon and edit urls for the tool type and the course url if it is a course type.
 *
 * @param stdClass $type The tool type
 *
 * @return array The urls of the tool type
 */
function urait_get_tool_type_urls(stdClass $type) {
    $courseurl = get_tool_type_course_url($type);

    $urls = array(
        'icon' => get_tool_type_icon_url($type),
        'edit' => get_tool_type_edit_url($type),
    );

    if ($courseurl) {
        $urls['course'] = $courseurl;
    }

    $url = new moodle_url('/mod/lti/certs.php');
    $urls['publickeyset'] = $url->out();
    $url = new moodle_url('/mod/urait/token.php');
    $urls['accesstoken'] = $url->out();
    $url = new moodle_url('/mod/urait/auth.php');
    $urls['authrequest'] = $url->out();

    return $urls;
}


/**
* Serialises this tool type
*
 * @param stdClass $type The tool type
*
 * @return array An array of values representing this type
*/
function urait_serialise_tool_type(stdClass $type) {
    global $CFG;

    $capabilitygroups = get_tool_type_capability_groups($type);
    $instanceids = get_tool_type_instance_ids($type);
    // Clean the name. We don't want tags here.
    $name = clean_param($type->name, PARAM_NOTAGS);
    if (!empty($type->description)) {
        // Clean the description. We don't want tags here.
        $description = clean_param($type->description, PARAM_NOTAGS);
    } else {
        $description = get_string('editdescription', 'mod_lti');
    }
    return array(
        'id' => $type->id,
        'name' => $name,
        'description' => $description,
        'urls' => urait_get_tool_type_urls($type),
        'state' => get_tool_type_state_info($type),
        'platformid' => $CFG->wwwroot,
        'clientid' => $type->clientid,
        'deploymentid' => $type->id,
        'hascapabilitygroups' => !empty($capabilitygroups),
        'capabilitygroups' => $capabilitygroups,
        // Course ID of 1 means it's not linked to a course.
        'courseid' => $type->course == 1 ? 0 : $type->course,
        'instanceids' => $instanceids,
        'instancecount' => count($instanceids)
    );
}

/**
 * Get both LTI tool proxies and tool types.
 *
 * If limit and offset are not zero, a subset of the tools will be returned. Tool proxies will be counted before tool
 * types.
 * For example: If 10 tool proxies and 10 tool types exist, and the limit is set to 15, then 10 proxies and 5 types
 * will be returned.
 *
 * @param int $limit Maximum number of tools returned.
 * @param int $offset Do not return tools before offset index.
 * @param bool $orphanedonly If true, only return orphaned proxies.
 * @param int $toolproxyid If not 0, only return tool types that have this tool proxy id.
 * @return array list(proxies[], types[]) List containing array of tool proxies and array of tool types.
 */
function urait_get_lti_types_and_proxies(int $limit = 0, int $offset = 0, bool $orphanedonly = false, int $toolproxyid = 0): array {
    global $DB;

    if ($orphanedonly) {
        $orphanedproxiessql = helper::get_tool_proxy_sql($orphanedonly, false);
        $countsql = helper::get_tool_proxy_sql($orphanedonly, true);
        $proxies  = $DB->get_records_sql($orphanedproxiessql, null, $offset, $limit);
        $totalproxiescount = $DB->count_records_sql($countsql);
    } else {
        $proxies = $DB->get_records('lti_tool_proxies', null, 'name ASC, state DESC, timemodified DESC',
            '*', $offset, $limit);
        $totalproxiescount = $DB->count_records('lti_tool_proxies');
    }

    // Find new offset and limit for tool types after getting proxies and set up query.
    $typesoffset = max($offset - $totalproxiescount, 0); // Set to 0 if negative.
    $typeslimit = max($limit - count($proxies), 0); // Set to 0 if negative.
    $typesparams = [];
    if (!empty($toolproxyid)) {
        $typesparams['toolproxyid'] = $toolproxyid;
    }

    $types = $DB->get_records('lti_types', $typesparams, 'name ASC, state DESC, timemodified DESC',
        '*', $typesoffset, $typeslimit);

    return [$proxies, array_map('urait_serialise_tool_type', $types)];
}

/**
 *
 * Generates some of the tool configuration based on the instance details
 *
 * @param int $id
 * @return object configuration
 */
function urait_get_type_config_from_instance($id) {
    global $DB;

    $instance = $DB->get_record('urait', array('id' => $id));
    $config = lti_get_config($instance);

    $type = new \stdClass();
    $type->lti_fix = $id;

    if (isset($config['toolurl'])) {
        $type->lti_toolurl = $config['toolurl'];
    }

    if (isset($config['instructorchoicesendname'])) {
        $type->lti_sendname = $config['instructorchoicesendname'];
    }

    if (isset($config['instructorchoicesendemailaddr'])) {
        $type->lti_sendemailaddr = $config['instructorchoicesendemailaddr'];
    }

    if (isset($config['instructorchoiceacceptgrades'])) {
        $type->lti_acceptgrades = $config['instructorchoiceacceptgrades'];
    }

    if (isset($config['instructorchoiceallowroster'])) {
        $type->lti_allowroster = $config['instructorchoiceallowroster'];
    }

    if (isset($config['instructorcustomparameters'])) {
        $type->lti_allowsetting = $config['instructorcustomparameters'];
    }

    return $type;
}

function urait_add_type($type, $config) {
    global $USER, $SITE, $DB;

    urait_prepare_type_for_save($type, $config);

    if (!isset($type->state)) {
        $type->state = LTI_TOOL_STATE_PENDING;
    }

    if (!isset($type->ltiversion)) {
        $type->ltiversion = LTI_VERSION_1;
    }

    if (!isset($type->timecreated)) {
        $type->timecreated = time();
    }

    if (!isset($type->createdby)) {
        $type->createdby = $USER->id;
    }

    if (!isset($type->course)) {
        $type->course = $SITE->id;
    }

    // Возвращаем в норму Urait инструмент если его вдруг изменили
    if (isset($config->id)) {
        $id = $config->id;

        $existingtype = $DB->get_record('lti_types', ['id' => $id]);
        $needtoupdate = false;

        foreach ($type as $fieldname => $fieldvalue) {
            if (isset($existingtype->$fieldname) && $fieldvalue != $existingtype->$fieldname) {
                if (in_array($fieldname, ['timemodified', 'timecreated'])) {
                    continue;
                }
                $existingtype->$fieldname = $fieldvalue;
                $needtoupdate = true;
            }
        }

        if ($needtoupdate) {
            $DB->update_record('lti_types', $existingtype);
        }

        // Предварительно достаем конфиги нашего инструмента,
        // которые понадобятся, чтобы сравнить их с дефолтными
        $sql = 'SELECT ltc.* FROM {lti_types_config} ltc
                        JOIN {lti_types} lt ON ltc.typeid = lt.id
                                                         AND lt.name = :ltname';
        $utypeconf = $DB->get_records_sql($sql, ['ltname' => 'Образовательная платформа «Юрайт»']);
        $utypeconfright = [];

        foreach ($utypeconf as $value) {
            $utypeconfright[$value->name] = $value;
        }
    } else {
        // Create a salt value to be used for signing passed data to extension services
        // The outcome service uses the service salt on the instance. This can be used
        // for communication with services not related to a specific LTI instance.
        $config->lti_servicesalt = uniqid('', true);
        $id = $DB->insert_record('lti_types', $type);
    }

    if ($id) {
        foreach ($config as $key => $value) {
            if (!is_null($value)) {
                if (substr($key, 0, 4) === 'lti_') {
                    $fieldname = substr($key, 4);
                } else if (substr($key, 0, 11) !== 'ltiservice_') {
                    continue;
                } else {
                    $fieldname = $key;
                }

                $record = new \StdClass();
                $record->typeid = $id;
                $record->name = $fieldname;
                $record->value = $value;

                $configid = $DB->get_field('lti_types_config', 'id', [
                    'typeid' => $id,
                    'name' => $fieldname
                ]);

                if ($configid) {
                    if ($value != $utypeconfright[$fieldname]->value) {
                        $record->id = $configid;
                        $DB->update_record('lti_types_config', $record);
                    }
                } else {
                    $DB->insert_record('lti_types_config', $record);
                }
            }
        }

        if (isset($config->lti_coursecategories)
            && !empty($config->lti_coursecategories
            && !isset($config->id))
        ) {
            lti_type_add_categories($id, $config->lti_coursecategories);
        }
    }

    return $id;
}

function urait_prepare_type_for_save($type, $config) {
    if (isset($config->lti_toolurl)) {
        $type->baseurl = $config->lti_toolurl;
        if (isset($config->lti_tooldomain)) {
            $type->tooldomain = $config->lti_tooldomain;
        } else {
            $type->tooldomain = lti_get_domain_from_url($config->lti_toolurl);
        }
    }
    if (isset($config->lti_description)) {
        $type->description = $config->lti_description;
    }
    if (isset($config->lti_typename)) {
        $type->name = $config->lti_typename;
    }
    if (isset($config->lti_ltiversion)) {
        $type->ltiversion = $config->lti_ltiversion;
    }
    if (isset($config->lti_clientid)) {
        $type->clientid = $config->lti_clientid;
    }
    if (!isset($config->id)) {
        if ((!empty($type->ltiversion) && $type->ltiversion === LTI_VERSION_1P3) && empty($type->clientid)) {
            $type->clientid = registration_helper::get()->new_clientid();
        } else if (empty($type->clientid)) {
            $type->clientid = null;
        }
    }
    if (isset($config->lti_coursevisible)) {
        $type->coursevisible = $config->lti_coursevisible;
    }

    if (isset($config->lti_icon)) {
        $type->icon = $config->lti_icon;
    }
    if (isset($config->lti_secureicon)) {
        $type->secureicon = $config->lti_secureicon;
    }

    $type->forcessl = !empty($config->lti_forcessl) ? $config->lti_forcessl : 0;
    $config->lti_forcessl = $type->forcessl;
    if (isset($config->lti_contentitem)) {
        $type->contentitem = !empty($config->lti_contentitem) ? $config->lti_contentitem : 0;
        $config->lti_contentitem = $type->contentitem;
    }
    if (isset($config->lti_toolurl_ContentItemSelectionRequest)) {
        if (!empty($config->lti_toolurl_ContentItemSelectionRequest)) {
            $type->toolurl_ContentItemSelectionRequest = $config->lti_toolurl_ContentItemSelectionRequest;
        } else {
            $type->toolurl_ContentItemSelectionRequest = '';
        }
        $config->lti_toolurl_ContentItemSelectionRequest = $type->toolurl_ContentItemSelectionRequest;
    }

    $type->timemodified = time();

    unset ($config->lti_typename);
    unset ($config->lti_toolurl);
    unset ($config->lti_description);
    unset ($config->lti_ltiversion);
    unset ($config->lti_clientid);
    unset ($config->lti_icon);
    unset ($config->lti_secureicon);
}

function urait_update_credentials_relevance() {
    $initiatelogin = URAIT_DEFAULT_INITIATELOGIN;
    $consumerkey = get_config('mod_urait', 'consumerkey');
    $token = get_config('mod_urait', 'token');

    $initiatelogin = str_replace('URAIT_CONSUMER_KEY', $consumerkey, $initiatelogin);
    $initiatelogin = str_replace('URAIT_TOKEN', $token, $initiatelogin);

    return $initiatelogin;
}

/**
* Generates some of the tool configuration based on the admin configuration details
*
 * @param int $id
*
 * @return stdClass Configuration details
*/
function urait_get_type_type_config($id) {
    global $DB;

    $basicltitype = $DB->get_record('lti_types', array('id' => $id));
    $config = lti_get_type_config($id);

    $type = new \stdClass();

    $type->lti_typename = $basicltitype->name;

    $type->typeid = $basicltitype->id;

    $type->course = $basicltitype->course;

    $type->toolproxyid = $basicltitype->toolproxyid;

    $type->lti_toolurl = $basicltitype->baseurl;

    $type->lti_ltiversion = $basicltitype->ltiversion;

    $type->lti_clientid = $basicltitype->clientid;
    $type->lti_clientid_disabled = $type->lti_clientid;

    $type->lti_description = $basicltitype->description;

    $type->lti_parameters = $basicltitype->parameter;

    $type->lti_icon = $basicltitype->icon;

    $type->lti_secureicon = $basicltitype->secureicon;

    if (isset($config['resourcekey'])) {
        $type->lti_resourcekey = $config['resourcekey'];
    }
    if (isset($config['password'])) {
        $type->lti_password = $config['password'];
    }
    if (isset($config['publickey'])) {
        $type->lti_publickey = $config['publickey'];
    }
    if (isset($config['publickeyset'])) {
        $type->lti_publickeyset = $config['publickeyset'];
    }
    if (isset($config['keytype'])) {
        $type->lti_keytype = $config['keytype'];
    }
    if (isset($config['initiatelogin'])) {
        $type->lti_initiatelogin = urait_update_credentials_relevance();
    }
    if (isset($config['redirectionuris'])) {
        $type->lti_redirectionuris = $config['redirectionuris'];
    }

    if (isset($config['sendname'])) {
        $type->lti_sendname = $config['sendname'];
    }
    if (isset($config['instructorchoicesendname'])) {
        $type->lti_instructorchoicesendname = $config['instructorchoicesendname'];
    }
    if (isset($config['sendemailaddr'])) {
        $type->lti_sendemailaddr = $config['sendemailaddr'];
    }
    if (isset($config['instructorchoicesendemailaddr'])) {
        $type->lti_instructorchoicesendemailaddr = $config['instructorchoicesendemailaddr'];
    }
    if (isset($config['acceptgrades'])) {
        $type->lti_acceptgrades = $config['acceptgrades'];
    }
    if (isset($config['instructorchoiceacceptgrades'])) {
        $type->lti_instructorchoiceacceptgrades = $config['instructorchoiceacceptgrades'];
    }
    if (isset($config['allowroster'])) {
        $type->lti_allowroster = $config['allowroster'];
    }
    if (isset($config['instructorchoiceallowroster'])) {
        $type->lti_instructorchoiceallowroster = $config['instructorchoiceallowroster'];
    }

    if (isset($config['customparameters'])) {
        $type->lti_customparameters = $config['customparameters'];
    }

    if (isset($config['forcessl'])) {
        $type->lti_forcessl = $config['forcessl'];
    }

    if (isset($config['organizationid_default'])) {
        $type->lti_organizationid_default = $config['organizationid_default'];
    } else {
        // Tool was configured before this option was available and the default then was host.
        $type->lti_organizationid_default = LTI_DEFAULT_ORGID_SITEHOST;
    }
    if (isset($config['organizationid'])) {
        $type->lti_organizationid = $config['organizationid'];
    }
    if (isset($config['organizationurl'])) {
        $type->lti_organizationurl = $config['organizationurl'];
    }
    if (isset($config['organizationdescr'])) {
        $type->lti_organizationdescr = $config['organizationdescr'];
    }
    if (isset($config['launchcontainer'])) {
        $type->lti_launchcontainer = $config['launchcontainer'];
    }

    if (isset($config['coursevisible'])) {
        $type->lti_coursevisible = $config['coursevisible'];
    }

    if (isset($config['contentitem'])) {
        $type->lti_contentitem = $config['contentitem'];
    }

    if (isset($config['toolurl_ContentItemSelectionRequest'])) {
        $type->lti_toolurl_ContentItemSelectionRequest = $config['toolurl_ContentItemSelectionRequest'];
    }

    if (isset($config['debuglaunch'])) {
        $type->lti_debuglaunch = $config['debuglaunch'];
    }

    if (isset($config['module_class_type'])) {
        $type->lti_module_class_type = $config['module_class_type'];
    }

    // Get the parameters from the LTI services.
    foreach ($config as $name => $value) {
        if (strpos($name, 'ltiservice_') === 0) {
            $type->{$name} = $config[$name];
        }
    }

    return $type;
}

function check_credentials() {
    $consumerkey = get_config('mod_urait', 'consumerkey');
    $token = get_config('mod_urait', 'token');

    if (!$consumerkey || !$token) {
        throw new \moodle_exception('nocredentials', 'mod_urait');
    }
}