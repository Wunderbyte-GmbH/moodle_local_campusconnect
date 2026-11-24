<?php
// This file is part of the CampusConnect plugin for Moodle - http://moodle.org/
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
 * Form to add or edit a category assignment rule.
 *
 * @package    local_campusconnect
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_campusconnect\form;

use core_course_category;
use html_writer;
use local_campusconnect\categoryrule;
use local_campusconnect\participantsettings;
use moodleform;
use stdClass;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Form to add or edit a category assignment rule.
 *
 * Custom data: 'ecsid' - the ECS the rule belongs to.
 *
 * @package    local_campusconnect
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class categoryrule_form extends moodleform {
    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;
        $ecsid = $this->_customdata['ecsid'];

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'ecsid', $ecsid);
        $mform->setType('ecsid', PARAM_INT);

        $mform->addElement('header', 'categoryassignment', get_string('categoryassignment', 'local_campusconnect'));

        $categories = core_course_category::make_categories_list('moodle/category:manage');
        $mform->addElement('autocomplete', 'categoryid', get_string('importcat', 'local_campusconnect'), $categories);
        $mform->addRule('categoryid', null, 'required', null, 'client');
        $mform->setType('categoryid', PARAM_INT);

        $types = [];
        foreach (self::get_mapping_type_labels() as $type => $label) {
            $types[] = $mform->createElement('radio', 'mappingtype', '', $label, $type);
        }
        $mform->addGroup($types, 'mappingtypegroup', get_string('mappingtype', 'local_campusconnect'), ' ', false);
        $mform->addHelpButton('mappingtypegroup', 'mappingtype', 'local_campusconnect');
        $mform->setDefault('mappingtype', categoryrule::TYPE_FIXED);
        $mform->setType('mappingtype', PARAM_INT);

        // Fixed values.
        $attributes = [];
        foreach (categoryrule::get_fixed_attributes() as $attribute) {
            $attributes[$attribute] = self::get_attribute_label($attribute);
        }
        $mform->addElement('select', 'attribute', get_string('attributename', 'local_campusconnect'), $attributes);
        $mform->setDefault('attribute', categoryrule::ATTRIBUTE_PARTICIPANT);
        $mform->hideIf('attribute', 'mappingtype', 'neq', categoryrule::TYPE_FIXED);

        $mform->addElement('text', 'fixedvalues', get_string('categoryrule_fixedvalues', 'local_campusconnect'), ['size' => 60]);
        $mform->setType('fixedvalues', PARAM_TEXT);
        $mform->addHelpButton('fixedvalues', 'categoryrule_fixedvalues', 'local_campusconnect');
        $mform->hideIf('fixedvalues', 'mappingtype', 'neq', categoryrule::TYPE_FIXED);

        $mform->addElement(
            'static',
            'participants',
            get_string('categoryrule_participants', 'local_campusconnect'),
            self::get_participants_html($ecsid)
        );
        $mform->hideIf('participants', 'mappingtype', 'neq', categoryrule::TYPE_FIXED);

        // Date range.
        $dateattributes = [];
        foreach (categoryrule::get_date_attributes() as $attribute) {
            $dateattributes[$attribute] = self::get_attribute_label($attribute);
        }
        $mform->addElement('select', 'dateattribute', get_string('attributename', 'local_campusconnect'), $dateattributes);
        $mform->setDefault('dateattribute', 'firstDate');
        $mform->hideIf('dateattribute', 'mappingtype', 'neq', categoryrule::TYPE_DATERANGE);

        $mform->addElement('date_selector', 'datefrom', get_string('fromdate'));
        $mform->hideIf('datefrom', 'mappingtype', 'neq', categoryrule::TYPE_DATERANGE);
        $mform->addElement('date_selector', 'dateto', get_string('todate'));
        $mform->hideIf('dateto', 'mappingtype', 'neq', categoryrule::TYPE_DATERANGE);

        // Object type.
        $objecttypes = [];
        foreach (categoryrule::get_object_types() as $objecttype) {
            $objecttypes[$objecttype] = self::get_object_type_label($objecttype);
        }
        $mform->addElement('select', 'objecttype', get_string('categoryrule_objecttype', 'local_campusconnect'), $objecttypes);
        $mform->hideIf('objecttype', 'mappingtype', 'neq', categoryrule::TYPE_OBJECTTYPE);

        $this->add_action_buttons();
    }

    /**
     * Check the rule.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $rule = self::to_rule((object)$data);
        foreach (categoryrule::validate($rule) as $field => $code) {
            if ($field == 'attribute' && $rule->mappingtype == categoryrule::TYPE_DATERANGE) {
                $field = 'dateattribute';
            } else if ($field == 'mappingtype') {
                $field = 'mappingtypegroup';
            } else if ($field == 'ecsid') {
                $field = 'categoryid';
            }
            $errors[$field] = get_string('categoryrule_error_' . $code, 'local_campusconnect');
        }

        return $errors;
    }

    /**
     * Convert the form data into a rule.
     *
     * The "to" date is set to the end of the day (23:59:59), so the whole day is included.
     *
     * @param stdClass $data
     * @return stdClass
     */
    public static function to_rule(stdClass $data): stdClass {
        $mappingtype = (int)($data->mappingtype ?? 0);
        $attribute = '';
        if ($mappingtype == categoryrule::TYPE_FIXED) {
            $attribute = $data->attribute ?? '';
        } else if ($mappingtype == categoryrule::TYPE_DATERANGE) {
            $attribute = $data->dateattribute ?? '';
        }

        return (object)[
            'id' => (int)($data->id ?? 0),
            'ecsid' => (int)($data->ecsid ?? 0),
            'categoryid' => (int)($data->categoryid ?? 0),
            'mappingtype' => $mappingtype,
            'attribute' => $attribute,
            'fixedvalues' => $data->fixedvalues ?? '',
            'datefrom' => empty($data->datefrom) ? null : (int)$data->datefrom,
            'dateto' => empty($data->dateto) ? null : categoryrule::end_of_day((int)$data->dateto),
            'objecttype' => $data->objecttype ?? null,
        ];
    }

    /**
     * Convert a rule into form data.
     *
     * @param stdClass $rule
     * @return stdClass
     */
    public static function from_rule(stdClass $rule): stdClass {
        $data = (object)[
            'id' => $rule->id,
            'ecsid' => $rule->ecsid,
            'categoryid' => $rule->categoryid,
            'mappingtype' => $rule->mappingtype,
            'fixedvalues' => $rule->fixedvalues,
        ];
        if ($rule->mappingtype == categoryrule::TYPE_FIXED) {
            $data->attribute = $rule->attribute;
        } else if ($rule->mappingtype == categoryrule::TYPE_DATERANGE) {
            $data->dateattribute = $rule->attribute;
            $data->datefrom = $rule->datefrom;
            $data->dateto = $rule->dateto;
        } else if ($rule->mappingtype == categoryrule::TYPE_OBJECTTYPE) {
            $data->objecttype = $rule->objecttype;
        }
        return $data;
    }

    /**
     * Labels of the rule types.
     *
     * @return string[] type => label
     */
    public static function get_mapping_type_labels(): array {
        return [
            categoryrule::TYPE_FIXED => get_string('fixedvalue', 'local_campusconnect'),
            categoryrule::TYPE_DATERANGE => get_string('daterange', 'local_campusconnect'),
            categoryrule::TYPE_OBJECTTYPE => get_string('categoryrule_objecttype', 'local_campusconnect'),
        ];
    }

    /**
     * Label of an attribute. The ECS course link fields are shown with their ECS name.
     *
     * @param string $attribute
     * @return string
     */
    public static function get_attribute_label(string $attribute): string {
        if ($attribute == categoryrule::ATTRIBUTE_PARTICIPANT || $attribute == categoryrule::ATTRIBUTE_COMMUNITY) {
            return get_string('categoryrule_attr_' . $attribute, 'local_campusconnect');
        }
        return $attribute;
    }

    /**
     * Label of an object type.
     *
     * @param string $objecttype
     * @return string
     */
    public static function get_object_type_label(string $objecttype): string {
        if ($objecttype == categoryrule::OBJECTTYPE_COURSELINK) {
            return get_string('ecscourselink', 'local_campusconnect');
        }
        return $objecttype;
    }

    /**
     * List of the participants we import course links from, with their participant ID.
     *
     * @param int $ecsid
     * @return string HTML
     */
    protected static function get_participants_html(int $ecsid): string {
        global $DB;

        $participants = $DB->get_records('local_campusconnect_part', [
            'ecsid' => $ecsid,
            'active' => 1,
            'import' => 1,
            'importtype' => participantsettings::IMPORT_LINK,
        ], 'mid', 'id, ecsid, mid, displayname');
        if (!$participants) {
            return get_string('categoryrule_participants_none', 'local_campusconnect');
        }

        $items = [];
        foreach ($participants as $participant) {
            $items[] = html_writer::tag('code', s("{$participant->ecsid}_{$participant->mid}")) . ' - '
                . s($participant->displayname);
        }
        return html_writer::alist($items);
    }
}
