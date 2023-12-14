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

use local_campusconnect\event;
use local_campusconnect\receivequeue;
use local_campusconnect\campusconnect_base_testcase;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot.'/local/campusconnect/tests/testbase.php');
/**
 * Class local_campusconnect_receivequeue_test
 *
 * @package   local_campusconnect
 * @copyright 2016 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_campusconnect\receivequeue
 */
class receivequeue_test extends campusconnect_base_testcase {
    protected $resources = [];
    /** @var receivequeue */
    protected $queue = null;


    protected function setUp(): void {
        parent::setUp();

        // Data for test resources to create.
        $this->resources[1] = (object)['url' => 'http://www.example.com/test123',
                                            'title' => 'Course from ECS',
                                            'organization' => 'Synergy Learning',
                                            'lang' => 'en',
                                            'semesterHours' => '5',
                                            'courseID' => 'course5:220',
                                            'term' => 'WS 06/07',
                                            'credits' => '10',
                                            'status' => 'online',
                                            'courseType' => 'Vorlesung'];

        $this->resources[2] = (object)['url' => 'http://www.example.com/test456'];

        // General settings used by the tests.
        $this->queue = new receivequeue();
    }

    public function tearDown(): void {
        $this->clear_ecs_resources(event::RES_COURSELINK);
        $this->connect = [];
        $this->mid = [];
    }

    public function test_update_from_ecs_empty() {
        global $DB;

        // Check the update queue - no updates expected.
        $this->assertEmpty($DB->get_records('local_campusconnect_eventin'));
        $this->queue->update_from_ecs($this->connect[2]);
        $this->assertEmpty($DB->get_records('local_campusconnect_eventin'));
    }

    public function test_update_from_ecs_create_update_delete() {
        // Add a resource to the community.
        $eid = $this->connect[1]->add_resource(event::RES_COURSELINK, $this->resources[1], $this->community);

        // Set up the expectations - 3 records inserted, none deleted/updated.
        $expecteddata = [];
        $expecteddata[0] = (object)['type' => 'campusconnect/courselinks',
                                         'resourceid' => "$eid",
                                         'serverid' => $this->connect[2]->get_ecs_id(),
                                         'status' => event::STATUS_CREATED];
        $expecteddata[1] = clone $expecteddata[0];
        $expecteddata[1]->status = event::STATUS_UPDATED;
        $expecteddata[2] = clone $expecteddata[0];
        $expecteddata[2]->status = event::STATUS_DESTROYED;

        // Check there is an event in the queue on the server.
        $result = $this->connect[2]->read_event_fifo();
        $this->assertTrue(is_array($result));
        $this->assertNotEmpty($result);

        // Check the event is transferred correctly into the queue.
        // Expect first 'insert_record' call.
        $this->queue->update_from_ecs($this->connect[2]);

        // Check there are no events in the queue any more.
        $result = $this->connect[2]->read_event_fifo();
        $this->assertEmpty($result);

        // Update the resource.
        $this->connect[1]->update_resource($eid, event::RES_COURSELINK, $this->resources[2], $this->community);

        // Check there is an event in the queue on the server.
        $result = $this->connect[2]->read_event_fifo();
        $this->assertTrue(is_array($result));
        $this->assertNotEmpty($result);

        // Check the event is transferred correctly into the queue.
        // Expect second 'insert_record' call.
        $this->queue->update_from_ecs($this->connect[2]);

        // Check there are no events in the queue any more.
        $result = $this->connect[2]->read_event_fifo();
        $this->assertEmpty($result);

        // Delete the resource.
        $this->connect[1]->delete_resource($eid, event::RES_COURSELINK);

        // Check there is an event in the queue on the server.
        $result = $this->connect[2]->read_event_fifo();
        $this->assertTrue(is_array($result));
        $this->assertNotEmpty($result);

        // Check the event is transferred correctly into the queue.
        // Expect third 'insert_record' call.
        $this->queue->update_from_ecs($this->connect[2]);

        // Check there are no events in the queue any more.
        $result = $this->connect[2]->read_event_fifo();
        $this->assertEmpty($result);
    }

    public function test_update_from_ecs_create_two() {
        global $DB;

        // Create two resources.
        $eid = $this->connect[1]->add_resource(event::RES_COURSELINK, $this->resources[1], $this->community);
        $eid2 = $this->connect[1]->add_resource(event::RES_COURSELINK, $this->resources[2], $this->community);

        // Check there is at least one event in the queue on the server.
        $result = $this->connect[2]->read_event_fifo();
        $this->assertTrue(is_array($result));
        $this->assertNotEmpty($result);

        // Check the event is transferred correctly into the queue.
        $this->queue->update_from_ecs($this->connect[2]);

        $records = $DB->get_records('local_campusconnect_eventin', [], 'id');
        $record = array_shift($records);
        unset($record->id, $record->failcount);
        $this->assertEquals((object)['type' => 'campusconnect/courselinks',
                                          'resourceid' => "$eid",
                                          'serverid' => $this->connect[2]->get_ecs_id(),
                                          'status' => event::STATUS_CREATED], $record);
        $record = array_shift($records);
        unset($record->id, $record->failcount);
        $this->assertEquals((object)['type' => 'campusconnect/courselinks',
                                          'resourceid' => "$eid2",
                                          'serverid' => $this->connect[2]->get_ecs_id(),
                                          'status' => event::STATUS_CREATED], $record);

        // Check there are no events in the queue any more.
        $result = $this->connect[2]->read_event_fifo();
        $this->assertEmpty($result);
    }
}
