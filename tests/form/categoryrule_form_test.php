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

namespace local_campusconnect\form;

use advanced_testcase;
use DateTime;
use DateTimeZone;
use local_campusconnect\categoryrule;
use local_campusconnect\ecssettings;
use local_campusconnect\participantsettings;

/**
 * Tests for the form to add or edit a category assignment rule.
 *
 * @package    local_campusconnect
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_campusconnect\form\categoryrule_form
 */
#[\PHPUnit\Framework\Attributes\CoversClass(categoryrule_form::class)]
final class categoryrule_form_test extends advanced_testcase {
    /** @var ecssettings */
    private $ecs;

    /** @var int */
    private $categoryid;

    /**
     * Create an ECS with one participant we import course links from.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->setTimezone('Europe/Berlin');

        $this->categoryid = (int)$this->getDataGenerator()->create_category()->id;
        $this->ecs = new ecssettings();
        $this->ecs->save_settings((object)[
            'name' => 'Test ECS',
            'url' => 'http://localhost/ecs',
            'auth' => ecssettings::AUTH_NONE,
            'ecsauth' => 'test',
            'importcategory' => $this->categoryid,
            'importrole' => 'student',
        ]);
        $DB->insert_record('local_campusconnect_part', (object)[
            'ecsid' => $this->ecs->get_id(),
            'mid' => 37,
            'import' => 1,
            'importtype' => participantsettings::IMPORT_LINK,
            'active' => 1,
            'displayname' => 'EUCOR: Université de Haute-Alsace',
        ]);
    }

    /**
     * Timestamp of the given local time in Europe/Berlin.
     *
     * @param string $time
     * @return int
     */
    private static function ts(string $time): int {
        return (new DateTime($time, new DateTimeZone('Europe/Berlin')))->getTimestamp();
    }

    /**
     * Form data for a rule.
     *
     * @param array $fields
     * @return array
     */
    private function form_data(array $fields): array {
        return array_merge([
            'id' => 0,
            'ecsid' => $this->ecs->get_id(),
            'categoryid' => $this->categoryid,
            'mappingtype' => categoryrule::TYPE_FIXED,
            'attribute' => categoryrule::ATTRIBUTE_PARTICIPANT,
            'fixedvalues' => '',
            'dateattribute' => 'firstDate',
            'datefrom' => ['day' => 16, 'month' => 10, 'year' => 2026],
            'dateto' => ['day' => 16, 'month' => 4, 'year' => 2027],
            'objecttype' => categoryrule::OBJECTTYPE_COURSELINK,
        ], $fields);
    }

    /**
     * Submit the form with the given data.
     *
     * @param array $data
     * @return categoryrule_form
     */
    private function submit(array $data): categoryrule_form {
        categoryrule_form::mock_submit($data);
        return new categoryrule_form(null, ['ecsid' => $this->ecs->get_id()]);
    }

    /**
     * The form shows the participants we import course links from, with their participant ID.
     */
    public function test_form_lists_participant_ids(): void {
        $form = new categoryrule_form(null, ['ecsid' => $this->ecs->get_id()]);
        $html = $form->render();

        $this->assertStringContainsString($this->ecs->get_id() . '_37', $html);
        $this->assertStringContainsString('Université de Haute-Alsace', $html);
    }

    /**
     * A valid date range rule: the "to" date includes the whole day.
     */
    public function test_submit_date_range(): void {
        $form = $this->submit($this->form_data(['mappingtype' => categoryrule::TYPE_DATERANGE]));
        $data = $form->get_data();
        $this->assertNotNull($data);

        $rule = categoryrule_form::to_rule($data);
        $this->assertSame('firstDate', $rule->attribute);
        $this->assertSame(self::ts('2026-10-16 00:00:00'), $rule->datefrom);
        $this->assertSame(self::ts('2027-04-16 23:59:59'), $rule->dateto);
        $this->assertSame([], categoryrule::validate($rule));
    }

    /**
     * The same day as "from" and "to" date is a valid range.
     */
    public function test_submit_date_range_one_day(): void {
        $form = $this->submit($this->form_data([
            'mappingtype' => categoryrule::TYPE_DATERANGE,
            'dateto' => ['day' => 16, 'month' => 10, 'year' => 2026],
        ]));

        $this->assertNotNull($form->get_data());
    }

    /**
     * Errors of the rule are shown at the right form fields.
     */
    public function test_validation_errors(): void {
        $form = $this->submit($this->form_data(['fixedvalues' => ' , ']));
        $this->assertNull($form->get_data());

        // The validation gets the dates as timestamps.
        $dates = ['datefrom' => self::ts('2026-10-16 00:00:00'), 'dateto' => self::ts('2027-04-16 00:00:00')];
        $errors = $form->validation($this->form_data(['fixedvalues' => ' , '] + $dates), []);
        $this->assertSame(['fixedvalues' => get_string('categoryrule_error_fixedvalues', 'local_campusconnect')], $errors);

        $errors = $form->validation($this->form_data([
            'mappingtype' => categoryrule::TYPE_DATERANGE,
            'dateattribute' => 'courseType',
            'datefrom' => self::ts('2027-04-17 00:00:00'),
            'dateto' => self::ts('2027-04-16 00:00:00'),
        ]), []);
        $this->assertSame([
            'dateattribute' => get_string('categoryrule_error_attribute', 'local_campusconnect'),
            'dateto' => get_string('categoryrule_error_daterange', 'local_campusconnect'),
        ], $errors);

        $errors = $form->validation($this->form_data(['mappingtype' => 99, 'categoryid' => 0]), []);
        $this->assertSame([
            'categoryid' => get_string('categoryrule_error_category', 'local_campusconnect'),
            'mappingtypegroup' => get_string('categoryrule_error_mappingtype', 'local_campusconnect'),
        ], $errors);
    }

    /**
     * A saved rule can be loaded into the form and saved again unchanged.
     */
    public function test_edit_round_trip(): void {
        $id = categoryrule::save((object)[
            'ecsid' => $this->ecs->get_id(),
            'categoryid' => $this->categoryid,
            'mappingtype' => categoryrule::TYPE_DATERANGE,
            'attribute' => 'firstDate',
            'datefrom' => self::ts('2026-10-16 00:00:00'),
            'dateto' => self::ts('2027-04-16 23:59:59'),
        ]);
        $saved = categoryrule::get_rule($id);

        $data = categoryrule_form::from_rule($saved);
        $rule = categoryrule_form::to_rule($data);

        $this->assertSame('firstDate', $rule->attribute);
        $this->assertEquals($saved->datefrom, $rule->datefrom);
        $this->assertEquals($saved->dateto, $rule->dateto);
        $this->assertSame([], categoryrule::validate($rule));
    }
}
