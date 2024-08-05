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
 * Dynamic semester cancel confirm form
 *
 * @package local_campusconnect
 * @copyright 2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_campusconnect\form;

use local_campusconnect\ecssettings;
use local_campusconnect\metadata;
use moodleform;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir.'/formslib.php');

/**
 * Class to handle form for import settings page for campus connect
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class campusconnect_import_form extends moodleform {

    /**
     * Form definition
     *
     * @return void
     *
     */
    public function definition() {
        $ecslist = ecssettings::list_ecs();

        foreach ($ecslist as $ecsid => $ecsname) {

            $mform = $this->_form;
            $mform->addElement('header', '', get_string('importmappingsettings', 'local_campusconnect'));
            $mform->addElement('html', "<h2>$ecsname</h2>");
            $mform->addElement('html', "<h3>".get_string('course')."</h3>");
            $ecssettings = new ecssettings($ecsid);
            $metadata = new metadata($ecssettings, false);
            $localfields = metadata::list_local_fields();
            $currentmappings = $metadata->get_import_mappings();

            foreach ($localfields as $localmap) {
                $elname = $ecsid.'_'.$localmap.'_course';
                $this->add_element($mform, $elname, $localmap, $currentmappings, $metadata);
            }

            $mform->addElement('html', "<h3>".get_string('externalcourse', 'local_campusconnect')."</h3>");

            $metadata = new metadata($ecssettings, true);
            $currentmappings = $metadata->get_import_mappings();

            foreach ($localfields as $localmap) {
                $elname = $ecsid.'_'.$localmap.'_courselink';
                $this->add_element($mform, $elname, $localmap, $currentmappings, $metadata);
            }
        }
        $this->add_action_buttons();
    }

    /**
     * Conditionally add element.
     *
     * @param \MoodleQuickForm $mform
     * @param string $elname
     * @param string $localmap
     * @param array $currentmappings
     * @param metadata $metadata
     * @return void
     */
    private function add_element(\MoodleQuickForm &$mform, string $elname, string $localmap,
            array $currentmappings, metadata $metadata): void {
        $strunmapped = get_string('unmapped', 'local_campusconnect');
        $strnomappings = get_string('nomappings', 'local_campusconnect');
        if ($localmap == 'summary') {
            $mform->addElement('editor', $elname, $localmap);
            $mform->setType($elname, PARAM_RAW);
            $mform->setDefault($elname, ['text' => $currentmappings[$localmap], 'format' => FORMAT_HTML]);
        } else if ($metadata->is_text_field($localmap)) {
            $mform->addElement('text', $elname, $localmap, $currentmappings[$localmap]);
            $mform->setDefault($elname, $currentmappings[$localmap]);
            $mform->setType($elname, PARAM_RAW);
        } else {
            $maparray = $metadata->list_remote_to_local_fields($localmap);
            if ($maparray) {
                $maps = ['' => $strunmapped];
                foreach ($maparray as $i) {
                    $maps[$i] = $i;
                }
            } else {
                $maps = ['' => $strnomappings];
            }
            $mform->addElement('select', $elname, $localmap, $maps, $currentmappings[$localmap]);
            $mform->setDefault($elname, $currentmappings[$localmap]);
        }
    }

    /**
     * Set errors
     *
     * @param array $errors
     * @return void
     */
    public function set_errors($errors) {
        $form = $this->_form;
        foreach ($errors as $element => $message) {
            $form->setElementError($element, $message);
        }
    }
}