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
 * Handles the importing of membership lists from the ECS
 *
 * @package   local_campusconnect
 * @copyright 2012 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_campusconnect;

use coding_exception;
use moodle_exception;

/**
 * Class to handle the importing of membership lists from the ECS
 *
 * @package   local_campusconnect
 * @copyright 2012 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class member_personid {

    /**
     * $id
     *
     * @var int
     */
    public $id;

    /**
     * $type
     *
     * @var mixed
     */
    public $type;

    /**
     * CUSTOM_FIELD_PREFIX
     *
     * @var string
     */
    const CUSTOM_FIELD_PREFIX = 'custom_';

    /**
     * $valididtypes
     *
     * @var array strings
     */
    public static $valididtypes = [
        courselink::PERSON_UNIQUECODE, courselink::PERSON_EPPN,
        courselink::PERSON_LOGIN, courselink::PERSON_LOGINUID,
        courselink::PERSON_UID, courselink::PERSON_EMAIL,
    ];

    /**
     * $mapping
     *
     * @var mixed
     */
    protected static $mapping = null;

    /**
     * Constructor
     *
     * @param int $personid
     * @param mixed $personidtype
     *
     */
    public function __construct($personid, $personidtype) {
        if (!self::valid_type($personidtype)) {
            throw new moodle_exception('invalidpersonidtype', 'local_campusconnect');
        }
        $this->id = $personid;
        $this->type = $personidtype;
    }

    /**
     * Valid type
     *
     * @param mixed $personidtype
     *
     * @return bool
     *
     */
    public static function valid_type($personidtype) {
        return in_array($personidtype, self::$valididtypes);
    }

    /**
     * Get type from member
     *
     * @param mixed $member
     *
     * @return mixed
     *
     */
    public static function get_type_from_member($member) {
        if (isset($member->personIDtype)) {
            if (self::valid_type($member->personIDtype)) {
                return $member->personIDtype;
            } else {
                log::add("Invalid personIDtype in course_members resource: {$member->personIDtype}");
                return null;
            }
        }
        return courselink::PERSON_LOGIN; // Default type, if none specified.
    }

    /**
     * Get possible user fields
     *
     * @return array
     *
     */
    public static function get_possible_user_fields() {
        return participantsettings::get_possible_export_fields();
    }

    /**
     * Get mapping
     *
     * @return mixed
     *
     */
    public static function get_mapping() {
        if (!self::$mapping) {
            if ($mapping = get_config('local_campusconnect', 'member_personid_mapping')) {
                self::$mapping = unserialize($mapping);
            } else {
                self::$mapping = [
                    courselink::PERSON_UNIQUECODE => null,
                    courselink::PERSON_EPPN => null,
                    courselink::PERSON_LOGIN => 'username',
                    courselink::PERSON_LOGINUID => null,
                    courselink::PERSON_UID => 'id',
                    courselink::PERSON_EMAIL => 'email',
                ];
            }
        }
        return self::$mapping;
    }

    /**
     * Set mapping
     *
     * @param mixed $mapping
     *
     * @return void
     *
     */
    public static function set_mapping($mapping) {
        $possibleuserfields = self::get_possible_user_fields();
        foreach ($mapping as $personidtype => $userfield) {
            if (!in_array($userfield, $possibleuserfields)) {
                $mapping[$personidtype] = null; // Invalid Moodle user field => clear the mapping.
            }
            if (!self::valid_type($personidtype)) {
                unset($mapping[$personidtype]); // Invalid persondidtype => remove the mapping.
            }
        }
        foreach (self::$valididtypes as $personidtype) {
            if (!array_key_exists($personidtype, $mapping)) {
                $mapping[$personidtype] = null; // Make sure all mappings are present.
            }
        }
        self::$mapping = $mapping;
        set_config('member_personid_mapping', serialize($mapping), 'local_campusconnect');
    }

    /**
     * Reset default mapping
     *
     * @return void
     *
     */
    public static function reset_default_mapping() {
        unset_config('member_personid_mapping', 'local_campusconnect');
        self::$mapping = null;
    }

    /**
     * Get userfield from type
     *
     * @param mixed $personidtype
     *
     * @return mixed
     *
     */
    public static function get_userfield_from_type($personidtype) {
        $mapping = self::get_mapping();
        if (!array_key_exists($personidtype, $mapping)) {
            throw new coding_exception("Invalid personidtype: {$personidtype}");
        }
        return $mapping[$personidtype];
    }

    /**
     * Is custom field
     *
     * @param string $fieldname
     *
     * @return string|bool
     *
     */
    public static function is_custom_field($fieldname) {
        $len = strlen(self::CUSTOM_FIELD_PREFIX);
        if (substr($fieldname, 0, $len) == self::CUSTOM_FIELD_PREFIX) {
            return substr($fieldname, $len);
        }
        return false;
    }
}
