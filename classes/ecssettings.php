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
 * Configuration settings for connecting to an ECS
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_campusconnect;

use coding_exception;
use stdClass;

/**
 * Class to configure settings for connecting to an ECS
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ecssettings {

    /**
     * AUTH_NONE
     * Development only - direct connection to ECS server.
     *
     * @var int
     */
    const AUTH_NONE = 1;

    /**
     * AUTH_HTTP
     * Basic HTTP authentication.
     *
     * @var int
     */
    const AUTH_HTTP = 2;

    /**
     * AUTH_CERTIFICATE
     * Certificate based authentication.
     *
     * @var int
     */
    const AUTH_CERTIFICATE = 3;

    // Connection settings.
    /**
     * $url
     *
     * @var string
     */
    protected $url = '';

    /**
     * $auth
     *
     * @var int
     */
    protected $auth = self::AUTH_CERTIFICATE;

    /**
     * $ecsauth
     *
     * @var string
     */
    protected $ecsauth = '';

    /**
     * $httpuser
     *
     * @var string
     */
    protected $httpuser = '';

    /**
     * $httppass
     *
     * @var string
     */
    protected $httppass = '';

    /**
     * $cacertpath
     *
     * @var string
     */
    protected $cacertpath = '';

    /**
     * $certpath
     *
     * @var string
     */
    protected $certpath = '';

    /**
     * $keypath
     *
     * @var string
     */
    protected $keypath = '';

    /**
     * $keypass
     *
     * @var string
     */
    protected $keypass = '';

    // Settings for incoming data.
    /**
     * $crontime
     *
     * @var int
     */
    protected $crontime = 60;

    /**
     * $lastcron
     *
     * @var int
     */
    protected $lastcron = 0;

    /**
     * $importcategory
     *
     * @var mixed|null
     */
    protected $importcategory = null;

    /**
     * $importrole
     *
     * @var string
     */
    protected $importrole = '-1';

    /**
     * $importperiod
     *
     * @var int
     */
    protected $importperiod = 6;

    // Notification details.
    /**
     * $notifyusers
     *
     * @var string
     */
    protected $notifyusers = '';

    /**
     * $notifycontent
     *
     * @var string
     */
    protected $notifycontent = '';

    /**
     * $notifycourses
     *
     * @var string
     */
    protected $notifycourses = '';

    // Misc settings.
    /**
     * $recordid
     *
     * @var int
     */
    protected $recordid = null;

    /**
     * $enabled
     *
     * @var bool
     */
    protected $enabled = true;

    /**
     * $name
     *
     * @var string
     */
    protected $name = '';

    /**
     * Used to validate incoming settings.
     *
     * @var array
     */
    protected $validsettings = [
        'recordid' => 'id',
        'enabled' => 'enabled',
        'name' => 'name',
        'url' => 'url',
        'auth' => 'auth',
        'ecsauth' => 'ecsauth',
        'httpuser' => 'httpuser',
        'httppass' => 'httppass',
        'cacertpath' => 'cacertpath',
        'certpath' => 'certpath',
        'keypath' => 'keypath',
        'keypass' => 'keypass',
        'crontime' => 'crontime',
        'importcategory' => 'importcategory',
        'importrole' => 'importrole',
        'importperiod' => 'importperiod',
        'notifyusers' => 'notifyusers',
        'notifycontent' => 'notifycontent',
        'notifycourses' => 'notifycourses',
    ];

    /**
     * $activeecs
     *
     * @var mixed|null
     */
    protected static $activeecs = null;

    /**
     * Initialise a settings object
     *
     * @param int $ecsid optional - the ID of the ECS to load settings for
     */
    public function __construct($ecsid = null) {
        // Load the settings, if an ECS ID has been specified.
        if ($ecsid) {
            $this->load_settings($ecsid);
        }
    }

    /**
     * List ecs
     *
     * @param bool $onlyenabled
     *
     * @return array
     *
     */
    public static function list_ecs($onlyenabled = true) {
        global $DB;
        $params = [];
        if ($onlyenabled) {
            $params['enabled'] = 1;
        }
        return $DB->get_records_menu('local_campusconnect_ecs', $params, 'name, id', 'id, name');
    }

    /**
     * Check if the given ECS is currently active.
     * @param int $ecsid
     *
     * @return bool
     */
    public static function is_active_ecs($ecsid) {
        if (self::$activeecs === null) {
            self::$activeecs = array_keys(self::list_ecs(true));
        }
        return in_array($ecsid, self::$activeecs);
    }

    /**
     * Get id
     *
     * @return int
     *
     */
    public function get_id() {
        return $this->recordid;
    }

    /**
     * Is enabled
     *
     * @return bool
     *
     */
    public function is_enabled() {
        return $this->enabled;
    }

    /**
     * Get name
     *
     * @return string
     *
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Get url
     *
     * @return mixed
     *
     */
    public function get_url() {
        return $this->url;
    }

    /**
     * Get auth type
     *
     * @return int
     *
     */
    public function get_auth_type() {
        return $this->auth;
    }

    /**
     * Get ecs auth
     *
     * @return string
     *
     */
    public function get_ecs_auth() {
        if ($this->get_auth_type() != self::AUTH_NONE) {
            throw new coding_exception('get_ecs_auth only valid when using no authentication');
        }
        return $this->ecsauth;
    }

    /**
     * Get http user
     *
     * @return string
     *
     */
    public function get_http_user() {
        if ($this->get_auth_type() != self::AUTH_HTTP) {
            throw new coding_exception('get_http_user only valid when using http authentication');
        }
        return $this->httpuser;
    }

    /**
     * Get http password
     *
     * @return string
     *
     */
    public function get_http_password() {
        if ($this->get_auth_type() != self::AUTH_HTTP) {
            throw new coding_exception('get_http_password only valid when using http authentication');
        }
        return $this->httppass;
    }

    /**
     * Get ca cert path
     *
     * @return string
     *
     */
    public function get_ca_cert_path() {
        if ($this->get_auth_type() != self::AUTH_CERTIFICATE) {
            throw new coding_exception('get_ca_cert_path only valid when using certificate authentication');
        }
        return $this->cacertpath;
    }

    /**
     * Get client cert path
     *
     * @return string
     *
     */
    public function get_client_cert_path() {
        if ($this->get_auth_type() != self::AUTH_CERTIFICATE) {
            throw new coding_exception('get_client_cert_path only valid when using certificate authentication');
        }
        return $this->certpath;
    }

    /**
     * Get key path
     *
     * @return string
     *
     */
    public function get_key_path() {
        if ($this->get_auth_type() != self::AUTH_CERTIFICATE) {
            throw new coding_exception('get_key_path only valid when using certificate authentication');
        }
        return $this->keypath;
    }

    /**
     * Get key pass
     *
     * @return string
     *
     */
    public function get_key_pass() {
        if ($this->get_auth_type() != self::AUTH_CERTIFICATE) {
            throw new coding_exception('get_key_pass only valid when using certificate authentication');
        }
        return $this->keypass;
    }

    /**
     * Get import category
     *
     * @return mixed
     *
     */
    public function get_import_category() {
        return $this->importcategory;
    }

    /**
     * Get import role
     *
     * @return string
     *
     */
    public function get_import_role() {
        return $this->importrole;
    }

    /**
     * Get import period
     *
     * @return int
     *
     */
    public function get_import_period() {
        return $this->importperiod;
    }

    /**
     * Get notify users
     *
     * @return array|bool
     *
     */
    public function get_notify_users() {
        return explode(',', $this->notifyusers);
    }

    /**
     * Get notify content
     *
     * @return array|bool
     *
     */
    public function get_notify_content() {
        return explode(',', $this->notifycontent);
    }

    /**
     * Get notify courses
     *
     * @return array|bool
     *
     */
    public function get_notify_courses() {
        return explode(',', $this->notifycourses);
    }

    /**
     * Get certificate expiry
     *
     * @return string
     *
     */
    public function get_certificate_expiry() {
        if ($this->auth != self::AUTH_CERTIFICATE) {
            return '';
        }
        if (empty($this->certpath)) {
            return '';
        }
        $certinfo = openssl_x509_parse(file_get_contents($this->certpath));
        return userdate($certinfo['validTo_time_t'], get_string('strftimedate'));
    }

    /**
     * Load settings
     *
     * @param mixed $ecsid
     *
     * @return void
     *
     */
    protected function load_settings($ecsid) {
        global $DB;

        $settings = $DB->get_record('local_campusconnect_ecs', ['id' => $ecsid], '*', MUST_EXIST);
        $this->set_settings($settings);
    }

    /**
     * Set settings
     *
     * @param mixed $settings
     *
     * @return void
     *
     */
    protected function set_settings($settings) {
        foreach ($this->validsettings as $localname => $dbname) {
            if (isset($settings->$dbname)) {
                $this->$localname = $settings->$dbname;
            }
        }
        if (isset($settings->lastcron)) {
            $this->lastcron = $settings->lastcron; // Not part of validsettings, as should never be set via the UI.
        }
    }

    /**
     * Save settings
     *
     * @param mixed $settings
     *
     * @return void
     *
     */
    public function save_settings($settings) {
        global $DB;

        $settings = (array)$settings; // Avoid updating passed-in objects.
        $settings = (object)$settings;

        // Clean the settings - make sure only expected values exist.
        foreach ($settings as $setting => $value) {
            if (!array_key_exists($setting, $this->validsettings)) {
                unset($settings->$setting);
            }
        }

        // Check the settings are valid.
        if (empty($settings->url)) {
            if (empty($this->url)) {
                throw new coding_exception("campusconnect_ecssettings - missing 'url' field");
            }
        } else {
            $scheme = parse_url($settings->url, PHP_URL_SCHEME);
            if ($scheme != 'http' && $scheme != 'https') {
                throw new coding_exception("campusconnect_ecssettings - URL must start 'http://' or 'https://'");
            }
        }

        if (isset($settings->auth)) {
            $auth = $settings->auth;
        } else {
            if (empty($this->auth)) {
                throw new coding_exception('campusconnect_ecssettings - missing \'auth\' field');
            }
            $auth = $this->auth;
        }

        switch ($auth) {
            case self::AUTH_NONE:
                if (empty($settings->ecsauth) && empty($this->ecsauth)) {
                    throw new coding_exception("campusconnect_ecssettings - auth method 'AUTH_NONE' requires an 'ecsauth' value");
                }
                break;
            case self::AUTH_HTTP:
                $requiredfields = ['httpuser', 'httppass'];
                foreach ($requiredfields as $required) {
                    if (empty($settings->$required) && empty($this->$required)) {
                        throw new coding_exception("campusconnect_ecssettings - auth method 'AUTH_HTTP' requires ".
                                                   "a '$required' value");
                    }
                }
                break;
            case self::AUTH_CERTIFICATE:
                $requiredfields = ['cacertpath', 'certpath', 'keypath', 'keypass'];
                foreach ($requiredfields as $required) {
                    if (empty($settings->$required) && empty($this->$required)) {
                        throw new coding_exception("campusconnect_ecssettings - auth method 'AUTH_CERTIFICATE' requires ".
                                                   "a '$required' value");
                    }
                }
                break;
            default:
                throw new coding_exception("campusconnect_ecssettings - invalid 'auth' value: $auth");
        }

        if (isset($settings->crontime) && $settings->crontime < 0) {
            throw new coding_exception("campusconnect_ecssettings - invalid crontime: $settings->crontime");
        }

        if (isset($settings->importcategory)) {
            if ($settings->importcategory != $this->importcategory) {
                if (!$DB->record_exists('course_categories', ['id' => $settings->importcategory])) {
                    throw new coding_exception("campusconnect_ecssettings - non-existent category ID: $settings->importcategory");
                }
            }
        } else if (empty($this->importcategory)) {
            throw new coding_exception("campusconnect_ecssettings - missing 'importcategory' field");
        }

        if (isset($settings->importrole)) {
            if ($settings->importrole != $this->importrole && $settings->importrole != -1) {
                if (!$DB->record_exists('role', ['shortname' => $settings->importrole])) {
                    throw new coding_exception("campusconnect_ecssettings - non-existent role shortname: $settings->importrole");
                }
            }
        } else if (empty($this->importrole)) {
            throw new coding_exception("campusconnect_ecssettings - missing 'importrole' field");
        }

        // Remove any spaces from the notify lists.
        if (isset($settings->notifyusers)) {
            $notify = explode(',', $settings->notifyusers);
            $notify = array_map('trim', $notify);
            $settings->notifyusers = implode(',', $notify);
        }

        if (isset($settings->notifycontent)) {
            $notify = explode(',', $settings->notifycontent);
            $notify = array_map('trim', $notify);
            $settings->notifycontent = implode(',', $notify);
        }

        if (isset($settings->notifycourses)) {
            $notify = explode(',', $settings->notifycourses);
            $notify = array_map('trim', $notify);
            $settings->notifycourses = implode(',', $notify);
        }

        // Save the settings.
        if (is_null($this->recordid)) {
            // Newly created ECS connection.
            $settings->id = $DB->insert_record('local_campusconnect_ecs', $settings);
        } else {
            $settings->id = $this->recordid;
            $DB->update_record('local_campusconnect_ecs', $settings);
        }

        // Update the local settings.
        $this->set_settings($settings);
    }

    /**
     * Get settings
     *
     * @return stdClass
     *
     */
    public function get_settings() {
        $ret = new stdClass();
        foreach ($this->validsettings as $localname => $dbname) {
            $ret->$localname = $this->$localname;
        }
        return $ret;
    }

    /**
     * Delete
     *
     * @param bool $force
     *
     * @return void
     *
     */
    public function delete($force = false) {
        global $DB;

        if (!is_null($this->recordid)) {
            metadata::delete_ecs_metadata_mappings($this->recordid);
            participantsettings::delete_ecs_participant_settings($this->recordid);
            export::delete_ecs_exports($this->recordid, $force);
            $DB->delete_records('local_campusconnect_ecs', ['id' => $this->recordid]);
            $this->recordid = null;
            $this->auth = -1;
        }
    }

    /**
     * Check if it is time to run a cron update for this ECS
     *
     * @return bool true if time for cron script to run
     */
    public function time_for_cron() {
        if ($this->crontime == 0) {
            return false;
        }
        return (($this->lastcron + $this->crontime) < time());
    }

    /**
     * Save the current time as the lastcron time
     *
     * @return void
     */
    public function update_last_cron() {
        global $DB;

        $lastcron = time();
        if (!is_null($this->recordid)) {
            $DB->set_field('local_campusconnect_ecs', 'lastcron', $lastcron, ['id' => $this->recordid]);
        }
        $this->lastcron = $lastcron;
    }
}
