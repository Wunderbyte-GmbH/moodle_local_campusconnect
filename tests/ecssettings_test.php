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
 * Tests for ECS settings
 *
 * @package   local_campusconnect
 * @copyright 2016 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_campusconnect;

use coding_exception;
use dml_missing_record_exception;
use local_campusconnect\ecssettings;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot.'/local/campusconnect/tests/testbase.php');

/**
 * These tests assume the following set up is already in place with
 * your ECS server:
 * - ECS server running on localhost:3000
 * - participant ids 'unittest1', 'unittest2' and 'unittest3' created
 * - participants are named 'Unit test 1', 'Unit test 2' and 'Unit test 3'
 * - all 3 participants have been added to a community called 'unittest'
 * - none of the participants are members of any other community
 */
/**
 * Class local_campusconnect_ecssettings_test
 *
 * @package    local_campusconnect
 * @copyright 2016 Davo Smith, Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_campusconnect\ecssettings
 */
class ecssettings_test extends campusconnect_base_testcase {

    /** @var array $testdata */
    protected $testdata = [
        'name' => 'test1name',
        'url' => 'http://www.example.com',
        'auth' => ecssettings::AUTH_NONE,
        'ecsauth' => 'test1',
        'importcategory' => null,  // Set via 'setUp' function, below.
        'importrole' => 'student',
        'importperiod' => 6,
    ];

    protected function setUp(): void {
        global $DB;

        parent::setUp();

        // Use the first course category found in the database as the import category, for testing.
        $importcategory = $DB->get_records('course_categories', [], 'id', 'id', 0, 1);
        $importcategory = reset($importcategory);
        $this->testdata['importcategory'] = $importcategory->id;
    }

    public function test_create_delete_settings() {
        $data = $this->testdata;
        $settings = new ecssettings();
        $settings->save_settings($data);

        $id = $settings->get_id();

        // Check the settings have been created successfully.
        $this->assertIsInt($id);
        $this->assertTrue($id > 0);

        // Check the settings are as expected.
        $this->assertEquals('test1name', $settings->get_name());
        $this->assertEquals('http://www.example.com', $settings->get_url());
        $this->assertEquals(ecssettings::AUTH_NONE, $settings->get_auth_type());
        $this->assertEquals('test1', $settings->get_ecs_auth());

        // Load the settings back from the database.
        $settings = new ecssettings($id);

        // Check the loaded settings are as expected.
        $this->assertEquals('test1name', $settings->get_name());
        $this->assertEquals('http://www.example.com', $settings->get_url());
        $this->assertEquals(ecssettings::AUTH_NONE, $settings->get_auth_type());
        $this->assertEquals('test1', $settings->get_ecs_auth());

        // Delete the settings.
        $settings->delete();

        // Check the settings do not exist any more.
        $this->expectException(dml_missing_record_exception::class);
        new ecssettings($id);
    }

    public function test_connect_setting_validation() {
        $settings = new ecssettings();
        $testdata = $this->testdata;

        $data = $testdata;
        unset($data['url']);
        $this->expectException(coding_exception::class);
        $settings->save_settings($data);

        $data = $testdata;
        unset($data['auth']);
        $this->expectException(coding_exception::class);
        $settings->save_settings($data);

        // Test the AUTH_NONE settings.
        $data = $testdata;
        unset($data['ecsauth']);
        $this->expectException(coding_exception::class);
        $settings->save_settings($data);

        $data = $testdata;
        $settings->save_settings($data);
        $this->assertTrue($settings->get_id() > 0);
        $settings->delete();

        // Test the AUTH_HTTP settings.
        $testdata['auth'] = ecssettings::AUTH_HTTP;
        $testdata['httppass'] = 'pass';
        $this->expectException(coding_exception::class);
        $settings->save_settings($testdata);

        unset($testdata['httppass']);
        $testdata['httpuser'] = 'user';
        $this->expectException(coding_exception::class);
        $settings->save_settings($testdata);

        $testdata['httppass'] = 'pass';
        $settings->save_settings($testdata);
        $this->assertTrue($settings->get_id() > 0);
        $settings->delete();

        // Test the AUTH_CERTIFICATE settings.
        $testdata['auth'] = ecssettings::AUTH_CERTIFICATE;
        $testdata['cacertpath'] = 'cacertpath';
        $testdata['certpath'] = 'certpath';
        $testdata['keypath'] = 'keypath';
        $testdata['keypass'] = 'keypass';

        $data = $testdata;
        unset($data['cacertpath']);
        $this->expectException(coding_exception::class);
        $settings->save_settings($data);

        $data = $testdata;
        unset($data['certpath']);
        $this->expectException(coding_exception::class);
        $settings->save_settings($data);

        $data = $testdata;
        unset($data['keypath']);
        $this->expectException(coding_exception::class);
        $settings->save_settings($data);

        $data = $testdata;
        unset($data['keypass']);
        $this->expectException(coding_exception::class);
        $settings->save_settings($data);

        $data = $testdata;
        $settings->save_settings($data);
        $this->assertTrue($settings->get_id() > 0);
        $settings->delete();
    }

    public function test_list_ecs() {
        $startingecs = ecssettings::list_ecs();

        $data = $this->testdata;

        // Add an ECS and test it is in the list.
        $settings1 = new ecssettings();
        $settings1->save_settings($data);
        $id1 = $settings1->get_id();

        $ecslist = array_diff(ecssettings::list_ecs(), $startingecs);
        $this->assertTrue(is_array($ecslist));
        $this->assertEquals([$id1 => 'test1name'], $ecslist);

        // Add a second ECS and test it is also in the list.
        $data['name'] = 'test2name';
        $settings2 = new ecssettings();
        $settings2->save_settings($data);
        $id2 = $settings2->get_id();

        $ecslist = array_diff(ecssettings::list_ecs(), $startingecs);
        $this->assertTrue(is_array($ecslist));
        $this->assertEquals([
            $id1 => 'test1name',
            $id2 => 'test2name',
        ], $ecslist);

        // Delete the first ECS and test the list only contains the second one.
        $settings1->delete();
        $ecslist = array_diff(ecssettings::list_ecs(), $startingecs);
        $this->assertTrue(is_array($ecslist));
        $this->assertEquals([$id2 => 'test2name'], $ecslist);

        // Delete the second ECS and test the list is empty.
        $settings2->delete();
        $ecslist = array_diff(ecssettings::list_ecs(), $startingecs);
        $this->assertTrue(is_array($ecslist));
        $this->assertEquals([], $ecslist);
    }

    public function test_incoming_setting_validation() {
        global $DB;

        $settings = new ecssettings();

        // Disable cron.
        $data = $this->testdata;
        $data['crontime'] = 0;
        $settings->save_settings($data);
        $this->assertFalse($settings->time_for_cron(), 'Cron should be disabled');

        // Enable cron.
        $data['crontime'] = 60;
        $settings->save_settings($data);
        $this->assertTrue($settings->time_for_cron(), 'Cron not ready to start');

        // Try setting 'lastcron' - should be ignored.
        $data['lastcron'] = time() + 100;
        $settings->save_settings($data);
        $this->assertTrue($settings->time_for_cron(), 'Lastcron was incorrectly updated');

        // Update the lastcron properly.
        $settings->update_last_cron();
        $this->assertFalse($settings->time_for_cron(), 'Last cron not correctly updated');

        // Check importcategory validation. Expected coding exception.
        $data = $this->testdata;
        $lastcategory = $DB->get_records('course_categories', [], 'id DESC', 'id', 0, 1);
        $lastcategory = reset($lastcategory);
        $data['importcategory'] = $lastcategory->id + 1;
        $this->expectException(coding_exception::class);
        $settings->save_settings($data);

        // Check importrole validation. Expected coding exception.
        $data = $this->testdata;
        $data['importrole'] = 'thisroledoesnotexist';
        $this->expectException(coding_exception::class);
        $settings->save_settings($data);
    }

    public function test_notify_setting() {
        $settings = new ecssettings();

        // Check notifyXX saving.
        $data = $this->testdata;
        $data['notifyusers'] = '    testa@example.com,   testb@example.com

, testc@example.com';
        $data['notifycontent'] = 'testa@example.com,testb@example.com,testc@example.com';
        $data['notifycourses'] = 'testa@example.com';
        $settings->save_settings($data);
        $this->assertEquals(['testa@example.com', 'testb@example.com', 'testc@example.com'], $settings->get_notify_users());
        $this->assertEquals(['testa@example.com', 'testb@example.com', 'testc@example.com'], $settings->get_notify_content());
        $this->assertEquals(['testa@example.com'], $settings->get_notify_courses());
    }

    public function test_settings_retrieval() {
        $notifyusers = ['testa@example.com', 'testb@example.com'];
        $notifycontent = ['testc@example.com', 'testd@example.com'];
        $notifycourses = ['teste@example.com', 'testf@example.com'];
        $data = array_merge($this->testdata, [
            'crontime' => 60,
            'httpuser' => 'username',
            'httppass' => 'pass',
            'cacertpath' => 'path/to/cacert',
            'certpath' => 'path/to/cert',
            'keypath' => 'path/to/key',
            'keypass' => 'supersecretpass',
            'notifyusers' => implode(',', $notifyusers),
            'notifycontent' => implode(',', $notifycontent),
            'notifycourses' => implode(',', $notifycourses),
        ]);

        $settings = new ecssettings();
        $settings->save_settings($data);
        $id = $settings->get_id();

        // Check that retrieving all settings works.
        $settings = new ecssettings($id);
        $allsettings = $settings->get_settings();
        foreach ($data as $field => $value) {
            $this->assertTrue(isset($allsettings->$field), "$field is not set");
            $this->assertEquals($allsettings->$field, $value);
        }

        // Check each individual setting.
        $this->assertEquals($data['name'], $settings->get_name());
        $this->assertEquals($data['url'], $settings->get_url());
        $this->assertEquals($data['auth'], $settings->get_auth_type());
        $this->assertEquals($data['ecsauth'], $settings->get_ecs_auth());

        $settings->save_settings(['auth' => ecssettings::AUTH_HTTP]);
        $this->assertEquals($data['httpuser'], $settings->get_http_user());
        $this->assertEquals($data['httppass'], $settings->get_http_password());

        $settings->save_settings(['auth' => ecssettings::AUTH_CERTIFICATE]);
        $this->assertEquals($data['cacertpath'], $settings->get_ca_cert_path());
        $this->assertEquals($data['certpath'], $settings->get_client_cert_path());
        $this->assertEquals($data['keypath'], $settings->get_key_path());
        $this->assertEquals($data['keypass'], $settings->get_key_pass());

        $this->assertEquals($data['importcategory'], $settings->get_import_category());
        $this->assertEquals($data['importrole'], $settings->get_import_role());
        $this->assertEquals($data['importperiod'], $settings->get_import_period());

        $this->assertEquals($notifyusers, $settings->get_notify_users());
        $this->assertEquals($notifycontent, $settings->get_notify_content());
        $this->assertEquals($notifycourses, $settings->get_notify_courses());

        $settings->delete();
    }
}
