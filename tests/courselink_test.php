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

namespace local_campusconnect;

use advanced_testcase;
use stdClass;

/**
 * Tests for the category of imported course links (category assignment rules).
 *
 * @package    local_campusconnect
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_campusconnect\courselink
 */
final class courselink_test extends advanced_testcase {
    /** The participant that sends the course links. */
    private const MID = 37;

    /** @var ecssettings */
    private $ecs;

    /** @var int */
    private $importcategoryid;

    /**
     * Create an ECS and a participant we import course links from.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();

        $this->importcategoryid = (int)$this->getDataGenerator()->create_category(['name' => 'Import'])->id;
        $this->ecs = new ecssettings();
        $this->ecs->save_settings((object)[
            'name' => 'Test ECS',
            'url' => 'http://localhost/ecs',
            'auth' => ecssettings::AUTH_NONE,
            'ecsauth' => 'test',
            'importcategory' => $this->importcategoryid,
            'importrole' => 'student',
        ]);
        $DB->insert_record('local_campusconnect_part', (object)[
            'ecsid' => $this->ecs->get_id(),
            'mid' => self::MID,
            'import' => 1,
            'importtype' => participantsettings::IMPORT_LINK,
            'active' => 1,
            'displayname' => 'Partner',
        ]);

        // The course creation prints messages about the guest enrolment.
        $this->expectOutputRegex('/.*/s');
    }

    /**
     * Transfer details of a course link sent by the participant.
     *
     * @param int $resourceid
     * @return details
     */
    private function make_details(int $resourceid): details {
        return new details((object)[
            'url' => event::RES_COURSELINK . '/' . $resourceid,
            'receivers' => [(object)['mid' => 1, 'itsyou' => true]],
            'senders' => [(object)['mid' => self::MID]],
            'owner' => (object)['itsyou' => false],
            'content_type' => 'application/json',
        ]);
    }

    /**
     * Course link data as sent by the ECS.
     *
     * @param string $coursetype
     * @return stdClass
     */
    private function make_courselink(string $coursetype): stdClass {
        return (object)[
            'id' => 'course-1',
            'title' => 'Mathematics 1',
            'url' => 'http://partner.example.com/course/1',
            'courseType' => $coursetype,
        ];
    }

    /**
     * Add a fixed values rule on the course type.
     *
     * @param string $coursetype
     * @return int the category of the rule
     */
    private function add_rule(string $coursetype): int {
        $categoryid = (int)$this->getDataGenerator()->create_category(['name' => $coursetype])->id;
        categoryrule::save((object)[
            'ecsid' => $this->ecs->get_id(),
            'categoryid' => $categoryid,
            'mappingtype' => categoryrule::TYPE_FIXED,
            'attribute' => 'courseType',
            'fixedvalues' => $coursetype,
        ]);
        return $categoryid;
    }

    /**
     * Get the category of the course of the course link.
     *
     * @param int $resourceid
     * @return int
     */
    private function get_course_category(int $resourceid): int {
        global $DB;
        $link = courselink::get_by_resourceid($resourceid, $this->ecs->get_id());
        return (int)$DB->get_field('course', 'category', ['id' => $link->courseid], MUST_EXIST);
    }

    /**
     * A new course link goes into the category of the matching rule.
     */
    public function test_create_uses_rule_category(): void {
        $seminarcat = $this->add_rule('Seminar');

        courselink::create(1, $this->ecs, $this->make_courselink('Seminar'), $this->make_details(1));

        $this->assertSame($seminarcat, $this->get_course_category(1));
    }

    /**
     * A new course link without a matching rule goes into the import category.
     */
    public function test_create_without_match_uses_import_category(): void {
        $this->add_rule('Seminar');

        courselink::create(1, $this->ecs, $this->make_courselink('Vorlesung'), $this->make_details(1));

        $this->assertSame($this->importcategoryid, $this->get_course_category(1));
    }

    /**
     * An update moves the course into the category of the matching rule.
     */
    public function test_update_moves_course_when_rule_matches(): void {
        courselink::create(1, $this->ecs, $this->make_courselink('Seminar'), $this->make_details(1));
        $this->assertSame($this->importcategoryid, $this->get_course_category(1));

        $seminarcat = $this->add_rule('Seminar');
        courselink::update(1, $this->ecs, $this->make_courselink('Seminar'), $this->make_details(1));

        $this->assertSame($seminarcat, $this->get_course_category(1));
    }

    /**
     * An update without a matching rule leaves the course where it is.
     */
    public function test_update_without_match_keeps_category(): void {
        global $DB;
        $this->add_rule('Seminar');
        courselink::create(1, $this->ecs, $this->make_courselink('Vorlesung'), $this->make_details(1));

        // The admin moves the course to another category.
        $othercat = (int)$this->getDataGenerator()->create_category(['name' => 'Other'])->id;
        $courseid = courselink::get_by_resourceid(1, $this->ecs->get_id())->courseid;
        $DB->set_field('course', 'category', $othercat, ['id' => $courseid]);

        courselink::update(1, $this->ecs, $this->make_courselink('Vorlesung'), $this->make_details(1));

        $this->assertSame($othercat, $this->get_course_category(1));
    }

    /**
     * A course that was deleted in Moodle is created again in the category of the matching rule.
     */
    public function test_update_recreates_deleted_course_in_rule_category(): void {
        courselink::create(1, $this->ecs, $this->make_courselink('Seminar'), $this->make_details(1));
        delete_course(courselink::get_by_resourceid(1, $this->ecs->get_id())->courseid, false);

        $seminarcat = $this->add_rule('Seminar');
        courselink::update(1, $this->ecs, $this->make_courselink('Seminar'), $this->make_details(1));

        $this->assertSame($seminarcat, $this->get_course_category(1));
    }

    /**
     * Deleting an ECS deletes its rules.
     */
    public function test_delete_ecs_deletes_rules(): void {
        $this->add_rule('Seminar');
        $ecsid = $this->ecs->get_id();

        $this->ecs->delete();

        $this->assertCount(0, categoryrule::get_rules($ecsid));
    }
}
