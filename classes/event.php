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
 * Represents an incoming event
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_campusconnect;

use coding_exception;

/**
 * Class represents an incoming event
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class event {

    /**
     * STATUS_CREATED
     *
     * @var string
     */
    const STATUS_CREATED = 'created';

    /**
     * STATUS_UPDATED
     *
     * @var string
     */
    const STATUS_UPDATED = 'updated';

    /**
     * STATUS_DESTROYED
     *
     * @var string
     */
    const STATUS_DESTROYED = 'destroyed';

    /**
     * STATUS_NEW_EXPORT
     *
     * @var string
     */
    const STATUS_NEW_EXPORT = 'new_export'; // Not quite sure when this is sent.

    /**
     * RES_COURSELINK
     *
     * @var string
     */
    const RES_COURSELINK = 'campusconnect/courselinks';

    /**
     * RES_DIRECTORYTREE
     *
     * @var string
     */
    const RES_DIRECTORYTREE = 'campusconnect/directory_trees';

    /**
     * RES_COURSE
     *
     * @var string
     */
    const RES_COURSE = 'campusconnect/courses';

    /**
     * RES_COURSE_MEMBERS
     *
     * @var string
     */
    const RES_COURSE_MEMBERS = 'campusconnect/course_members';

    /**
     * RES_COURSE_URL
     *
     * @var string
     */
    const RES_COURSE_URL = 'campusconnect/course_urls';

    /**
     * RES_ENROLMENT
     *
     * @var string
     */
    const RES_ENROLMENT = 'campusconnect/member_status';

    /**
     * $validstatus
     *
     * @var array
     */
    protected static $validstatus = [self::STATUS_CREATED, self::STATUS_UPDATED, self::STATUS_DESTROYED];

    /**
     * $validresources
     *
     * @var array
     */
    protected static $validresources = [
        self::RES_COURSELINK, self::RES_DIRECTORYTREE, self::RES_COURSE,
        self::RES_COURSE_MEMBERS, self::RES_COURSE_URL, self::RES_ENROLMENT,
    ];

    /**
     * $resource
     *
     * @var mixed
     */
    protected $resource;

    /**
     * $resourceid
     *
     * @var int
     */
    protected $resourceid;

    /**
     * $resourcetype
     *
     * @var mixed
     */
    protected $resourcetype;

    /**
     * $ecsid
     *
     * @var int
     */
    protected $ecsid;

    /**
     * $status
     *
     * @var mixed
     */
    protected $status;

    /**
     * $id
     *
     * @var int|null
     */
    protected $id = null;

    /**
     * $failcount
     *
     * @var int
     */
    protected $failcount = 0;

    /**
     * Constructor
     *
     * @param mixed $eventdata
     * @param int|null $ecsid
     *
     */
    public function __construct($eventdata, $ecsid = null) {
        if (isset($eventdata->id)) {
            // Constructing from a database record.
            $this->id = $eventdata->id;
            $this->ecsid = $eventdata->serverid;
            $this->status = $eventdata->status;
            $this->resourcetype = $eventdata->type;
            $this->resourceid = $eventdata->resourceid;
            $this->resource = $this->resourcetype.'/'.$this->resourceid;
            $this->failcount = $eventdata->failcount;

        } else {
            // Constructing from an ECS response.
            $this->ecsid = $ecsid;
            $this->resource = $eventdata->ressource; // Handle the spelling mistake.
            $this->status = $eventdata->status;

            $resource = explode('/', $this->resource);
            $this->resourceid = array_pop($resource);
            $this->resourcetype = implode('/', $resource);
        }

        if (!self::is_valid_resource($this->resourcetype)) {
            throw new event_exception("Unexpected event type: $this->resourcetype");
        }
        if (!self::is_valid_status($this->status)) {
            throw new event_exception("Unexpected event status: {$this->status}");
        }
    }

    /**
     * Get id
     *
     * @return int
     *
     */
    public function get_id() {
        if (is_null($this->id)) {
            throw new coding_exception("Can only call 'get_id' on events loaded from the database");
        }
        return $this->id;
    }

    /**
     * Get ecs id
     *
     * @return int
     *
     */
    public function get_ecs_id() {
        return $this->ecsid;
    }

    /**
     * Get resource id
     *
     * @return int
     *
     */
    public function get_resource_id() {
        return $this->resourceid;
    }

    /**
     * Get resource type
     *
     * @return mixed
     *
     */
    public function get_resource_type() {
        return $this->resourcetype;
    }

    /**
     * Get status
     *
     * @return mixed
     *
     */
    public function get_status() {
        return $this->status;
    }

    /**
     * Get failcount
     *
     * @return mixed
     *
     */
    public function get_failcount() {
        return $this->failcount;
    }

    /**
     * Create / destroy status should override duplicate events,
     * update status should not override
     *
     * @return bool true if any duplicate event should be updated
     */
    public function should_update_duplicate(): bool {
        return $this->get_status() == self::STATUS_CREATED || $this->get_status() == self::STATUS_DESTROYED;
    }

    /**
     * Is valid resource
     *
     * @param mixed $type
     *
     * @return bool
     *
     */
    public static function is_valid_resource($type): bool {
        return in_array($type, self::$validresources);
    }

    /**
     * Is valid status
     *
     * @param mixed $status
     *
     * @return bool
     *
     */
    public static function is_valid_status($status): bool {
        return in_array($status, self::$validstatus);
    }
}
