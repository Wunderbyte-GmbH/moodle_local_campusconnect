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
 * Connection exception
 *
 * @package   local_campusconnect
 * @copyright 2016 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_campusconnect;

use moodle_exception;

/**
 * Class to handle connection exception
 *
 * @package   local_campusconnect
 * @copyright 2016 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class connect_exception extends moodle_exception {
    /**
     * HTTP status code of the ECS response that caused this exception (null if not caused by a bad response)
     *
     * @var int|null
     */
    protected $httpcode;

    /**
     * Constructor
     *
     * @param string $msg
     * @param int|null $httpcode the HTTP status code of the ECS response, if known
     *
     */
    public function __construct($msg, $httpcode = null) {
        parent::__construct('error', 'local_campusconnect', '', $msg);
        $this->httpcode = $httpcode;
        $this->email_admin($msg);
    }

    /**
     * Get the HTTP status code of the ECS response that caused this exception
     *
     * @return int|null
     */
    public function get_http_code() {
        return $this->httpcode;
    }

    /**
     * Email admin
     *
     * @param string $msg
     *
     * @return void
     *
     */
    protected function email_admin($msg) {
        // Not implemented yet.
        // May need to consider gathering the errors into a log and only sending emails at most once an hour?
    }
}
