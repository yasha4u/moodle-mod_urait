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
 * @package mod_ltiurls
 * @copyright 2024 LMS-Service {@link https://lms-service.ru/}
 * @author Daniil Romanov
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['pluginname'] = 'ЮРАЙТ';
$string['modulename'] = 'ЮРАЙТ';

$string['modulename_help'] = "
Модуль Юрайт позволяет добавить материалы Образовательной платформы Юрайт, 
как элемент курса Moodle.

<p><strong>Особенности модуля Юрайт:</strong></p>

1. Бесшовный доступ к курсам, книгам, тeстам и экзаменам <br>
2. Оценки за тeсты автоматически отправляются в Moodle
";

$string['subplugintype_urait'] = 'ЮРАЙТ';
$string['subplugintype_uraitsource_plural'] = 'ЮРАЙТ';
$string['subplugintype_uraitservice_plural'] = 'ЮРАЙТ';

$string['urait:addinstance'] = 'Добавить новый внешний инструмент';
$string['urait:view'] = 'Запустить внешний инструмент';
$string['modulenameplural'] = 'Инстурумент Юрайт';
$string['custom'] = 'Пользовательские параметры';
$string['custom_help'] = 'Пользовательские параметры настройки, используемые поставляемым инструментом. 
Например, пользовательский параметр может быть использован для отображения определенного поставляемого ресурса. 
Каждый параметр должен быть введен на отдельной строке с использованием формата «name=value» («имя=значение»); например, «глава=3». 

Можно безопасно оставить это поле без изменений, если нет указаний поставщика инструмента.';
$string['custominstr'] = 'Пользовательские параметры';
$string['pluginadministration'] = 'Управление внешним инструментом';

$string['consumerkey'] = 'Ключ клиента';
$string['token'] = 'Секретный ключ';

$string['uraitsetinitiatelogin'] = 'Установка реквизитов для внешнего инструмета Urait';
$string['nocredentials'] = 'Должны быть установлены реквизиты consumerkey и token';
$string['modulenamepluralformatted'] = 'Экземпляры плагина Юрайт';

$string['accept_grades_from_tool'] = 'Разрешить элементу {$a} добавлять оценки в журнал оценок';
$string['tooltypenotfounderror'] = "Инструмент URAIT, используемый в этом элементе, удален. За помощью обратитесь к своему учителю или администратору сайта. ";

$string['toolurl'] = 'URL-адрес материала «Юрайт»';
$string['toolurl_help'] = "
Ссылка на материал, скопированная на Образовательной платформе Юрайт <br>
Подробнее: <a href='https://urait.ru/help/professor_help#LMS_Moodle'>
https://urait.ru/help/professor_help#LMS_Moodle</a>

<h5>Общая ссылка на платформу Юрайт:</h5>
<strong>https://urait.ru/</strong> - переход на каталог курсов и книг  из подписки

<h5>Ссылки на курс или книгу:</h5>
<strong>https://urait.ru/book/{идентификатор книги}</strong> - переход к материалам книги<br>

Например,<br>
https://urait.ru/book/istoriya-rossii-dlya-tehnicheskih-specialnostey-451084

<strong>https://urait.ru/author-course/{идентификатор курса}</strong> - переход к материалам курса<br>

Например,<br>
https://urait.ru/author-course/abilitacionnaya-pedagogika-540090

<strong>https://urait.ru/viewer/{идентификатор курса или книги}</strong> - переход к материалам курса или книги<br>

Например,<br>
https://urait.ru/viewer/istoriya-rossii-dlya-tehnicheskih-specialnostey-451084

<strong>https://urait.ru/viewer/{идентификатор курса или книги}#page/{номер страницы}</strong> - переход на указанную страницу курса или книги<br>

Например,<br>
https://urait.ru/viewer/istoriya-rossii-dlya-tehnicheskih-specialnostey-451084#page/10<

<h5>Ссылки на тест:</h5>
<strong>https://urait.ru/quiz/run-test/{идентификатор теста}</strong> - переход к тесту

Например,<br>
https://urait.ru/quiz/run-test/ED178826-CE96-44C5-86B5-98D6F71FE8D7/AE729F21-24FB-453F-81C9-1468F0EEDAC6/C14FF033-D806-4CB8-865E-97A7AC108495

<h5>Ссылки на экзамен:</h5>
<strong>https://urait.ru/quiz/join-exam/{идентификатор экзамена}</strong> - переход к экзамену<br>

Например,<br> 
https://urait.ru/quiz/join-exam/16142

<h5>Ссылки на Входной тест</h5>
<strong>https://urait.ru/input-quiz/run-test/{идентификатор теста}</strong> - переход к входному тесту<br>

Например,<br> 
https://urait.ru/input-quiz/run-test/C77D23B3-1613-45E7-85F2-6ED894D289AA?type=1

<h5>Ссылки на Тотальный экзамен</h5>
<strong>https://urait.ru/quality-quiz/run-test/{идентификатор экзамена}</strong> - переход к тотальному экзамену<br>

Например,<br>
<a href='https://urait.ru/quality-quiz/run-test/9CA99EAC-E3C3-4074-B5C0-1CC0384726B1/2125/7990?ready=1'>
https://urait.ru/quality-quiz/run-test/9CA99EAC-E3C3-4074-B5C0-1CC0384726B1/2125/7990?ready=1</a>
";
