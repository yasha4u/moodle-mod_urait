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

namespace uraitservice_basicoutcomes\local\resources;

defined('MOODLE_INTERNAL') || die();

use mod_urait\local\uraitservice\resource_base;
use mod_urait\local\uraitservice\service_base;
use mod_lti\local\ltiservice\response;

/**
 * A resource implementing the Basic Outcomes service.
 *
 * Class basicoutcomes
 * @package ltiservice_basicoutcomes\local\resources
 */
class basicoutcomes extends resource_base {

    /**
     * Class constructor.
     *
     * @param service_base $service Service instance
     */
    public function __construct($service) {
        parent::__construct($service);
        $this->id = 'Outcomes.LTI1';
        $this->template = '';
        $this->formats[] = 'application/vnd.ims.lti.v1.outcome+xml';
        $this->methods[] = 'POST';
    }

    /**
     * Get the resource fully qualified endpoint.
     *
     * @return string
     */
    public function get_endpoint() {
        $url = new \moodle_url('/mod/urait/service.php');
        return $url->out(false);
    }

    /**
     * Execute the request for this resource.
     *
     * @param response $response  Response object for this request.
     */
    public function execute($response) {
        // Should never be called as the endpoint sends requests to the LTI 1 service endpoint.
    }

}
