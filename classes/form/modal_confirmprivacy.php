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

use context;
use context_system;
use core_form\dynamic_form;
use html_writer;
use local_campusconnect\courselink;
use local_campusconnect\participantsettings;
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

    /** @var int $targeturl */
    private $targeturl = '';

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

        global $USER, $DB;

        $mform = $this->_form;

        $ajaxformdata = $this->_ajaxformdata;

        $courselink = courselink::get_by_courseid($ajaxformdata['courseid']);

        $coursetitle = $DB->get_field('course', 'fullname', ['id' => $ajaxformdata['courseid']]);

        $url = $courselink->url;
        $participant = new participantsettings($courselink->ecsid, $courselink->mid);

        $mform->addElement('hidden', 'courseid', $ajaxformdata['courseid']);
        $mform->addElement('hidden', 'targeturl', $ajaxformdata['targeturl']);

        $mform->addElement('static', 'coursetitle',
            get_string('coursetitle', 'local_campusconnect'), html_writer::tag('b', $coursetitle));

        $mform->addElement('static', 'ecsparticipant',
            get_string('ecsparticipant', 'local_campusconnect'), html_writer::tag('b', $participant->get_displayname()));

        $exportdata = $participant->map_export_data($USER);

        $listelements = array_map(fn($a) => html_writer::tag('li', $a), $exportdata);

        $html = html_writer::tag('ul', implode (PHP_EOL, $listelements));

        $mform->addElement('static', 'userinformation', '', $html);

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

        if ($data['ecsformconsent'] != 1) {
            if (empty($data['ecsformconsent'])) {
                $errors['ecsformconsent'] = get_string('confirm', 'local_campusconnect');
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
