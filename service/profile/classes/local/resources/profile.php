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
 * This file contains a class definition for the Tool Consumer Profile resource
 *
 * @package uraitservice_profile
 * @copyright 2024 LMS-Service {@link https://lms-service.ru/}
 * @author Daniil Romanov
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace uraitservice_profile\local\resources;

use \mod_urait\local\uraitservice\service_base;

defined('MOODLE_INTERNAL') || die();

/**
 * Class profile
 * @package uraitservice_profile\local\resources
 */
class profile extends \mod_urait\local\uraitservice\resource_base {

    /**
     * Class constructor.
     *
     * @param service_base $service Service instance
     */
    public function __construct($service) {
        parent::__construct($service);
        $this->id = 'ToolConsumerProfile';
        $this->template = '/profile/{tool_proxy_id}';
        $this->variables[] = 'ToolConsumerProfile.url';
        $this->formats[] = 'application/vnd.ims.lti.v2.toolconsumerprofile+json';
        $this->methods[] = 'GET';
    }

    /**
     * Get the path for this resource.
     *
     * @return string
     */
    public function get_path() {

        $path = $this->template;
        $toolproxy = $this->get_service()->get_tool_proxy();
        if (!empty($toolproxy)) {
            $path = str_replace('{tool_proxy_id}', $toolproxy->guid, $path);
        }

        return $path;

    }

    /**
     * Execute the request for this resource.
     *
     * @param \mod_lti\local\ltiservice\response $response  Response object for this request.
     */
    public function execute($response) {
        global $CFG;

        $version = service_base::LTI_VERSION2P0;
        $params = $this->parse_template();
        if (optional_param('lti_version', service_base::LTI_VERSION2P0, PARAM_ALPHANUMEXT) != $version) {
            $ok = false;
            $response->set_code(400);
        } else {
            $toolproxy = lti_get_tool_proxy_from_guid($params['tool_proxy_id']);
            $ok = $toolproxy !== false;
        }
        if ($ok) {
            $this->get_service()->set_tool_proxy($toolproxy);
            $response->set_content_type($this->formats[0]);

            $servicepath = (new \moodle_url('/mod/urait/services.php'))->out(false);
            $id = $servicepath . $this->get_path();
            $now = date('Y-m-d\TH:iO');
            $capabilityofferedarr = explode("\n", $toolproxy->capabilityoffered);
            $serviceofferedarr = explode("\n", $toolproxy->serviceoffered);
            $serviceoffered = '';
            $sep = '';
            $services = \core_component::get_plugin_list('uraitservice');
            foreach ($services as $name => $location) {
                if (in_array($name, $serviceofferedarr)) {
                    $classname = "\\uraitservice_{$name}\\local\\service\\{$name}";
                    /** @var service_base $service */
                    $service = new $classname();
                    $service->set_tool_proxy($toolproxy);
                    $resources = $service->get_resources();
                    foreach ($resources as $resource) {
                        $formats = implode("\", \"", $resource->get_formats());
                        $methods = implode("\", \"", $resource->get_methods());
                        $capabilityofferedarr = array_merge($capabilityofferedarr, $resource->get_variables());
                        $template = $resource->get_path();
                        if (!empty($template)) {
                            $path = $servicepath . preg_replace('/[\(\)]/', '', $template);
                        } else {
                            $path = $resource->get_endpoint();
                        }
                        $serviceoffered .= <<< EOD
{$sep}
    {
      "@type":"{$resource->get_type()}",
      "@id":"tcp:{$resource->get_id()}",
      "endpoint":"{$path}",
      "format":["{$formats}"],
      "action":["{$methods}"]
    }
EOD;
                        $sep = ',';
                    }
                }
            }
            $capabilityoffered = implode("\",\n    \"", $capabilityofferedarr);
            if (strlen($capabilityoffered) > 0) {
                $capabilityoffered = "\n    \"{$capabilityoffered}\"";
            }
            $urlparts = parse_url($CFG->wwwroot);
            $orgid = $urlparts['host'];
            $name = 'Moodle';
            $code = 'moodle';
            $vendorname = 'Moodle.org';
            $vendorcode = 'mdl';
            $prodversion = strval($CFG->version);
            if (!empty($CFG->mod_lti_institution_name)) {
                $consumername = $CFG->mod_lti_institution_name;
                $consumerdesc = '';
            } else {
                $consumername = get_site()->fullname;
                $consumerdesc = strip_tags(get_site()->summary);
            }
            $profile = <<< EOD
{
  "@context":[
    "http://purl.imsglobal.org/ctx/lti/v2/ToolConsumerProfile",
    {
      "tcp":"{$id}#"
    }
  ],
  "@type":"ToolConsumerProfile",
  "@id":"{$id}",
  "lti_version":"{$version}",
  "guid":"{$toolproxy->guid}",
  "product_instance":{
    "guid":"{$orgid}",
    "product_info":{
      "product_name":{
        "default_value":"{$name}",
        "key":"product.name"
      },
      "product_version":"{$prodversion}",
      "product_family":{
        "code":"{$code}",
        "vendor":{
          "code":"{$vendorcode}",
          "vendor_name":{
            "default_value":"{$vendorname}",
            "key":"product.vendor.name"
          },
          "timestamp":"{$now}"
        }
      }
    },
    "service_owner":{
      "@id":"ServiceOwner",
      "service_owner_name":{
        "default_value":"{$consumername}",
        "key":"service_owner.name"
      },
      "description":{
        "default_value":"{$consumerdesc}",
        "key":"service_owner.description"
      }
    }
  },
  "capability_offered":[{$capabilityoffered}
  ],
  "service_offered":[{$serviceoffered}
  ]
}
EOD;
            $response->set_body($profile);

        }
    }

    /**
     * Parse a value for custom parameter substitution variables.
     *
     * @param string $value String to be parsed
     *
     * @return string
     */
    public function parse_value($value) {
        if (!empty($this->get_service()->get_tool_proxy()) && (strpos($value, '$ToolConsumerProfile.url') !== false)) {
            $value = str_replace('$ToolConsumerProfile.url', $this->get_endpoint(), $value);
        }
        return $value;

    }

}
