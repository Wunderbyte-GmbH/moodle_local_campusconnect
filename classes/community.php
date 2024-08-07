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
 * Represents a participant (VLE/CMS) in an ECS community.
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_campusconnect;

/**
 * Clss represents a participant (VLE/CMS) in an ECS community.
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class community {

    /**
     * $name
     *
     * @var string
     */
    public string $name;

    /**
     * $desciption
     *
     * @var string
     */
    public string $description;

    /**
     * $ecsid
     *
     * @var int
     */
    public int $ecsid;

    /** @var participantsettings[] */
    public array $participants = [];

    /**
     * Constructor
     *
     * @param int $ecsid
     * @param string $name
     * @param string $description
     *
     */
    public function __construct(int $ecsid, string $name, string $description) {
        $this->name = $name;
        $this->description = $description;
        $this->ecsid = $ecsid;
    }

    /**
     * Add participant
     *
     * @param participantsettings $part
     *
     * @return void
     *
     */
    public function add_participant(participantsettings $part): void {
        $this->participants[$part->get_identifier()] = $part;
    }

    /**
     * Remove participant
     *
     * @param participantsettings $part
     *
     * @return void
     *
     */
    public function remove_participant(participantsettings $part): void {
        unset($this->participants[$part->get_identifier()]);
    }

    /**
     * Has participants
     *
     * @return bool
     *
     */
    public function has_participants(): bool {
        return !!($this->participants);
    }
}
