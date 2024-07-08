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

require_once('../../config.php');
require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->dirroot.'/mod/lti/lib.php');
require_once($CFG->dirroot.'/mod/lti/locallib.php');
require_once($CFG->dirroot.'/mod/urait/locallib.php');

$id = optional_param('id', 0, PARAM_INT); // Course Module ID, or
$l  = optional_param('l', 0, PARAM_INT);  // urait ID.
$action = optional_param('action', '', PARAM_TEXT);
$foruserid = optional_param('user', 0, PARAM_INT);
$forceview = optional_param('forceview', 0, PARAM_BOOL);

check_credentials();

if ($l) {  // Two ways to specify the module.
    $urait = $DB->get_record('urait', ['id' => $l], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('urait', $l, $urait->course, false, MUST_EXIST);
} else {
    $cm = get_coursemodule_from_id('urait', $id, 0, false, MUST_EXIST);
    $urait = $DB->get_record('urait', ['id' => $cm->instance], '*', MUST_EXIST);
}
$lti = $urait;

$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

$typeid = $lti->typeid;
if (empty($typeid) && ($tool = lti_get_tool_by_url_match($lti->toolurl))) {
    $typeid = $tool->id;
}
if ($typeid) {
    $toolconfig = lti_get_type_config($typeid);
    $missingtooltype = empty($toolconfig);
    if (!$missingtooltype) {
        $toolurl = $toolconfig['toolurl'];
    }
} else {
    $toolconfig = array();
    $toolurl = $lti->toolurl;
}

$PAGE->set_cm($cm, $course); // Set's up global $COURSE.
$context = context_module::instance($cm->id);
$PAGE->set_context($context);

require_login($course, true, $cm);
require_capability('mod/lti:view', $context);

if (!empty($foruserid) && (int)$foruserid !== (int)$USER->id) {
    require_capability('gradereport/grader:view', $context);
}

$url = new moodle_url('/mod/urait/view.php', array('id' => $cm->id));
$PAGE->set_url($url);

if (!empty($missingtooltype)) {
    $PAGE->set_pagelayout('incourse');
    echo $OUTPUT->header();
    throw new moodle_exception('tooltypenotfounderror', 'mod_lti');
}

$launchcontainer = lti_get_launch_container($lti, $toolconfig);

if ($launchcontainer == LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS) {
    $PAGE->set_pagelayout('incourse');
    $PAGE->blocks->show_only_fake_blocks(); // Disable blocks for layouts which do include pre-post blocks.
} else if ($launchcontainer == LTI_LAUNCH_CONTAINER_REPLACE_MOODLE_WINDOW) {
    if (!$forceview) {
        $url = new moodle_url('/mod/urait/launch.php', array('id' => $cm->id));
        redirect($url);
    }
} else { // Handles LTI_LAUNCH_CONTAINER_DEFAULT, LTI_LAUNCH_CONTAINER_EMBED, LTI_LAUNCH_CONTAINER_WINDOW.
    $PAGE->set_pagelayout('incourse');
}

urait_view($urait, $course, $cm, $context);

$pagetitle = strip_tags($course->shortname.': '.format_string($lti->name));
$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);

$activityheader = $PAGE->activityheader;
if (!$lti->showtitlelaunch) {
    $header['title'] = '';
}
if (!$lti->showdescriptionlaunch) {
    $header['description'] = '';
}
$activityheader->set_attrs($header ?? []);

// Print the page header.
echo $OUTPUT->header();

if ($typeid) {
    $config = urait_get_type_type_config($typeid);
} else {
    $config = new stdClass();
    $config->lti_ltiversion = LTI_VERSION_1;
}
$launchurl = new moodle_url('/mod/urait/launch.php', ['id' => $cm->id, 'triggerview' => 0]);
if ($action) {
    $launchurl->param('action', $action);;
}
if ($foruserid) {
    $launchurl->param('user', $foruserid);;
}
unset($SESSION->lti_initiatelogin_status);
if (($launchcontainer == LTI_LAUNCH_CONTAINER_WINDOW)) {
    if (!$forceview) {
        echo "<script language=\"javascript\">//<![CDATA[\n";
        echo "window.open('{$launchurl->out(true)}','lti-$cm->id');";
        echo "//]]\n";
        echo "</script>\n";
        echo "<p>".get_string("basiclti_in_new_window", "lti")."</p>\n";
    }
    echo html_writer::start_tag('p');
    echo html_writer::link($launchurl->out(false), get_string("basiclti_in_new_window_open", "lti"), array('target' => '_blank'));
    echo html_writer::end_tag('p');
} else {
    $content = '';
    // Build the allowed URL, since we know what it will be from $lti->toolurl,
    // If the specified toolurl is invalid the iframe won't load, but we still want to avoid parse related errors here.
    // So we set an empty default allowed url, and only build a real one if the parse is successful.
    $ltiallow = '';
    $urlparts = parse_url($toolurl);
    if ($urlparts && array_key_exists('scheme', $urlparts) && array_key_exists('host', $urlparts)) {
        $ltiallow = $urlparts['scheme'] . '://' . $urlparts['host'];
        // If a port has been specified we append that too.
        if (array_key_exists('port', $urlparts)) {
            $ltiallow .= ':' . $urlparts['port'];
        }
    }

    // Request the launch content with an iframe tag.
    $attributes = [];
    $attributes['id'] = "contentframe";
    $attributes['height'] = '600px';
    $attributes['width'] = '100%';
    $attributes['src'] = $launchurl;
    $attributes['allow'] = "microphone $ltiallow; " .
        "camera $ltiallow; " .
        "geolocation $ltiallow; " .
        "midi $ltiallow; " .
        "encrypted-media $ltiallow; " .
        "autoplay $ltiallow";
    $attributes['allowfullscreen'] = 1;
    $iframehtml = html_writer::tag('iframe', $content, $attributes);
    echo $iframehtml;

    // Output script to make the iframe tag be as large as possible.
    $resize = '
        <script type="text/javascript">
        //<![CDATA[
            YUI().use("node", "event", function(Y) {
                var doc = Y.one("body");
                var frame = Y.one("#contentframe");
                var padding = 15; //The bottom of the iframe wasn\'t visible on some themes. Probably because of border widths, etc.
                var lastHeight;
                var resize = function(e) {
                    var viewportHeight = doc.get("winHeight");
                    if(lastHeight !== Math.min(doc.get("docHeight"), viewportHeight)){
                        frame.setStyle("height", viewportHeight - frame.getY() - padding + "px");
                        lastHeight = Math.min(doc.get("docHeight"), doc.get("winHeight"));
                    }
                };

                resize();

                Y.on("windowresize", resize);
            });
        //]]
        </script>
';

    echo $resize;
}

// Finish the page.
echo $OUTPUT->footer();
