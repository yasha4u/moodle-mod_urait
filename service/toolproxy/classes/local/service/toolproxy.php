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
 * This file contains a class definition for the Tool Proxy service
 *
 * @package uraitservice_toolproxy
 * @copyright 2024 LMS-Service {@link https://lms-service.ru/}
 * @author Daniil Romanov
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace uraitservice_toolproxy\local\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Class toolproxy
 * @package uraitservice_toolproxy\local\service
 */
class toolproxy extends \mod_urait\local\uraitservice\service_base {

    /**
     * Class constructor.
     */
    public function __construct() {
        parent::__construct();
        $this->id = 'toolproxy';
        $this->name = 'Tool Proxy';
    }

    /**
     * Get the resources for this service.
     *
     * @return array
     */
    public function get_resources() {
        if (empty($this->resources)) {
            $this->resources = array();
            $this->resources[] = new \uraitservice_toolproxy\local\resources\toolproxy($this);
        }
        return $this->resources;
    }
}
