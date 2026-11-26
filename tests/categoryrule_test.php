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
use coding_exception;
use DateTime;
use DateTimeZone;
use stdClass;

/**
 * Tests for the rules that assign imported course links to categories.
 *
 * @package    local_campusconnect
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_campusconnect\categoryrule
 */
final class categoryrule_test extends advanced_testcase {
    /** Timezone used for all date range tests. */
    private const TIMEZONE = 'Europe/Berlin';

    /**
     * Create an ECS connection for the tests.
     *
     * @return ecssettings
     */
    private function create_ecs(): ecssettings {
        $category = $this->getDataGenerator()->create_category();
        $ecs = new ecssettings();
        $ecs->save_settings((object)[
            'name' => 'Test ECS',
            'url' => 'http://localhost/ecs',
            'auth' => ecssettings::AUTH_NONE,
            'ecsauth' => 'test',
            'importcategory' => $category->id,
            'importrole' => 'student',
        ]);
        return $ecs;
    }

    /**
     * Build a rule object (not saved).
     *
     * @param int $mappingtype
     * @param array $fields
     * @return stdClass
     */
    private function make_rule(int $mappingtype, array $fields = []): stdClass {
        return (object)array_merge([
            'id' => 0,
            'ecsid' => 0,
            'categoryid' => 0,
            'mappingtype' => $mappingtype,
            'attribute' => '',
            'fixedvalues' => null,
            'datefrom' => null,
            'dateto' => null,
            'objecttype' => null,
            'sortorder' => 0,
        ], $fields);
    }

    /**
     * Timestamp of the given local time in the test timezone.
     *
     * @param string $time
     * @return int
     */
    private static function ts(string $time): int {
        return (new DateTime($time, new DateTimeZone(self::TIMEZONE)))->getTimestamp();
    }

    /**
     * Fixed values: one of several comma-separated values must be equal to the attribute value.
     */
    public function test_fixed_values_match_one_of_the_values(): void {
        $rule = $this->make_rule(categoryrule::TYPE_FIXED, [
            'attribute' => categoryrule::ATTRIBUTE_PARTICIPANT,
            'fixedvalues' => '5_37,5_40',
        ]);

        $this->assertTrue(categoryrule::matches($rule, [categoryrule::ATTRIBUTE_PARTICIPANT => '5_37']));
        $this->assertTrue(categoryrule::matches($rule, [categoryrule::ATTRIBUTE_PARTICIPANT => '5_40']));
        $this->assertFalse(categoryrule::matches($rule, [categoryrule::ATTRIBUTE_PARTICIPANT => '5_41']));
        $this->assertFalse(categoryrule::matches($rule, [categoryrule::ATTRIBUTE_PARTICIPANT => '15_37']));
    }

    /**
     * Fixed values: spaces around the values and upper/lower case are ignored.
     */
    public function test_fixed_values_ignore_spaces_and_case(): void {
        $rule = $this->make_rule(categoryrule::TYPE_FIXED, [
            'attribute' => 'courseType',
            'fixedvalues' => ' Seminar ,  Vorlesung ',
        ]);

        $this->assertTrue(categoryrule::matches($rule, ['courseType' => 'seminar']));
        $this->assertTrue(categoryrule::matches($rule, ['courseType' => ' VORLESUNG ']));
        $this->assertFalse(categoryrule::matches($rule, ['courseType' => 'Übung']));
        $this->assertFalse(categoryrule::matches($rule, ['courseType' => 'Seminar Vorlesung']));
    }

    /**
     * Fixed values: for a list attribute, one matching item is enough.
     */
    public function test_fixed_values_list_attribute(): void {
        $rule = $this->make_rule(categoryrule::TYPE_FIXED, [
            'attribute' => categoryrule::ATTRIBUTE_COMMUNITY,
            'fixedvalues' => 'eucor',
        ]);

        $this->assertTrue(categoryrule::matches($rule, [categoryrule::ATTRIBUTE_COMMUNITY => ['Other', 'EUCOR']]));
        $this->assertFalse(categoryrule::matches($rule, [categoryrule::ATTRIBUTE_COMMUNITY => ['Other']]));
        $this->assertFalse(categoryrule::matches($rule, [categoryrule::ATTRIBUTE_COMMUNITY => []]));
    }

    /**
     * Fixed values: a missing or empty attribute never matches.
     */
    public function test_fixed_values_missing_attribute(): void {
        $rule = $this->make_rule(categoryrule::TYPE_FIXED, [
            'attribute' => 'term',
            'fixedvalues' => 'WS 2026/27',
        ]);

        $this->assertFalse(categoryrule::matches($rule, []));
        $this->assertFalse(categoryrule::matches($rule, ['term' => null]));
        $this->assertFalse(categoryrule::matches($rule, ['term' => '']));
    }

    /**
     * Data provider for test_date_range_limits.
     *
     * @return array
     */
    public static function date_range_provider(): array {
        return [
            'on the from date, 00:00:00' => ['2026-10-16 00:00:00', true],
            'one second before the from date' => ['2026-10-15 23:59:59', false],
            'in the middle' => ['2027-01-10 12:00:00', true],
            'on the to date, 23:59:59' => ['2027-04-16 23:59:59', true],
            'one second after the to date' => ['2027-04-17 00:00:00', false],
        ];
    }

    /**
     * Date range: both the from and the to day are included.
     *
     * @dataProvider date_range_provider
     * @param string $coursedate
     * @param bool $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('date_range_provider')]
    public function test_date_range_limits(string $coursedate, bool $expected): void {
        $rule = $this->make_rule(categoryrule::TYPE_DATERANGE, [
            'attribute' => 'firstDate',
            'datefrom' => self::ts('2026-10-16 00:00:00'),
            'dateto' => self::ts('2027-04-16 23:59:59'),
        ]);

        $this->assertSame($expected, categoryrule::matches($rule, ['firstDate' => self::ts($coursedate)]));
    }

    /**
     * Date range: a course without a (valid) date never matches.
     */
    public function test_date_range_without_date(): void {
        $rule = $this->make_rule(categoryrule::TYPE_DATERANGE, [
            'attribute' => 'firstDate',
            'datefrom' => self::ts('2026-10-16 00:00:00'),
            'dateto' => self::ts('2027-04-16 23:59:59'),
        ]);

        $this->assertFalse(categoryrule::matches($rule, []));
        $this->assertFalse(categoryrule::matches($rule, ['firstDate' => null]));
        $this->assertFalse(categoryrule::matches($rule, ['firstDate' => false])); // Result of strtotime() for a bad date.
        $this->assertFalse(categoryrule::matches($rule, ['firstDate' => 'not a date']));
    }

    /**
     * The end of a day is 23:59:59 in the user's timezone, also on a day with a DST change.
     */
    public function test_end_of_day(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->setTimezone(self::TIMEZONE);

        $this->assertSame(self::ts('2027-04-16 23:59:59'), categoryrule::end_of_day(self::ts('2027-04-16 00:00:00')));
        // 25 October 2026 has 25 hours in Europe/Berlin.
        $this->assertSame(self::ts('2026-10-25 23:59:59'), categoryrule::end_of_day(self::ts('2026-10-25 00:00:00')));
    }

    /**
     * Object type: the value must be the selected type.
     */
    public function test_object_type(): void {
        $rule = $this->make_rule(categoryrule::TYPE_OBJECTTYPE, [
            'objecttype' => categoryrule::OBJECTTYPE_COURSELINK,
        ]);

        $this->assertTrue(categoryrule::matches($rule, [categoryrule::ATTRIBUTE_OBJECTTYPE => 'courselink']));
        $this->assertFalse(categoryrule::matches($rule, [categoryrule::ATTRIBUTE_OBJECTTYPE => 'course']));
        $this->assertFalse(categoryrule::matches($rule, []));
    }

    /**
     * The first matching rule decides the category.
     */
    public function test_find_category_first_match_wins(): void {
        $this->resetAfterTest();
        $cat1 = $this->getDataGenerator()->create_category();
        $cat2 = $this->getDataGenerator()->create_category();
        $cat3 = $this->getDataGenerator()->create_category();

        $rules = [
            $this->make_rule(categoryrule::TYPE_FIXED, [
                'categoryid' => $cat1->id, 'attribute' => 'courseType', 'fixedvalues' => 'Seminar',
            ]),
            $this->make_rule(categoryrule::TYPE_FIXED, [
                'categoryid' => $cat2->id, 'attribute' => categoryrule::ATTRIBUTE_PARTICIPANT, 'fixedvalues' => '5_37',
            ]),
            $this->make_rule(categoryrule::TYPE_OBJECTTYPE, [
                'categoryid' => $cat3->id, 'objecttype' => categoryrule::OBJECTTYPE_COURSELINK,
            ]),
        ];
        $values = [
            'courseType' => 'Vorlesung',
            categoryrule::ATTRIBUTE_PARTICIPANT => '5_37',
            categoryrule::ATTRIBUTE_OBJECTTYPE => categoryrule::OBJECTTYPE_COURSELINK,
        ];

        // Rule 1 does not match, rule 2 and rule 3 match: rule 2 wins.
        $this->assertSame((int)$cat2->id, categoryrule::find_category($rules, $values));
    }

    /**
     * A rule whose category was deleted is skipped.
     */
    public function test_find_category_skips_deleted_category(): void {
        $this->resetAfterTest();
        $deleted = $this->getDataGenerator()->create_category();
        $other = $this->getDataGenerator()->create_category();
        $deletedid = (int)$deleted->id;
        $deleted->delete_full(false);

        $rules = [
            $this->make_rule(categoryrule::TYPE_OBJECTTYPE, [
                'categoryid' => $deletedid, 'objecttype' => categoryrule::OBJECTTYPE_COURSELINK,
            ]),
            $this->make_rule(categoryrule::TYPE_OBJECTTYPE, [
                'categoryid' => $other->id, 'objecttype' => categoryrule::OBJECTTYPE_COURSELINK,
            ]),
        ];
        $values = [categoryrule::ATTRIBUTE_OBJECTTYPE => categoryrule::OBJECTTYPE_COURSELINK];

        $this->assertSame((int)$other->id, categoryrule::find_category($rules, $values));
    }

    /**
     * No rules or no matching rule: null, so the caller uses the import category.
     */
    public function test_find_category_no_match(): void {
        $this->resetAfterTest();
        $cat = $this->getDataGenerator()->create_category();
        $rules = [
            $this->make_rule(categoryrule::TYPE_FIXED, [
                'categoryid' => $cat->id, 'attribute' => 'courseType', 'fixedvalues' => 'Seminar',
            ]),
        ];

        $this->assertNull(categoryrule::find_category([], ['courseType' => 'Seminar']));
        $this->assertNull(categoryrule::find_category($rules, ['courseType' => 'Vorlesung']));
    }

    /**
     * Validation returns an error code for each wrong field.
     */
    public function test_validate(): void {
        $this->resetAfterTest();
        $ecs = $this->create_ecs();
        $cat = $this->getDataGenerator()->create_category();
        $base = ['ecsid' => $ecs->get_id(), 'categoryid' => $cat->id];

        // Valid rules.
        $valid = [
            $this->make_rule(categoryrule::TYPE_FIXED, $base + ['attribute' => 'courseType', 'fixedvalues' => 'Seminar']),
            $this->make_rule(categoryrule::TYPE_FIXED, $base + [
                'attribute' => categoryrule::ATTRIBUTE_PARTICIPANT, 'fixedvalues' => '5_37',
            ]),
            $this->make_rule(categoryrule::TYPE_FIXED, $base + [
                'attribute' => categoryrule::ATTRIBUTE_COMMUNITY, 'fixedvalues' => 'EUCOR',
            ]),
            $this->make_rule(categoryrule::TYPE_DATERANGE, $base + [
                'attribute' => 'firstDate', 'datefrom' => 1000, 'dateto' => 1000,
            ]),
            $this->make_rule(categoryrule::TYPE_OBJECTTYPE, $base + ['objecttype' => categoryrule::OBJECTTYPE_COURSELINK]),
        ];
        foreach ($valid as $rule) {
            $this->assertSame([], categoryrule::validate($rule));
        }

        // Wrong ECS and category.
        $rule = $this->make_rule(categoryrule::TYPE_OBJECTTYPE, [
            'ecsid' => $ecs->get_id() + 100, 'categoryid' => $cat->id + 100,
            'objecttype' => categoryrule::OBJECTTYPE_COURSELINK,
        ]);
        $this->assertSame(['ecsid' => 'ecs', 'categoryid' => 'category'], categoryrule::validate($rule));

        // Wrong mapping type.
        $this->assertSame(['mappingtype' => 'mappingtype'], categoryrule::validate($this->make_rule(99, $base)));

        // Fixed values: date attribute, unknown attribute, no values.
        $rule = $this->make_rule(categoryrule::TYPE_FIXED, $base + ['attribute' => 'firstDate', 'fixedvalues' => 'x']);
        $this->assertSame(['attribute' => 'attribute'], categoryrule::validate($rule));
        $rule = $this->make_rule(categoryrule::TYPE_FIXED, $base + ['attribute' => 'unknown', 'fixedvalues' => 'x']);
        $this->assertSame(['attribute' => 'attribute'], categoryrule::validate($rule));
        $rule = $this->make_rule(categoryrule::TYPE_FIXED, $base + ['attribute' => 'courseType', 'fixedvalues' => ' , ']);
        $this->assertSame(['fixedvalues' => 'fixedvalues'], categoryrule::validate($rule));

        // Date range: text attribute, missing dates, from after to.
        $rule = $this->make_rule(categoryrule::TYPE_DATERANGE, $base + [
            'attribute' => 'courseType', 'datefrom' => 1000, 'dateto' => 2000,
        ]);
        $this->assertSame(['attribute' => 'attribute'], categoryrule::validate($rule));
        $rule = $this->make_rule(categoryrule::TYPE_DATERANGE, $base + ['attribute' => 'firstDate']);
        $this->assertSame(['datefrom' => 'daterequired'], categoryrule::validate($rule));
        $rule = $this->make_rule(categoryrule::TYPE_DATERANGE, $base + [
            'attribute' => 'firstDate', 'datefrom' => 2000, 'dateto' => 1999,
        ]);
        $this->assertSame(['dateto' => 'daterange'], categoryrule::validate($rule));

        // Object type: unknown type.
        $rule = $this->make_rule(categoryrule::TYPE_OBJECTTYPE, $base + ['objecttype' => 'course']);
        $this->assertSame(['objecttype' => 'objecttype'], categoryrule::validate($rule));
    }

    /**
     * Fixed values: a value that another rule of the same ECS with the same attribute already uses is not valid.
     */
    public function test_validate_shared_values(): void {
        $this->resetAfterTest();
        $ecs = $this->create_ecs();
        $otherecs = $this->create_ecs();
        $cat1 = $this->getDataGenerator()->create_category();
        $cat2 = $this->getDataGenerator()->create_category();
        $base = ['ecsid' => $ecs->get_id(), 'categoryid' => $cat2->id, 'attribute' => 'courseType'];

        $fixedid = categoryrule::save($this->make_rule(categoryrule::TYPE_FIXED, [
            'ecsid' => $ecs->get_id(), 'categoryid' => $cat1->id, 'attribute' => 'courseType', 'fixedvalues' => 'Seminar, Übung',
        ]));

        // Same values, one shared value, other case: not valid. The shared values are returned as written in the new rule.
        $cases = [
            'übung,SEMINAR' => ['übung', 'SEMINAR'],
            'Vorlesung, seminar' => ['seminar'],
            ' Seminar , Vorlesung , SEMINAR ' => ['Seminar'],
        ];
        foreach ($cases as $values => $shared) {
            $rule = $this->make_rule(categoryrule::TYPE_FIXED, $base + ['fixedvalues' => $values]);
            $this->assertSame(['fixedvalues' => 'sharedvalues'], categoryrule::validate($rule), $values);
            $this->assertSame($shared, categoryrule::get_shared_values($rule), $values);
        }

        // Other values, another attribute or another ECS: valid.
        $rule = $this->make_rule(categoryrule::TYPE_FIXED, $base + ['fixedvalues' => 'Vorlesung, Seminar 2']);
        $this->assertSame([], categoryrule::validate($rule));
        $rule = $this->make_rule(categoryrule::TYPE_FIXED, ['attribute' => 'term'] + $base + ['fixedvalues' => 'Seminar']);
        $this->assertSame([], categoryrule::validate($rule));
        $rule = $this->make_rule(categoryrule::TYPE_FIXED, ['ecsid' => $otherecs->get_id()] + $base + ['fixedvalues' => 'Seminar']);
        $this->assertSame([], categoryrule::validate($rule));

        // A rule does not share values with itself (editing it).
        $rule = categoryrule::get_rule($fixedid);
        $rule->categoryid = $cat2->id;
        $this->assertSame([], categoryrule::validate($rule));

        // A rule with a shared value is not saved.
        $this->expectException(coding_exception::class);
        categoryrule::save($this->make_rule(categoryrule::TYPE_FIXED, $base + ['fixedvalues' => 'Vorlesung, Übung']));
    }

    /**
     * Date range and object type: an exact duplicate of an existing rule of the same ECS is not valid.
     * Overlapping date ranges are valid.
     */
    public function test_validate_duplicate(): void {
        $this->resetAfterTest();
        $ecs = $this->create_ecs();
        $otherecs = $this->create_ecs();
        $cat1 = $this->getDataGenerator()->create_category();
        $cat2 = $this->getDataGenerator()->create_category();
        $base = ['ecsid' => $ecs->get_id(), 'categoryid' => $cat2->id];

        $daterangeid = categoryrule::save($this->make_rule(categoryrule::TYPE_DATERANGE, [
            'ecsid' => $ecs->get_id(), 'categoryid' => $cat1->id, 'attribute' => 'firstDate', 'datefrom' => 1000, 'dateto' => 2000,
        ]));
        categoryrule::save($this->make_rule(categoryrule::TYPE_OBJECTTYPE, [
            'ecsid' => $ecs->get_id(), 'categoryid' => $cat1->id, 'objecttype' => categoryrule::OBJECTTYPE_COURSELINK,
        ]));

        // Date range: same attribute and same dates is a duplicate, other dates are not.
        $rule = $this->make_rule(categoryrule::TYPE_DATERANGE, $base + [
            'attribute' => 'firstDate', 'datefrom' => 1000, 'dateto' => 2000,
        ]);
        $this->assertSame(['datefrom' => 'duplicate'], categoryrule::validate($rule));
        $rule = $this->make_rule(categoryrule::TYPE_DATERANGE, $base + [
            'attribute' => 'firstDate', 'datefrom' => 1000, 'dateto' => 2001,
        ]);
        $this->assertSame([], categoryrule::validate($rule));

        // Object type: the same type is a duplicate.
        $rule = $this->make_rule(categoryrule::TYPE_OBJECTTYPE, $base + ['objecttype' => categoryrule::OBJECTTYPE_COURSELINK]);
        $this->assertSame(['objecttype' => 'duplicate'], categoryrule::validate($rule));

        // The same rule in another ECS is not a duplicate.
        $rule = $this->make_rule(categoryrule::TYPE_OBJECTTYPE, [
            'ecsid' => $otherecs->get_id(), 'categoryid' => $cat2->id, 'objecttype' => categoryrule::OBJECTTYPE_COURSELINK,
        ]);
        $this->assertSame([], categoryrule::validate($rule));

        // A rule is not a duplicate of itself (editing it).
        $rule = categoryrule::get_rule($daterangeid);
        $rule->categoryid = $cat2->id;
        $this->assertSame([], categoryrule::validate($rule));

        // A duplicate is not saved.
        $this->expectException(coding_exception::class);
        categoryrule::save($this->make_rule(categoryrule::TYPE_OBJECTTYPE, $base + [
            'objecttype' => categoryrule::OBJECTTYPE_COURSELINK,
        ]));
    }

    /**
     * Saving cleans the rule and adds new rules at the end of the list.
     */
    public function test_save(): void {
        $this->resetAfterTest();
        $ecs = $this->create_ecs();
        $cat = $this->getDataGenerator()->create_category();
        $base = ['ecsid' => $ecs->get_id(), 'categoryid' => $cat->id];

        // Fields of the other rule types are not stored.
        $id1 = categoryrule::save($this->make_rule(categoryrule::TYPE_FIXED, $base + [
            'attribute' => 'courseType', 'fixedvalues' => ' Seminar, ,Vorlesung ', 'datefrom' => 5, 'dateto' => 6,
            'objecttype' => categoryrule::OBJECTTYPE_COURSELINK,
        ]));
        $id2 = categoryrule::save($this->make_rule(categoryrule::TYPE_OBJECTTYPE, $base + [
            'attribute' => 'courseType', 'fixedvalues' => 'x', 'objecttype' => categoryrule::OBJECTTYPE_COURSELINK,
        ]));

        $rules = categoryrule::get_rules($ecs->get_id());
        $this->assertSame([$id1, $id2], array_keys($rules));

        $this->assertSame('Seminar, Vorlesung', $rules[$id1]->fixedvalues);
        $this->assertNull($rules[$id1]->datefrom);
        $this->assertNull($rules[$id1]->dateto);
        $this->assertNull($rules[$id1]->objecttype);
        $this->assertEquals(1, $rules[$id1]->sortorder);

        $this->assertSame('', $rules[$id2]->attribute);
        $this->assertNull($rules[$id2]->fixedvalues);
        $this->assertSame(categoryrule::OBJECTTYPE_COURSELINK, $rules[$id2]->objecttype);
        $this->assertEquals(2, $rules[$id2]->sortorder);

        // Update keeps the position.
        $rule = $rules[$id1];
        $rule->fixedvalues = 'Übung';
        $this->assertSame($id1, categoryrule::save($rule));
        $rules = categoryrule::get_rules($ecs->get_id());
        $this->assertSame('Übung', $rules[$id1]->fixedvalues);
        $this->assertEquals(1, $rules[$id1]->sortorder);

        // An invalid rule is not saved.
        $this->expectException(coding_exception::class);
        categoryrule::save($this->make_rule(categoryrule::TYPE_FIXED, $base + ['attribute' => 'courseType']));
    }

    /**
     * Rules can be moved up and down, and deleting a rule closes the gap.
     */
    public function test_move_and_delete(): void {
        $this->resetAfterTest();
        $ecs = $this->create_ecs();
        $cat = $this->getDataGenerator()->create_category();
        $base = ['ecsid' => $ecs->get_id(), 'categoryid' => $cat->id];
        $ids = [];
        foreach (['a', 'b', 'c'] as $value) {
            $ids[$value] = categoryrule::save($this->make_rule(categoryrule::TYPE_FIXED, $base + [
                'attribute' => 'courseType', 'fixedvalues' => $value,
            ]));
        }

        categoryrule::move($ids['c'], true);
        $this->assertSame([$ids['a'], $ids['c'], $ids['b']], array_keys(categoryrule::get_rules($ecs->get_id())));

        categoryrule::move($ids['a'], false);
        $this->assertSame([$ids['c'], $ids['a'], $ids['b']], array_keys(categoryrule::get_rules($ecs->get_id())));

        // The first rule cannot move up, the last rule cannot move down.
        categoryrule::move($ids['c'], true);
        categoryrule::move($ids['b'], false);
        $this->assertSame([$ids['c'], $ids['a'], $ids['b']], array_keys(categoryrule::get_rules($ecs->get_id())));

        categoryrule::delete($ids['a']);
        $rules = categoryrule::get_rules($ecs->get_id());
        $this->assertSame([$ids['c'], $ids['b']], array_keys($rules));
        $this->assertEquals([1, 2], array_values(array_column($rules, 'sortorder')));
    }

    /**
     * Deleting the rules of one ECS keeps the rules of the other ECS.
     */
    public function test_delete_ecs_rules(): void {
        $this->resetAfterTest();
        $ecs1 = $this->create_ecs();
        $ecs2 = $this->create_ecs();
        $cat = $this->getDataGenerator()->create_category();
        foreach ([$ecs1, $ecs2] as $ecs) {
            categoryrule::save($this->make_rule(categoryrule::TYPE_OBJECTTYPE, [
                'ecsid' => $ecs->get_id(), 'categoryid' => $cat->id, 'objecttype' => categoryrule::OBJECTTYPE_COURSELINK,
            ]));
        }

        categoryrule::delete_ecs_rules($ecs1->get_id());

        $this->assertCount(0, categoryrule::get_rules($ecs1->get_id()));
        $this->assertCount(1, categoryrule::get_rules($ecs2->get_id()));
    }

    /**
     * The values of a course link: participant ID, object type and the flattened ECS data.
     */
    public function test_get_courselink_values(): void {
        $this->resetAfterTest();
        $ecs = $this->create_ecs();
        $courselink = (object)[
            'id' => 'abc',
            'title' => 'Mathematics 1',
            'courseType' => 'Vorlesung',
            'term' => 'WS 2026/27',
            'firstDate' => '2026-10-20T10:00:00+02:00',
            'degreeProgrammes' => [
                (object)['code' => 'MATH', 'title' => 'Mathematics'],
                (object)['code' => 'PHYS', 'title' => 'Physics'],
            ],
        ];

        $values = categoryrule::get_courselink_values($ecs, 37, $courselink, false);

        $this->assertSame($ecs->get_id() . '_37', $values[categoryrule::ATTRIBUTE_PARTICIPANT]);
        $this->assertSame(categoryrule::OBJECTTYPE_COURSELINK, $values[categoryrule::ATTRIBUTE_OBJECTTYPE]);
        $this->assertSame('Vorlesung', $values['courseType']);
        $this->assertSame('WS 2026/27', $values['term']);
        $this->assertSame(self::ts('2026-10-20 10:00:00'), $values['firstDate']);
        $this->assertSame(['MATH', 'PHYS'], $values['degreeProgrammes_code']);
        $this->assertArrayNotHasKey(categoryrule::ATTRIBUTE_COMMUNITY, $values);
    }

    /**
     * The category for a course link: rule category, or null when there is no rule or no match.
     */
    public function test_get_courselink_category(): void {
        $this->resetAfterTest();
        $ecs = $this->create_ecs();
        $cat = $this->getDataGenerator()->create_category();
        $courselink = (object)['id' => 'abc', 'title' => 'Mathematics 1', 'courseType' => 'Seminar'];

        $this->assertNull(categoryrule::get_courselink_category($ecs, 37, $courselink));

        categoryrule::save($this->make_rule(categoryrule::TYPE_FIXED, [
            'ecsid' => $ecs->get_id(), 'categoryid' => $cat->id,
            'attribute' => categoryrule::ATTRIBUTE_PARTICIPANT, 'fixedvalues' => $ecs->get_id() . '_37',
        ]));

        $this->assertSame((int)$cat->id, categoryrule::get_courselink_category($ecs, 37, $courselink));
        $this->assertNull(categoryrule::get_courselink_category($ecs, 38, $courselink));
    }
}
