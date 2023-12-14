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
 * Tests for course import filtering for CampusConnect
 *
 * @package    local_campusconnect
 * @copyright  2014 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_campusconnect;

use advanced_testcase;
use core_course_category;
use local_campusconnect\ecssettings;
use local_campusconnect\filtering;
use local_campusconnect\metadata;

/**
 * Class local_campusconnect_filtering_test
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_campusconnect\filtering
 */
class filtering_test extends advanced_testcase {

    protected function setUp(): void {
        $this->resetAfterTest();
    }

    public function test_check_filter_match() {

        // Test a single 'allwords' filter match.
        $filter = [
            'attribute1' => (object)[
                'allwords' => true,
                'words' => [],
                'createsubdirectories' => false,
            ],
        ];
        $metadata = [
            'attribute1' => 'testvalue',
            'attribute2' => 'fish',
        ];
        $this->assertTrue(filtering::check_filter_match($metadata, $filter));

        // Test matching multiple 'allwords' filters.
        $filter['attribute2'] = (object)[
            'allwords' => true,
            'words' => [],
            'createsubdirectories' => false,
        ];
        $filter['attribute3'] = (object)[
            'allwords' => true,
            'words' => [],
            'createsubdirectories' => false,
        ];
        $this->assertTrue(filtering::check_filter_match($metadata, $filter));

        // Test matching a single 'specific words' filter.
        $filter = [
            'attribute1' => (object)[
                'allwords' => false,
                'words' => ['cat', 'testvalue', 'dog'],
                'createsubdirectories' => false,
            ],
        ];
        $this->assertTrue(filtering::check_filter_match($metadata, $filter));

        // Test matching multiple 'specific words' filters.
        $filter['attribute2'] = (object)[
            'allwords' => false,
            'words' => ['cow', 'horse', 'fish'],
            'createsubdirectories' => false,
        ];
        $this->assertTrue(filtering::check_filter_match($metadata, $filter));

        // Test failing due to missing attribute in metadata.
        $filter['attribute3'] = (object)[
            'allwords' => false,
            'words' => ['lion', 'tiger'],
            'createsubdirectories' => false,
        ];
        $this->assertFalse(filtering::check_filter_match($metadata, $filter));

        // Test failing due to non-matching of attribute.
        $filter = [
            'attribute1' => (object)[
                'allwords' => false,
                'words' => ['cat', 'testvalue', 'dog'],
                'createsubdirectories' => false,
            ],
            'attribute2' => (object)[
                'allwords' => false,
                'words' => ['cow', 'horse', 'fishes'],
                'createsubdirectories' => false,
            ],
        ];
        $this->assertFalse(filtering::check_filter_match($metadata, $filter));

        // Test matching array attribute.
        $filter = [
            'attribute1' => (object)[
                'allwords' => false,
                'words' => ['cat', 'testvalue', 'dog'],
                'createsubdirectories' => false,
            ],
        ];
        $metadata = [
            'attribute1' => ['big', 'small', 'testvalue'],
            'attribute2' => ['fish', 'whale', 'mermaid'],
        ];
        $this->assertTrue(filtering::check_filter_match($metadata, $filter));

        // Test failing to match array attribute.
        $filter['attribute2'] = (object)[
            'allwords' => false,
            'words' => ['lion', 'tiger', 'bear'],
            'createsubdirectories' => false,
        ];
        $this->assertFalse(filtering::check_filter_match($metadata, $filter));
    }

    public function test_find_or_create_category() {

        $basecategory = $this->getDataGenerator()->create_category(['name' => 'Base category']);

        // Test creating the course directly in the parent category.
        $filter = [
            'attribute1' => (object)[
                'allwords' => true,
                'words' => [],
                'createsubdirectories' => false,
            ],
        ];
        $metadata = [
            'attribute1' => 'testvalue',
            'attribute2' => 'fish',
        ];
        $categoryids = filtering::find_or_create_categories($metadata, $filter, $basecategory->id);
        $this->assertEquals([$basecategory->id], $categoryids);

        // Test creating the course directly in the parent category (with multiple attributes).
        $filter = [
            'attribute1' => (object)[
                'allwords' => true,
                'words' => [],
                'createsubdirectories' => false,
            ],
            'attribute2' => (object)[
                'allwords' => false,
                'words' => ['fish', 'cat', 'dog'],
                'createsubdirectories' => false,
            ],
        ];
        $categoryids = filtering::find_or_create_categories($metadata, $filter, $basecategory->id);
        $this->assertEquals([$basecategory->id], $categoryids);

        // Test creating course in subcategory of parent category.
        $filter = [
            'attribute1' => (object)[
                'allwords' => true,
                'words' => [],
                'createsubdirectories' => true,
            ],
        ];
        $categoryids = filtering::find_or_create_categories($metadata, $filter, $basecategory->id);
        $this->assertCount(1, $categoryids);
        $categoryid = reset($categoryids);
        $newcat = core_course_category::get($categoryid);
        $this->assertEquals($metadata['attribute1'], $newcat->name);
        $parents = $newcat->get_parents();
        $parent = core_course_category::get(array_pop($parents));
        $this->assertEquals($basecategory->id, $parent->id);

        // Test creating course in two levels of subcategories.
        $filter = [
            'attribute1' => (object)[
                'allwords' => true,
                'words' => [],
                'createsubdirectories' => true,
            ],
            'attribute2' => (object)[
                'allwords' => false,
                'words' => ['fish', 'cat', 'dog'],
                'createsubdirectories' => true,
            ],
        ];
        $categoryids = filtering::find_or_create_categories($metadata, $filter, $basecategory->id);
        $this->assertCount(1, $categoryids);
        $categoryid = reset($categoryids);
        $newcat = core_course_category::get($categoryid);
        $this->assertEquals($metadata['attribute2'], $newcat->name);
        $parents = $newcat->get_parents();
        $parent = core_course_category::get(array_pop($parents));
        $this->assertEquals($metadata['attribute1'], $parent->name);
        $parent = core_course_category::get(array_pop($parents));
        $this->assertEquals($basecategory->id, $parent->id);

        // Test creating course in subcategory from 2nd filter only.
        $filter = [
            'attribute1' => (object)[
                'allwords' => true,
                'words' => [],
                'createsubdirectories' => false,
            ],
            'attribute2' => (object)[
                'allwords' => false,
                'words' => ['fish', 'cat', 'dog'],
                'createsubdirectories' => true,
            ],
        ];
        $categoryids = filtering::find_or_create_categories($metadata, $filter, $basecategory->id);
        $this->assertCount(1, $categoryids);
        $categoryid = reset($categoryids);
        $newcat = core_course_category::get($categoryid);
        $this->assertEquals($metadata['attribute2'], $newcat->name);
        $parents = $newcat->get_parents();
        $parent = core_course_category::get(array_pop($parents));
        $this->assertEquals($basecategory->id, $parent->id);
    }

    public function test_complex_metadata() {
        $metadata = (object)[
            'title' => 'Test title',
            'organisationalUnits' => [
                (object)['id' => 5, 'title' => 'test1'],
                (object)['id' => 6, 'title' => 'test2'],
            ],
            'groups' => [
                (object)[
                    'id' => 'group1',
                    'title' => 'group1title',
                    'lecturers' => [
                        (object)['firstName' => 'Fred', 'lastName' => 'Bloggs'],
                        (object)['firstName' => 'Gary', 'lastName' => 'Barlow'],
                    ],
                ],
            ],
        ];

        $ecs = new ecssettings();
        $ecs->save_settings([
                                'url' => 'http://localhost:3000',
                                'auth' => ecssettings::AUTH_NONE,
                                'ecsauth' => 'unittest1',
                                'importcategory' => 0,
                                'importrole' => 'student',
                            ]);
        $meta = new metadata($ecs, false);
        $metadata = $meta->flatten_remote_data($metadata, false);

        // Check for matching organisationalUnits field.
        $filter = [
            'organisationalUnits' => (object)[
                'allwords' => false,
                'words' => ['test1', 'test2'],
                'createsubdirectories' => false,
            ],
        ];
        $this->assertTrue(filtering::check_filter_match($metadata, $filter));

        // Check for non-matching organisationalUnits field.
        $filter = [
            'organisationalUnits' => (object)[
                'allwords' => false,
                'words' => ['test3', 'test4'],
                'createsubdirectories' => false,
            ],
        ];
        $this->assertFalse(filtering::check_filter_match($metadata, $filter));

        // Check for matching groups field.
        $filter = [
            'groups' => (object)[
                'allwords' => false,
                'words' => ['group1title', 'group2title'],
                'createsubdirectories' => false,
            ],
        ];
        $this->assertTrue(filtering::check_filter_match($metadata, $filter));

        // Check for non-matching groups field.
        $filter = [
            'groups' => (object)[
                'allwords' => false,
                'words' => ['group2title'],
                'createsubdirectories' => false,
            ],
        ];
        $this->assertFalse(filtering::check_filter_match($metadata, $filter));

        // Check for matching groups_lecturers field.
        $filter = [
            'groups_lecturers' => (object)[
                'allwords' => false,
                'words' => ['Fred Bloggs', 'Robbie Williams'],
                'createsubdirectories' => false,
            ],
        ];
        $this->assertTrue(filtering::check_filter_match($metadata, $filter));

        // Check for non-matching groups_lecturers field.
        $filter = [
            'groups_lecturers' => (object)[
                'allwords' => false,
                'words' => ['Robbie Williams'],
                'createsubdirectories' => false,
            ],
        ];
        $this->assertFalse(filtering::check_filter_match($metadata, $filter));
    }
}
