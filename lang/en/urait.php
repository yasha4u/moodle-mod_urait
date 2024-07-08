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
//
// This file is part of BasicLTI4Moodle
//
// BasicLTI4Moodle is an IMS BasicLTI (Basic Learning Tools for Interoperability)
// consumer for Moodle 1.9 and Moodle 2.0. BasicLTI is a IMS Standard that allows web
// based learning tools to be easily integrated in LMS as native ones. The IMS BasicLTI
// specification is part of the IMS standard Common Cartridge 1.1 Sakai and other main LMS
// are already supporting or going to support BasicLTI. This project Implements the consumer
// for Moodle. Moodle is a Free Open source Learning Management System by Martin Dougiamas.
// BasicLTI4Moodle is a project iniciated and leaded by Ludo(Marc Alier) and Jordi Piguillem
// at the GESSI research group at UPC.
// SimpleLTI consumer for Moodle is an implementation of the early specification of LTI
// by Charles Severance (Dr Chuck) htp://dr-chuck.com , developed by Jordi Piguillem in a
// Google Summer of Code 2008 project co-mentored by Charles Severance and Marc Alier.
//
// BasicLTI4Moodle is copyright 2009 by Marc Alier Forment, Jordi Piguillem and Nikolas Galanis
// of the Universitat Politecnica de Catalunya http://www.upc.edu
// Contact info: Marc Alier Forment granludo @ gmail.com or marc.alier @ upc.edu.

/**
 * @package mod_urait
 * @copyright 2024 LMS-Service {@link https://lms-service.ru/}
 * @author Daniil Romanov
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['pluginname'] = 'URAIT';
$string['modulename'] = 'URAIT';

$string['modulename_help'] = "
Urait module Allows you to add materials.
Educational platform Urait,
as part of a Moodle course.

<p><strong>Features of the Urait module:</strong></p>

1. Seamless access to courses, textbooks, tests and exams <br>
2. Test scores are automatically sent to Moodle.
";

$string['subplugintype_urait'] = 'URAIT';
$string['subplugintype_uraitsource_plural'] = 'URAIT';
$string['subplugintype_uraitservice_plural'] = 'URAIT';

$string['urait:addinstance'] = 'Add a new external tool';
$string['urait:view'] = 'Launch external tool activities';
$string['modulenameplural'] = 'Urait tools';
$string['custom'] = 'Custom parameters';
$string['custom_help'] = 'Custom parameters are settings used by the tool provider. For example, a custom parameter may be used to display
a specific resource from the provider.  Each parameter should be entered on a separate line using a format of "name=value"; for example, "chapter=3".

It is safe to leave this field unchanged unless directed by the tool provider.';
$string['custominstr'] = 'Custom parameters';
$string['pluginadministration'] = 'External tool administration';

$string['consumerkey'] = 'Consumer key';
$string['token'] = 'Token';

$string['uraitsetinitiatelogin'] = 'Installation of credentials for the Urait external tool';
$string['nocredentials'] = 'Credentials customerkey and token has to be sat';
$string['modulenamepluralformatted'] = 'Instances of the Urait plugin';

$string['accept_grades_from_tool'] = 'Allow {$a} to add grades in the gradebook';
$string['tooltypenotfounderror'] = "The URAIT tool used in this activity has been deleted. If you need help, contact your teacher or site administrator.";

$string['toolurl'] = 'URL of the material "Urait"';
$string['toolurl_help'] = "Link to material copied on the Educational platform Urait <br>
More details: <a href='https://urait.ru/help/professor_help#LMS_Moodle'>
https://urait.ru/help/professor_help#LMS_Moodle</a>

<h5>General link to the Urait platform:</h5>
<strong>https://urait.ru/</strong> - transition to the catalog of courses and books from the subscription

<h5>Links to course or book:</h5>
<strong>https://urait.ru/book/{book identifier}</strong> - transition to book materials<br>

For example,<br>
https://urait.ru/book/istoriya-rossii-dlya-tehnicheskih-specialnostey-451084

<strong>https://urait.ru/author-course/{course identifier}</strong> - transition to course materials<br>

For example,<br>
https://urait.ru/author-course/abilitacionnaya-pedagogika-540090

<strong>https://urait.ru/viewer/{course or book identifier}</strong> - transition to course or book materials<br>

For example,<br>
https://urait.ru/viewer/istoriya-rossii-dlya-tehnicheskih-specialnostey-451084

<strong>https://urait.ru/viewer/{course or book identifier}#page/{page number}</strong> - go to the specified page of the course or book<br>

For example,<br>
https://urait.ru/viewer/istoriya-rossii-dlya-tehnicheskih-specialnostey-451084#page/10<

<h5>Test links:</h5>
<strong>https://urait.ru/quiz/run-test/{test identifier}</strong> - go to the test

For example,<br>
https://urait.ru/quiz/run-test/ED178826-CE96-44C5-86B5-98D6F71FE8D7/AE729F21-24FB-453F-81C9-1468F0EEDAC6/C14FF033-D806-4CB8-865E-97A7AC108495

<h5>Links to the exam:</h5>
<strong>https://urait.ru/quiz/join-exam/{exam ID}</strong> - go to the exam<br>

For example,<br>
https://urait.ru/quiz/join-exam/16142

<h5>Links to Entry Test</h5>
<strong>https://urait.ru/input-quiz/run-test/{test identifier}</strong> - go to the input test<br>

For example,<br>
https://urait.ru/input-quiz/run-test/C77D23B3-1613-45E7-85F2-6ED894D289AA?type=1

<h5>Links to Total Exam</h5>
<strong>https://urait.ru/quality-quiz/run-test/{exam ID}</strong> - transition to the total exam<br>

For example,<br>
<a href='https://urait.ru/quality-quiz/run-test/9CA99EAC-E3C3-4074-B5C0-1CC0384726B1/2125/7990?ready=1'>
https://urait.ru/quality-quiz/run-test/9CA99EAC-E3C3-4074-B5C0-1CC0384726B1/2125/7990?ready=1</a>
";