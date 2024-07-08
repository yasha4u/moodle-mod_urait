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

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/lti/locallib.php');
require_once($CFG->dirroot.'/mod/urait/locallib.php');

class mod_urait_mod_form extends moodleform_mod {

    /** @var int the tool typeid, or 0 if the instance form is being created for a manually configured tool instance.*/
    protected int $typeid;

    /** @var string|null type */
    protected ?string $type;

    /**
     * Constructor.
     *
     * Throws an exception if trying to init the form for a new manual instance of a tool, which is not supported in 4.3 onward.
     *
     * @param \stdClass $current the current form data.
     * @param string $section the section number.
     * @param \stdClass $cm the course module object.
     * @param \stdClass $course the course object.
     * @throws moodle_exception if trying to init the form for the creation of a manual instance, which is no longer supported.
     */
    public function __construct($current, $section, $cm, $course) {
        check_credentials();

        // Setup some of the pieces used to control display in the form definition() method.
        // Type ID parameter being passed when adding an preconfigured tool from activity chooser.
        $this->typeid = optional_param('typeid', 0, PARAM_INT);
        $this->type = optional_param('type', null, PARAM_ALPHA);

        parent::__construct($current, $section, $cm, $course);
    }

    public function definition() {
        global $PAGE, $OUTPUT, $COURSE, $DB;

        if ($this->type) {
            component_callback("ltisource_$this->type", 'add_instance_hook');
        }

        // Determine whether this tool instance is a manually configure instance (now deprecated).
        $manualinstance = empty($this->current->typeid) && empty($this->typeid);

        // Determine whether this tool instance is using a domain-matched site tool which is not visible at the course level.
        // In such a case, the instance has a typeid (the site tool) and toolurl (the url used to domain match the site tool) set,
        // and the type still exists (is not deleted).
        $instancetypes = lti_get_types_for_add_instance();
        $matchestoolnotavailabletocourse = false;
        if (!$manualinstance && !empty($this->current->toolurl)) {
            if (lti_get_type_config($this->current->typeid)) {
                $matchestoolnotavailabletocourse = !in_array($this->current->typeid, array_keys($instancetypes));
            }
        }

        $tooltypeid = $DB->get_field('lti_types', 'id', ['name' => 'Образовательная платформа «Юрайт»']);
        $tooltype = lti_get_type($tooltypeid);

        // Store the id of the tool type should it be linked to a tool proxy, to aid in disabling certain form elements.
        $toolproxytypeid = $tooltype->toolproxyid ? $tooltypeid : '';

        $issitetooltype = $tooltype->course == get_site()->id;

        $mform =& $this->_form;

        // Adding the "general" fieldset, where all the common settings are shown.
        $mform->addElement('html', "<div data-attribute='dynamic-import' hidden aria-hidden='true' role='alert'></div>");
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // For tools supporting content selection, add the 'Select content button'.
        $config = lti_get_type_config($tooltypeid);
        $supportscontentitemselection = !empty($config['contentitem']);

        if ($supportscontentitemselection) {
            $contentitemurl = new moodle_url('/mod/urait/contentitem.php');
            $contentbuttonattributes = [
                'data-contentitemurl' => $contentitemurl->out(false),
            ];

            // If this is an instance, was it created based on content selection in a prior-edit (need to infer since not stored).
            $iscontentitem = !empty($this->current->id)
                && (!empty($this->current->toolurl) || !empty($this->current->instructorcustomparameters)
                    || !empty($this->current->secureicon) || !empty($this->current->icon));

            $selectcontentindicatorinner = $iscontentitem ?
                $OUTPUT->pix_icon('i/valid', get_string('contentselected', 'mod_lti'), 'moodle', ['class' => 'mr-1'])
                . get_string('contentselected', 'mod_lti') : '';
            $selectcontentindicator = html_writer::div($selectcontentindicatorinner, '',
                ['aria-role' => 'status', 'id' => 'id_selectcontentindicator']);
            $selectcontentstatus = $iscontentitem ? 'true' : 'false';
            $selectcontentgrp = [
                $mform->createElement('button', 'selectcontent', get_string('selectcontent', 'mod_lti'), $contentbuttonattributes,
                    ['customclassoverride' => 'btn-primary']),
                $mform->createElement('html', $selectcontentindicator),
                $mform->createElement('hidden', 'selectcontentstatus', $selectcontentstatus),
            ];
            $mform->setType('selectcontentstatus', PARAM_TEXT);
            $mform->addGroup($selectcontentgrp, 'selectcontentgroup', get_string('content'), ' ', false);
            $mform->addRule('selectcontentgroup', get_string('selectcontentvalidationerror', 'mod_lti'), 'required');
        }

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('basicltiname', 'lti'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'server');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'server');

        // Include in the form anyway, so we retain the setting value in case the tool launch container is changed back.
        $mform->addElement('hidden', 'showtitlelaunch');
        $mform->setType('showtitlelaunch', PARAM_BOOL);

        // Adding the optional "intro" and "introformat" pair of fields.
        $this->standard_intro_elements(get_string('basicltiintro', 'lti'));

        // Display the label to the right of the checkbox so it looks better & matches rest of the form.
        if ($mform->elementExists('showdescription')) {
            $coursedesc = $mform->getElement('showdescription');
            if (!empty($coursedesc)) {
                $coursedesc->setText(' ' . $coursedesc->getLabel());
                $coursedesc->setLabel('&nbsp');
            }
        }

        // Show activity description when launched only applies to embedded type launches.
        if (in_array($config['launchcontainer'], [LTI_LAUNCH_CONTAINER_EMBED, LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS])) {
            $mform->addElement('checkbox', 'showdescriptionlaunch', get_string('display_description', 'lti'));
            $mform->addHelpButton('showdescriptionlaunch', 'display_description', 'lti');
        } else {
            // Include in the form anyway, so we retain the setting value in case the tool launch container is changed back.
            $mform->addElement('hidden', 'showdescriptionlaunch');
            $mform->setType('showdescriptionlaunch', PARAM_BOOL);
        }

        // Tool settings.
        $mform->addElement('hidden', 'typeid', $tooltypeid, ['id' => 'hidden_typeid']);
        $mform->setType('typeid', PARAM_INT);
        if (!empty($config['contentitem'])) {
            $mform->addElement('hidden', 'contentitem', 1);
            $mform->setType('contentitem', PARAM_INT);
        }

        // Included to support deep linking return, but hidden to avoid instructor modification.
        $mform->addElement('text', 'toolurl', get_string('toolurl', 'urait'), ['id' => 'id_toolurl', 'size' => '64']);
        $mform->setType('toolurl', PARAM_URL);
        $mform->addHelpButton('toolurl', 'toolurl', 'urait');
        $mform->addRule('toolurl', null, 'required', null, 'server');

        $mform->addElement('hidden', 'securetoolurl', '', ['id' => 'id_securetoolurl']);
        $mform->setType('securetoolurl', PARAM_URL);

        $mform->addElement('hidden', 'urlmatchedtypeid', '', ['id' => 'id_urlmatchedtypeid']);
        $mform->setType('urlmatchedtypeid', PARAM_INT);

        $mform->addElement('hidden', 'lineitemresourceid', '', ['id' => 'id_lineitemresourceid']);
        $mform->setType('lineitemresourceid', PARAM_TEXT);

        $mform->addElement('hidden', 'lineitemtag', '', ['id' => 'id_lineitemtag']);
        $mform->setType('lineitemtag', PARAM_TEXT);

        $mform->addElement('hidden', 'lineitemsubreviewurl', '', ['id' => 'id_lineitemsubreviewurl']);
        $mform->setType('lineitemsubreviewurl', PARAM_URL);

        $mform->addElement('hidden', 'lineitemsubreviewparams', '', ['id' => 'id_lineitemsubreviewparams']);
        $mform->setType('lineitemsubreviewparams', PARAM_TEXT);

        // Launch container is set to 'LTI_LAUNCH_CONTAINER_DEFAULT', meaning it'll delegate to the tool's configuration.
        // Existing instances using values other than this can continue to use their existing value but cannot change it.
        $mform->addElement('hidden', 'launchcontainer', LTI_LAUNCH_CONTAINER_DEFAULT);
        $mform->setType('launchcontainer', PARAM_INT);

        // Included to support deep linking return, but hidden to avoid instructor modification.
        $mform->addElement('hidden', 'resourcekey', '', ['id' => 'id_resourcekey']);
        $mform->setType('resourcekey', PARAM_TEXT);
        $mform->addElement('hidden', 'password', '', ['id' => 'id_password']);
        $mform->setType('password', PARAM_TEXT);
        $mform->addElement('hidden', 'instructorcustomparameters', '', ['id' => 'id_instructorcustomparameters']);
        $mform->setType('instructorcustomparameters', PARAM_TEXT);
        $mform->addElement('hidden', 'icon', '', ['id' => 'id_icon']);
        $mform->setType('icon', PARAM_URL);
        $mform->addElement('hidden', 'secureicon', '', ['id' => 'id_secureicon']);
        $mform->setType('secureicon', PARAM_URL);

        // Add standard course module grading elements, and show them if the tool type + instance config permits it.
        if (!empty($config['acceptgrades']) && in_array($config['acceptgrades'], [LTI_SETTING_ALWAYS, LTI_SETTING_DELEGATE])) {
            $elementnamesbeforegrading = $this->_form->_elementIndex;
            $this->standard_grading_coursemodule_elements();
            $elementnamesaftergrading = $this->_form->_elementIndex;

            // For all 'real' elements (not hidden or header) added as part of the standard grading elements, add a hideIf rule
            // making the element dependent on the 'accept grades from the tool' checkbox (instructorchoiceacceptgrades).
            $diff = array_diff($elementnamesaftergrading, $elementnamesbeforegrading);
            $diff = array_filter($diff, fn($key) => !in_array($this->_form->_elements[$key]->_type, ['hidden', 'header']));
            foreach ($diff as $gradeelementname => $gradeelementindex) {
                $mform->hideIf($gradeelementname, 'instructorchoiceacceptgrades', 'eq', 0);
            }

            // Extend the grade section with the 'accept grades from the tool' checkbox, allowing per-instance overrides of that
            // value according to the following rules:
            // - Site tools with 'acceptgrades' set to 'ALWAYS' do not permit overrides at the instance level; the checkbox is
            // omitted in such cases.
            // - Site tools with 'acceptgrades' set to 'DELEGATE' result in a checkbox that is defaulted to unchecked but which
            // permits overrides to 'yes/checked'.
            // - Course tools with 'acceptgrades' set to 'ALWAYS' result in a checkbox that is defaulted to checked but which
            // permits overrides to 'no/unchecked'.
            // - Course tools with 'acceptgrades' set to 'DELEGATE' result in a checkbox that is defaulted to unchecked but which
            // permits overrides to 'yes/checked'.
            $mform->insertElementBefore(
                $mform->createElement(
                    'advcheckbox',
                    'instructorchoiceacceptgrades',
                    get_string('accept_grades_from_tool', 'mod_urait', $tooltype->name)
                ),
                array_keys($diff)[0]
            );
            $acceptgradesdefault = !$issitetooltype && $config['acceptgrades'] == LTI_SETTING_ALWAYS ? '1' : '0';
            $mform->setDefault('instructorchoiceacceptgrades', $acceptgradesdefault);
            $mform->disabledIf('instructorchoiceacceptgrades', 'typeid', 'in', [$toolproxytypeid]); // LTI 2 only.
        }

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();
        $mform->setAdvanced('cmidnumber');

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();

        if ($supportscontentitemselection) {
            $PAGE->requires->js_call_amd('mod_lti/mod_form', 'init', [$COURSE->id]);
        }
    }

    /**
     * Sets the current values handled by services in case of update.
     *
     * @param object $defaultvalues default values to populate the form with.
     */
    public function set_data($defaultvalues) {
        $services = urait_get_services();
        if (is_object($defaultvalues)) {
            foreach ($services as $service) {
                $service->set_instance_form_values( $defaultvalues );
            }
        }
        parent::set_data($defaultvalues);
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (isset($data['selectcontentstatus']) && $data['selectcontentstatus'] === 'false') {
            $errors['selectcontentgroup'] = get_string('selectcontentvalidationerror', 'mod_lti');
        }

        return $errors;
    }
}
