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
 * Rules that assign imported course links to course categories.
 *
 * @package    local_campusconnect
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_campusconnect;

use coding_exception;
use core_date;
use core_text;
use DateTime;
use moodle_exception;
use stdClass;

/**
 * Rules that assign imported course links to course categories.
 *
 * Each ECS has an ordered list of rules. Each rule has one target category, one rule type
 * (fixed values, date range or object type) and the settings for this type. The first rule
 * that matches a course link decides its category. If no rule matches, the caller uses the
 * import category of the ECS.
 *
 * The class does not use language strings: validate() returns error codes and the lists
 * for the form return keys only, so the admin page translates them.
 *
 * @package    local_campusconnect
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class categoryrule {
    /** Database table of the rules. */
    public const TABLE = 'local_campusconnect_catrule';

    /** Rule type: the attribute value is one of a list of fixed values. */
    public const TYPE_FIXED = 1;

    /** Rule type: the attribute date is inside a date range. */
    public const TYPE_DATERANGE = 2;

    /** Rule type: the ECS object has the given type. */
    public const TYPE_OBJECTTYPE = 3;

    /** Attribute: participant ID in the format "ecsid_mid", e.g. "5_37". */
    public const ATTRIBUTE_PARTICIPANT = 'participantid';

    /** Attribute: name(s) of the community of the participant. */
    public const ATTRIBUTE_COMMUNITY = 'community';

    /** Key of the object type in the values of an ECS object. */
    public const ATTRIBUTE_OBJECTTYPE = 'objecttype';

    /** Object type: course link. */
    public const OBJECTTYPE_COURSELINK = 'courselink';

    /** Form field that shows the "duplicate" error, per rule type (fixed values use "sharedvalues"). */
    protected const DUPLICATE_ERROR_FIELDS = [
        self::TYPE_DATERANGE => 'datefrom',
        self::TYPE_OBJECTTYPE => 'objecttype',
    ];

    /** @var array Community names: ecsid => mid => string[] */
    protected static $communitycache = [];

    /**
     * The rule types.
     *
     * @return int[]
     */
    public static function get_mapping_types(): array {
        return [self::TYPE_FIXED, self::TYPE_DATERANGE, self::TYPE_OBJECTTYPE];
    }

    /**
     * The attributes a fixed values rule can check: participant ID, community and all
     * course link fields that are not dates.
     *
     * @return string[]
     */
    public static function get_fixed_attributes(): array {
        $datefields = self::get_date_attributes();
        $fields = array_filter(metadata::list_remote_fields(true), fn($field) => !in_array($field, $datefields));
        return array_merge([self::ATTRIBUTE_PARTICIPANT, self::ATTRIBUTE_COMMUNITY], array_values($fields));
    }

    /**
     * The attributes a date range rule can check: the course link date fields.
     *
     * @return string[]
     */
    public static function get_date_attributes(): array {
        return metadata::list_remote_to_local_fields('startdate', true);
    }

    /**
     * The object types an object type rule can check.
     *
     * @return string[]
     */
    public static function get_object_types(): array {
        return [self::OBJECTTYPE_COURSELINK];
    }

    /**
     * Get the rules of an ECS, in the order they are checked.
     *
     * @param int $ecsid
     * @return stdClass[] id => rule
     */
    public static function get_rules(int $ecsid): array {
        global $DB;
        return $DB->get_records(self::TABLE, ['ecsid' => $ecsid], 'sortorder ASC, id ASC');
    }

    /**
     * Get one rule.
     *
     * @param int $id
     * @param int $strictness IGNORE_MISSING or MUST_EXIST
     * @return stdClass|false
     */
    public static function get_rule(int $id, int $strictness = MUST_EXIST) {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id], '*', $strictness);
    }

    /**
     * Check a rule.
     *
     * @param stdClass $rule
     * @return string[] field => error code, empty if the rule is valid
     */
    public static function validate(stdClass $rule): array {
        global $DB;

        $errors = [];
        if (empty($rule->ecsid) || !$DB->record_exists('local_campusconnect_ecs', ['id' => $rule->ecsid])) {
            $errors['ecsid'] = 'ecs';
        }
        if (empty($rule->categoryid) || !$DB->record_exists('course_categories', ['id' => $rule->categoryid])) {
            $errors['categoryid'] = 'category';
        }

        $mappingtype = (int)($rule->mappingtype ?? 0);
        switch ($mappingtype) {
            case self::TYPE_FIXED:
                if (!in_array($rule->attribute ?? '', self::get_fixed_attributes(), true)) {
                    $errors['attribute'] = 'attribute';
                }
                if (!self::split_values($rule->fixedvalues ?? '')) {
                    $errors['fixedvalues'] = 'fixedvalues';
                }
                break;
            case self::TYPE_DATERANGE:
                if (!in_array($rule->attribute ?? '', self::get_date_attributes(), true)) {
                    $errors['attribute'] = 'attribute';
                }
                if (empty($rule->datefrom) || empty($rule->dateto)) {
                    $errors['datefrom'] = 'daterequired';
                } else if ($rule->datefrom > $rule->dateto) {
                    $errors['dateto'] = 'daterange';
                }
                break;
            case self::TYPE_OBJECTTYPE:
                if (!in_array($rule->objecttype ?? '', self::get_object_types(), true)) {
                    $errors['objecttype'] = 'objecttype';
                }
                break;
            default:
                $errors['mappingtype'] = 'mappingtype';
        }

        if (!$errors) {
            if ($mappingtype == self::TYPE_FIXED) {
                if (self::get_shared_values($rule)) {
                    $errors['fixedvalues'] = 'sharedvalues';
                }
            } else if (self::is_duplicate($rule)) {
                $errors[self::DUPLICATE_ERROR_FIELDS[$mappingtype]] = 'duplicate';
            }
        }

        return $errors;
    }

    /**
     * Get the values of a fixed values rule that another fixed values rule of the same ECS with the
     * same attribute already uses. Such a value can only match the rule that is higher in the list,
     * so it is never used in the other rule. Upper/lower case is ignored.
     *
     * @param stdClass $rule a fixed values rule
     * @return string[] the shared values, as written in $rule
     */
    public static function get_shared_values(stdClass $rule): array {
        $used = [];
        foreach (self::get_rules((int)$rule->ecsid) as $other) {
            if (!empty($rule->id) && $other->id == $rule->id) {
                continue;
            }
            if ($other->mappingtype != self::TYPE_FIXED || $other->attribute !== ($rule->attribute ?? '')) {
                continue;
            }
            foreach (self::split_values($other->fixedvalues ?? '') as $value) {
                $used[core_text::strtolower($value)] = true;
            }
        }

        $shared = [];
        foreach (self::split_values($rule->fixedvalues ?? '') as $value) {
            $key = core_text::strtolower($value);
            if (isset($used[$key]) && !isset($shared[$key])) {
                $shared[$key] = $value;
            }
        }
        return array_values($shared);
    }

    /**
     * Check if another date range or object type rule of the same ECS has the same attribute and values
     * (the target category is not checked).
     *
     * @param stdClass $rule a date range or object type rule
     * @return bool
     */
    protected static function is_duplicate(stdClass $rule): bool {
        foreach (self::get_rules((int)$rule->ecsid) as $other) {
            if ((!empty($rule->id) && $other->id == $rule->id) || $other->mappingtype != $rule->mappingtype) {
                continue;
            }
            if ($rule->mappingtype == self::TYPE_DATERANGE) {
                $same = $other->attribute === $rule->attribute
                    && (int)$other->datefrom === (int)$rule->datefrom
                    && (int)$other->dateto === (int)$rule->dateto;
            } else {
                $same = $other->objecttype === $rule->objecttype;
            }
            if ($same) {
                return true;
            }
        }
        return false;
    }

    /**
     * Save a rule. A new rule is added at the end of the list of its ECS.
     *
     * Only the fields of the rule type are stored, the other fields are set to null.
     *
     * @param stdClass $rule
     * @return int the rule id
     * @throws coding_exception if the rule is not valid
     */
    public static function save(stdClass $rule): int {
        global $DB;

        if ($errors = self::validate($rule)) {
            throw new coding_exception('Invalid category rule: ' . implode(', ', array_keys($errors)));
        }

        $record = (object)[
            'ecsid' => (int)$rule->ecsid,
            'categoryid' => (int)$rule->categoryid,
            'mappingtype' => (int)$rule->mappingtype,
            'attribute' => '',
            'fixedvalues' => null,
            'datefrom' => null,
            'dateto' => null,
            'objecttype' => null,
            'timemodified' => time(),
        ];
        switch ($record->mappingtype) {
            case self::TYPE_FIXED:
                $record->attribute = $rule->attribute;
                $record->fixedvalues = implode(', ', self::split_values($rule->fixedvalues));
                break;
            case self::TYPE_DATERANGE:
                $record->attribute = $rule->attribute;
                $record->datefrom = (int)$rule->datefrom;
                $record->dateto = (int)$rule->dateto;
                break;
            case self::TYPE_OBJECTTYPE:
                $record->objecttype = $rule->objecttype;
                break;
        }

        if (!empty($rule->id)) {
            $existing = self::get_rule($rule->id);
            if ($existing->ecsid != $record->ecsid) {
                throw new coding_exception('A category rule cannot be moved to another ECS');
            }
            $record->id = (int)$existing->id;
            $DB->update_record(self::TABLE, $record);
            return $record->id;
        }

        $maxsortorder = $DB->get_field(self::TABLE, 'MAX(sortorder)', ['ecsid' => $record->ecsid]);
        $record->sortorder = (int)$maxsortorder + 1;
        $record->timecreated = $record->timemodified;
        return (int)$DB->insert_record(self::TABLE, $record);
    }

    /**
     * Delete a rule.
     *
     * @param int $id
     */
    public static function delete(int $id): void {
        global $DB;

        if (!$rule = self::get_rule($id, IGNORE_MISSING)) {
            return;
        }
        $DB->delete_records(self::TABLE, ['id' => $id]);
        self::write_order(array_keys(self::get_rules($rule->ecsid)));
    }

    /**
     * Move a rule one position up or down in the list of its ECS.
     *
     * @param int $id
     * @param bool $up true to move up (the rule is checked earlier), false to move down
     */
    public static function move(int $id, bool $up): void {
        $rule = self::get_rule($id);
        $ids = array_keys(self::get_rules($rule->ecsid));
        $pos = array_search($id, $ids);
        $newpos = $up ? $pos - 1 : $pos + 1;
        if ($newpos < 0 || $newpos >= count($ids)) {
            return;
        }
        [$ids[$pos], $ids[$newpos]] = [$ids[$newpos], $ids[$pos]];
        self::write_order($ids);
    }

    /**
     * Store the order of the rules as 1, 2, 3, ...
     *
     * @param int[] $ids rule ids in the new order
     */
    protected static function write_order(array $ids): void {
        global $DB;

        $sortorder = 1;
        foreach ($ids as $id) {
            $DB->set_field(self::TABLE, 'sortorder', $sortorder++, ['id' => $id]);
        }
    }

    /**
     * Delete all rules of an ECS (used when the ECS is deleted).
     *
     * @param int $ecsid
     */
    public static function delete_ecs_rules(int $ecsid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['ecsid' => $ecsid]);
    }

    /**
     * Split a comma-separated list of values. Spaces around the values and empty values are removed.
     *
     * @param string $values
     * @return string[]
     */
    public static function split_values(string $values): array {
        return array_values(array_filter(array_map('trim', explode(',', $values)), fn($value) => $value !== ''));
    }

    /**
     * Get the last second (23:59:59) of the day of the timestamp, in the timezone of the current user.
     *
     * @param int $timestamp
     * @return int
     */
    public static function end_of_day(int $timestamp): int {
        $date = new DateTime('@' . $timestamp);
        $date->setTimezone(core_date::get_user_timezone_object());
        $date->setTime(23, 59, 59);
        return $date->getTimestamp();
    }

    /**
     * Check if a rule matches the values of an ECS object.
     *
     * @param stdClass $rule
     * @param array $values attribute => value (string, int or list of strings)
     * @return bool
     */
    public static function matches(stdClass $rule, array $values): bool {
        switch ((int)$rule->mappingtype) {
            case self::TYPE_FIXED:
                $expected = array_map([core_text::class, 'strtolower'], self::split_values($rule->fixedvalues ?? ''));
                $actual = $values[$rule->attribute] ?? null;
                foreach (is_array($actual) ? $actual : [$actual] as $value) {
                    if (is_scalar($value) && in_array(core_text::strtolower(trim((string)$value)), $expected, true)) {
                        return true;
                    }
                }
                return false;

            case self::TYPE_DATERANGE:
                $date = $values[$rule->attribute] ?? null;
                if (!is_int($date) && !(is_string($date) && ctype_digit($date))) {
                    return false;
                }
                if (empty($rule->datefrom) || empty($rule->dateto)) {
                    return false;
                }
                return (int)$date >= (int)$rule->datefrom && (int)$date <= (int)$rule->dateto;

            case self::TYPE_OBJECTTYPE:
                return isset($values[self::ATTRIBUTE_OBJECTTYPE]) && $values[self::ATTRIBUTE_OBJECTTYPE] === $rule->objecttype;
        }
        return false;
    }

    /**
     * Find the category for an ECS object: the category of the first rule that matches.
     *
     * A rule whose category no longer exists is skipped.
     *
     * @param stdClass[] $rules in the order they are checked
     * @param array $values attribute => value
     * @return int|null the category id, null if no rule matches
     */
    public static function find_category(array $rules, array $values): ?int {
        global $DB;

        foreach ($rules as $rule) {
            if (!self::matches($rule, $values)) {
                continue;
            }
            if (!$DB->record_exists('course_categories', ['id' => $rule->categoryid])) {
                $msg = "Category assignment rule {$rule->id}: category {$rule->categoryid} does not exist - rule skipped";
                log::add($msg, false, false, false);
                continue;
            }
            return (int)$rule->categoryid;
        }
        return null;
    }

    /**
     * Get the category for a course link from the rules of its ECS.
     *
     * @param ecssettings $settings the ECS the course link came from
     * @param int $mid the participant that sent the course link
     * @param stdClass $courselink the course link data from the ECS
     * @return int|null the category id, null if there is no matching rule (use the import category)
     */
    public static function get_courselink_category(ecssettings $settings, int $mid, stdClass $courselink): ?int {
        $rules = self::get_rules($settings->get_id());
        if (!$rules) {
            return null;
        }

        $withcommunity = false;
        foreach ($rules as $rule) {
            if ($rule->mappingtype == self::TYPE_FIXED && $rule->attribute == self::ATTRIBUTE_COMMUNITY) {
                $withcommunity = true;
                break;
            }
        }

        return self::find_category($rules, self::get_courselink_values($settings, $mid, $courselink, $withcommunity));
    }

    /**
     * Get the values of a course link the rules can check.
     *
     * @param ecssettings $settings the ECS the course link came from
     * @param int $mid the participant that sent the course link
     * @param stdClass $courselink the course link data from the ECS
     * @param bool $withcommunity true to add the community names (needs a request to the ECS)
     * @return array attribute => value
     */
    public static function get_courselink_values(
        ecssettings $settings,
        int $mid,
        stdClass $courselink,
        bool $withcommunity
    ): array {
        $metadata = new metadata($settings, true);
        $values = $metadata->flatten_remote_data($courselink);
        $values[self::ATTRIBUTE_PARTICIPANT] = $settings->get_id() . '_' . $mid;
        $values[self::ATTRIBUTE_OBJECTTYPE] = self::OBJECTTYPE_COURSELINK;
        if ($withcommunity) {
            $values[self::ATTRIBUTE_COMMUNITY] = self::get_community_names($settings, $mid);
        }
        return $values;
    }

    /**
     * Get the names of the communities a participant belongs to.
     *
     * The memberships are loaded from the ECS once per request. If the ECS cannot be
     * reached, the list is empty (so community rules do not match) and nothing is cached.
     *
     * @param ecssettings $settings
     * @param int $mid
     * @return string[]
     */
    protected static function get_community_names(ecssettings $settings, int $mid): array {
        $ecsid = $settings->get_id();
        if (!isset(self::$communitycache[$ecsid])) {
            try {
                $connect = new connect($settings);
                $names = [];
                foreach ($connect->get_memberships() as $membership) {
                    foreach ($membership->participants as $participant) {
                        $names[$participant->mid][] = $membership->community->name;
                    }
                }
                self::$communitycache[$ecsid] = $names;
            } catch (moodle_exception $e) {
                log::add("Category assignment rules: could not load the communities from ECS {$ecsid}: " .
                         $e->getMessage(), false, false, false);
                return [];
            }
        }
        return self::$communitycache[$ecsid][$mid] ?? [];
    }
}
