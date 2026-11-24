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
 * Admin page to manage the rules that assign imported course links to categories (one list of rules per ECS).
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../../config.php');

global $CFG, $PAGE, $OUTPUT;

require_once($CFG->libdir.'/adminlib.php');
require_once("$CFG->libdir/formslib.php");

$PAGE->set_url(new moodle_url('/local/campusconnect/admin/categoryassignment.php'));
admin_externalpage_setup('campusconnectcategoryassignment');

require_login();
require_capability('moodle/site:config', context_system::instance());

/**
 * Class to handle category form on settings page for campus connect
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class campusconnect_category_form extends moodleform {

    /**
     * Form definition
     *
     * @return void
     *
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'categoryassignment', get_string('categoryassignment', 'local_campusconnect'));

        $cats = [];
        $categories = \core_course_category::get(0)->get_children();
        foreach ($categories as $category) {
            $cats[$category->id] = $category->name;
        }
        $mform->addElement('select', 'importcat', get_string('importcat', 'local_campusconnect'), $cats);

        $mform->addElement('select', 'attributename', get_string('attributename', 'local_campusconnect'), [
            '1' => 'Community', '2' => 'Participant ID',
        ]);

        $radioarray = [];
        $radioarray[] = $mform->createElement('radio', 'cc_mapping', '', get_string('fixedvalue', 'local_campusconnect'),
                                                'mapping_fixed', 'onclick=cc_switch_mapping_fixed()');
        $radioarray[] = $mform->createElement('radio', 'cc_mapping', '', get_string('daterange', 'local_campusconnect'),
                                                'mapping_date', 'onclick=cc_switch_mapping_date()');
        $mform->addGroup($radioarray, 'radioar', get_string('mappingtype', 'local_campusconnect'), ' ', false);
        $mform->setDefault('cc_mapping', 'mapping_fixed');

        $mform->addElement('text', 'attribute', get_string('attribute', 'local_campusconnect'));
        $mform->setType('crstitle', PARAM_TEXT);

        $mform->addElement('date_selector', 'daterangefrom', get_string('fromdate'));
        $mform->addElement('date_selector', 'daterangeto', get_string('todate'));

        $mform->disabledIf('attribute', 'cc_mapping', 'eq', 'mapping_date');
        $mform->disabledIf('daterangefrom', 'cc_mapping', 'eq', 'mapping_fixed');;
        $mform->disabledIf('daterangeto', 'cc_mapping', 'eq', 'mapping_fixed');

        $this->add_action_buttons();
    }
}

$ecslist = ecssettings::list_ecs(false);
if (!isset($ecslist[$ecsid])) {
    $ecsid = (int)array_key_first($ecslist);
}

if ($mform->is_cancelled()) {

    redirect("{$CFG->wwwroot}/admin/campusconnect/categoryassignment.php", '', 0);

} else if ($post = $mform->get_data()) {

    print 'TODO';

} else {

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('pluginname', 'local_campusconnect'));

    print get_string('currentassignments', 'local_campusconnect');

    print '<table class="generaltable"><thead><tr><th class="header">Current Assignments</th></tr></thead></table>';

    print '<h2>'.get_string('newassignment', 'local_campusconnect').'</h2>';

    $mform->display();

    echo "
    <script type=\"text/javascript\">

        cc_switch_mapping_fixed();

        function cc_switch_mapping_fixed() {
            document.getElementById('fitem_id_attribute').style.display = 'block';
            document.getElementById('fitem_id_daterangefrom').style.display = 'none';
            document.getElementById('fitem_id_daterangeto').style.display = 'none';
        }

        function cc_switch_mapping_date() {
            document.getElementById('fitem_id_attribute').style.display = 'none';
            document.getElementById('fitem_id_daterangefrom').style.display = 'block';
            document.getElementById('fitem_id_daterangeto').style.display = 'block';
        }
    </script>";

    echo $OUTPUT->footer();
    die();
}

// Move a rule up or down.
if ($rule && ($action === 'moveup' || $action === 'movedown')) {
    require_sesskey();
    categoryrule::move($rule->id, $action === 'moveup');
    redirect($baseurl);
}

// Delete a rule (after confirmation).
if ($rule && $action === 'delete') {
    if (optional_param('confirm', 0, PARAM_BOOL)) {
        require_sesskey();
        categoryrule::delete($rule->id);
        $msg = get_string('categoryrule_deleted', 'local_campusconnect');
        redirect($baseurl, $msg, null, \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading($strtitle);
    $confirmurl = new moodle_url($baseurl, ['action' => 'delete', 'id' => $rule->id, 'confirm' => 1, 'sesskey' => sesskey()]);
    echo $OUTPUT->confirm(get_string('categoryrule_confirmdelete', 'local_campusconnect'), $confirmurl, $baseurl);
    echo $OUTPUT->footer();
    die();
}

// Add or edit a rule.
if ($action === 'add' || ($rule && $action === 'edit')) {
    $formurl = new moodle_url($baseurl, ['action' => $action]);
    if ($rule) {
        $formurl->param('id', $rule->id);
    }
    $form = new categoryrule_form($formurl, ['ecsid' => $ecsid]);
    if ($form->is_cancelled()) {
        redirect($baseurl);
    }
    if ($data = $form->get_data()) {
        $newrule = categoryrule_form::to_rule($data);
        $newrule->ecsid = $ecsid;
        $newrule->id = $rule ? $rule->id : 0;
        categoryrule::save($newrule);
        $msg = get_string('categoryrule_saved', 'local_campusconnect');
        redirect($baseurl, $msg, null, \core\output\notification::NOTIFY_SUCCESS);
    }
    if ($rule) {
        $form->set_data(categoryrule_form::from_rule($rule));
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading($strtitle);
    echo $OUTPUT->heading(get_string($rule ? 'categoryrule_edit' : 'newassignment', 'local_campusconnect'), 3);
    $form->display();
    echo $OUTPUT->footer();
    die();
}

// List of the rules of the ECS.
echo $OUTPUT->header();
echo $OUTPUT->heading($strtitle);

if (count($ecslist) > 1) {
    $selecturl = new moodle_url('/local/campusconnect/admin/categoryassignment.php');
    $ecsnames = array_map('format_string', $ecslist);
    $label = ['label' => get_string('ecs', 'local_campusconnect')];
    echo $OUTPUT->single_select($selecturl, 'ecsid', $ecsnames, $ecsid, null, null, $label);
}

$ecssettings = new ecssettings($ecsid);
$importcategory = core_course_category::get($ecssettings->get_import_category(), IGNORE_MISSING);
$importcategoryname = $importcategory ? $importcategory->get_formatted_name() : $ecssettings->get_import_category();
echo html_writer::tag('p', get_string('categoryrule_intro', 'local_campusconnect', $importcategoryname));

echo $OUTPUT->heading(get_string('currentassignments', 'local_campusconnect'), 3);

$rules = categoryrule::get_rules($ecsid);
if (!$rules) {
    echo $OUTPUT->notification(get_string('categoryrule_norules', 'local_campusconnect'), 'info');
} else {
    $typelabels = categoryrule_form::get_mapping_type_labels();
    $dateformat = get_string('strftimedate', 'langconfig');

    $table = new html_table();
    $table->head = [
        get_string('importcat', 'local_campusconnect'),
        get_string('mappingtype', 'local_campusconnect'),
        get_string('attributename', 'local_campusconnect'),
        get_string('categoryrule_value', 'local_campusconnect'),
        get_string('actions'),
    ];
    $table->data = [];
    $first = reset($rules)->id;
    $last = end($rules)->id;
    foreach ($rules as $r) {
        $category = core_course_category::get($r->categoryid, IGNORE_MISSING);
        if ($category) {
            $categorycell = html_writer::link(
                new moodle_url('/course/management.php', ['categoryid' => $category->id]),
                $category->get_formatted_name()
            );
        } else {
            $categorycell = html_writer::span(
                get_string('categoryrule_categorymissing', 'local_campusconnect', $r->categoryid),
                'text-danger'
            );
        }

        switch ($r->mappingtype) {
            case categoryrule::TYPE_FIXED:
                $attribute = categoryrule_form::get_attribute_label($r->attribute);
                $value = s($r->fixedvalues);
                break;
            case categoryrule::TYPE_DATERANGE:
                $attribute = categoryrule_form::get_attribute_label($r->attribute);
                $value = userdate($r->datefrom, $dateformat) . ' – ' . userdate($r->dateto, $dateformat);
                break;
            default:
                $attribute = '-';
                $value = categoryrule_form::get_object_type_label((string)$r->objecttype);
        }

        $actions = [];
        $actions[] = $OUTPUT->action_icon(
            new moodle_url($baseurl, ['action' => 'edit', 'id' => $r->id]),
            new pix_icon('t/edit', get_string('edit'))
        );
        $actions[] = $OUTPUT->action_icon(
            new moodle_url($baseurl, ['action' => 'delete', 'id' => $r->id]),
            new pix_icon('t/delete', get_string('delete'))
        );
        if ($r->id != $first) {
            $actions[] = $OUTPUT->action_icon(
                new moodle_url($baseurl, ['action' => 'moveup', 'id' => $r->id, 'sesskey' => sesskey()]),
                new pix_icon('t/up', get_string('moveup'))
            );
        }
        if ($r->id != $last) {
            $actions[] = $OUTPUT->action_icon(
                new moodle_url($baseurl, ['action' => 'movedown', 'id' => $r->id, 'sesskey' => sesskey()]),
                new pix_icon('t/down', get_string('movedown'))
            );
        }

        $table->data[] = [
            $categorycell,
            $typelabels[$r->mappingtype] ?? $r->mappingtype,
            s($attribute),
            $value,
            implode(' ', $actions),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->single_button(
    new moodle_url($baseurl, ['action' => 'add']),
    get_string('newassignment', 'local_campusconnect'),
    'get'
);

echo $OUTPUT->footer();
