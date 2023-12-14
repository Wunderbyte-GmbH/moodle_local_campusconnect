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
 * Class to hold a list of URIs sent by the ECS server
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_campusconnect;

/**
 * Class uri_list to hold a list of URIs sent by the ECS server
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class uri_list {
    /**
     * Uris
     *
     * @var array
     */
    protected $uris = [];

    /**
     * Add uri to array.
     *
     * @param mixed $uri
     * @param int $id
     *
     * @return void
     *
     */
    public function add($uri, $id) {
        $this->uris[$id] = $uri;
    }

    /**
     * Get ids.
     *
     * @return array
     *
     */
    public function get_ids(): array {
        return array_keys($this->uris);
    }

    /**
     * Get uris.
     *
     * @return array
     *
     */
    public function get_uris(): array {
        return $this->uris;
    }
}
