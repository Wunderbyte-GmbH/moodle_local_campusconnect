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
 * @package mod_booking
 * @copyright 2021 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_campusconnect\form;

use context;
use context_system;
use core_form\dynamic_form;
use moodle_url;
use stdClass;

/**
 * Add holidays form.
 *
 * @copyright Wunderbyte GmbH <info@wunderbyte.at>
 * @author Bernhard Fischer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class modal_confirmprivacy extends dynamic_form {

    /** @var int $courseid */
    private $courseid = 0;

    /** @var int $returnurl */
    private $returnurl = '';

    /**
     * Get context for dynamic submission.
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        return context_system::instance();
    }

    /**
     * Check access for dynamic submission.
     * @return void
     */
    protected function check_access_for_dynamic_submission(): void {
        // No special capability is required.
        require_login();
    }


    /**
     * Set data for dynamic submission.
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {

        $data = (object)$this->_ajaxformdata;
        $this->set_data($data);

    }

    /**
     * Process dynamic submission.
     * @return stdClass|null
     */
    public function process_dynamic_submission(): stdClass {
        global $PAGE;

        $data = $this->get_data();

        return $data;
    }

    /**
     * Form definition.
     * @return void
     */
    public function definition(): void {

        $mform = $this->_form;

        $ajaxformdata = $this->_ajaxformdata;

        $mform->addElement('hidden', 'courseid', $ajaxformdata['courseid']);
        $mform->addElement('hidden', 'returnurl', $ajaxformdata['returnurl']);

        $mform->addElement('static', 'coursetitle', 
            get_string('coursetitle', 'local_campusconnect') . ": " . $ajaxformdata['coursetitle']);
        $mform->addElement('static', 'ecstargetplatform', 
            get_string('ecstargetplatform', 'local_campusconnect') . ": " . $ajaxformdata['ecstargetplatform']);
        $mform->addElement('static', 'ecsparticipant', 
            get_string('ecsparticipant', 'local_campusconnect') . ": " . $ajaxformdata['ecsparticipant']);
        $mform->addElement('checkbox', 'ecsformconsent', 
            get_string('ecsformconsent', 'local_campusconnect'));
    
    }

    /**
     * Server-side form validation.
     * @param array $data
     * @param array $files
     * @return array $errors
     */
    public function validation($data, $files): array {
        $errors = [];

        if ($data['confirm'] != 1) {
            if (empty($data['confirm'])) {
                $errors['confirm'] = get_string('confirm', 'local_campusconnect');
            }
        }

        return $errors;
    }

    /**
     * Get page URL for dynamic submission.
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/local/campusconnect/consent.php', ['courseid' => $this->courseid]);
    }

}
