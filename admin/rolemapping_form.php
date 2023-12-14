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
 * ECS settings page for campus connect
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir."/formslib.php");
require_once($CFG->libdir."/form/group.php");

/**
 * Class to handle rolemapping form on ECS settings page for campus connect
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class campusconnect_rolemapping_form extends moodleform {

    /**
     * $roles
     *
     * @var mixed
     */
    protected $roles;
    /**
     * $mappings
     *
     * @var mixed
     */
    protected $mappings;

    /**
     * Form definition
     *
     * @return void
     *
     */
    public function definition() {
        $this->roles = [];
        $roles = role_fix_names(get_all_roles(), context_system::instance(), ROLENAME_ORIGINAL);
        $allowedroleids = get_roles_for_contextlevels(CONTEXT_COURSE);
        foreach ($roles as $role) {
            if (in_array($role->id, $allowedroleids)) {
                $this->roles[$role->id] = $role->localname;
            }
        }
    }

    /**
     * Set data
     *
     * @param array|stdClass $defaultvalues
     *
     * @return void
     *
     */
    public function set_data($defaultvalues) {
        $this->mappings = $defaultvalues;
        parent::set_data($defaultvalues);
    }

    /**
     * Definition after data
     *
     * @return void
     *
     */
    public function definition_after_data() {
        $mform = $this->_form;

        if (!isset($this->mappings)) {
            global $DB;
            $this->mappings = $DB->get_records_menu('local_campusconnect_rolemap', [],
                                                    'ccrolename', 'ccrolename, moodleroleid');
        }

        // Create repeating mapping elements.
        $ccrolename = $mform->createElement('text', 'ccrolename', get_string('ccrolename', 'local_campusconnect'));
        $moodleroleid = $mform->createElement('select', 'moodleroleid', get_string('moodlerole', 'local_campusconnect'),
                                              $this->roles);
        $mapping = new MoodleQuickForm_group('mapping', null, [$ccrolename, $moodleroleid]);
        $repeatels = [
            $mapping,
        ];
        $this->repeat_elements($repeatels, count($this->mappings) + 3, [], 'numtexts', 'addtexts', 3);

        // Default mappings.
        $id = 0;
        foreach ($this->mappings as $ccrolename => $moodleroleid) {
            $group = $mform->getElement('mapping['.$id.']');
            $elements = $group->getElements();
            $elements[0]->setValue($ccrolename);
            $elements[1]->setValue($moodleroleid);
            $id++;
        }

        $this->add_action_buttons();
    }
}
