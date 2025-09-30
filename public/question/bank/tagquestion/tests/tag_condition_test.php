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

namespace qbank_tagquestion;

use core\output\datafilter;
use core_question\local\bank\question_edit_contexts;
use context_module;

/**
 * Unit tests for tag_condition class.
 *
 * @package    qbank_tagquestion
 * @copyright  2025 Catalyst IT Canada Pty Ltd
 * @author     Niko Hoogeveen <nikohoogeveen@catalyst-ca.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \qbank_tagquestion\tag_condition
 */
final class tag_condition_test extends \advanced_testcase {
    /** @var \stdClass */
    private $course;

    /** @var \stdClass */
    private $qbank;

    /** @var context_module */
    private $context;

    /** @var \core_question_generator */
    private $questiongenerator;

    /** @var \stdClass */
    private $questioncategory;

    /** @var array */
    private $questions = [];

    /** @var array */
    private $tags = [];

    /**
     * Set up test environment.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create course and qbank.
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->qbank = $generator->create_module('qbank', ['course' => $this->course->id]);
        $this->context = context_module::instance($this->qbank->cmid);

        // Set up question generator.
        $this->questiongenerator = $generator->get_plugin_generator('core_question');

        // Create question category.
        $contexts = new question_edit_contexts($this->context);
        $this->questioncategory = $this->questiongenerator->create_question_category([
            'contextid' => $this->context->id,
            'name' => 'Test Category',
        ]);

        // Create tags.
        $this->tags = \core_tag_tag::create_if_missing(
            \core_tag_area::get_collection('core', 'question'),
            ['math', 'algebra', 'geometry', 'advanced'],
        );
        // Create questions with different tag combinations.
        $this->create_questions_with_tags();
    }

    /**
     * Create test questions with various tag combinations.
     */
    private function create_questions_with_tags(): void {
        // Question 1: tagged with 'math' only.
        $this->questions['q1'] = $this->questiongenerator->create_question('truefalse', null, [
            'category' => $this->questioncategory->id,
            'name' => 'Question 1 - Math only',
        ]);
        \core_tag_tag::set_item_tags(
            'core_question',
            'question',
            $this->questions['q1']->id,
            $this->context,
            ['math'],
        );

        // Question 2: tagged with 'math' and 'algebra'.
        $this->questions['q2'] = $this->questiongenerator->create_question('truefalse', null, [
            'category' => $this->questioncategory->id,
            'name' => 'Question 2 - Math and Algebra',
        ]);
        \core_tag_tag::set_item_tags(
            'core_question',
            'question',
            $this->questions['q2']->id,
            $this->context,
            ['math', 'algebra'],
        );

        // Question 3: tagged with 'math', 'algebra', and 'advanced'.
        $this->questions['q3'] = $this->questiongenerator->create_question('truefalse', null, [
            'category' => $this->questioncategory->id,
            'name' => 'Question 3 - Math, Algebra, Advanced',
        ]);
        \core_tag_tag::set_item_tags(
            'core_question',
            'question',
            $this->questions['q3']->id,
            $this->context,
            ['math', 'algebra', 'advanced'],
        );

        // Question 4: tagged with 'geometry' only.
        $this->questions['q4'] = $this->questiongenerator->create_question('truefalse', null, [
            'category' => $this->questioncategory->id,
            'name' => 'Question 4 - Geometry only',
        ]);
        \core_tag_tag::set_item_tags(
            'core_question',
            'question',
            $this->questions['q4']->id,
            $this->context,
            ['geometry'],
        );

        // Question 5: tagged with 'math' and 'geometry'.
        $this->questions['q5'] = $this->questiongenerator->create_question('truefalse', null, [
            'category' => $this->questioncategory->id,
            'name' => 'Question 5 - Math and Geometry',
        ]);
        \core_tag_tag::set_item_tags(
            'core_question',
            'question',
            $this->questions['q5']->id,
            $this->context,
            ['math', 'geometry'],
        );

        // Question 6: no tags.
        $this->questions['q6'] = $this->questiongenerator->create_question('truefalse', null, [
            'category' => $this->questioncategory->id,
            'name' => 'Question 6 - No tags',
        ]);
    }

    /**
     * Helper method to get question IDs from filter results.
     *
     * @param array $filter Filter configuration
     * @return array Array of question IDs that match the filter
     */
    private function get_questions_from_filter(array $filter): array {
        global $DB;

        [$where, $params] = tag_condition::build_query_from_filter($filter);

        $sql = "SELECT q.id
                FROM {question} q
                JOIN {question_versions} qv ON q.id = qv.questionid
                JOIN {question_bank_entries} qbe ON qv.questionbankentryid = qbe.id
                WHERE qbe.questioncategoryid = :categoryid";
        $params['categoryid'] = $this->questioncategory->id;

        if (!empty($where)) {
            $sql .= " AND $where";
        }

        $results = $DB->get_records_sql($sql, $params);
        return array_keys($results);
    }

    /**
     * Test filtering with no tags selected returns all questions.
     *
     * @covers ::build_query_from_filter
     */
    public function test_filter_no_tags(): void {
        $filter = [
            'values' => [],
            'jointype' => datafilter::JOINTYPE_ALL,
        ];

        $questionids = $this->get_questions_from_filter($filter);

        // Should return all questions when no filter is applied.
        $this->assertCount(6, $questionids);
        $this->assertTrue(in_array($this->questions['q1']->id, $questionids));
        $this->assertTrue(in_array($this->questions['q2']->id, $questionids));
        $this->assertTrue(in_array($this->questions['q3']->id, $questionids));
        $this->assertTrue(in_array($this->questions['q4']->id, $questionids));
        $this->assertTrue(in_array($this->questions['q5']->id, $questionids));
        $this->assertTrue(in_array($this->questions['q6']->id, $questionids));
    }

    /**
     * Test filtering with single tag using ANY jointype.
     *
     * @covers ::build_query_from_filter
     */
    public function test_filter_single_tag_any(): void {
        $filter = [
            'values' => [$this->tags['math']->id],
            'jointype' => datafilter::JOINTYPE_ANY,
        ];

        $questionids = $this->get_questions_from_filter($filter);

        // Should return questions tagged with 'math': q1, q2, q3, q5.
        $this->assertCount(4, $questionids);
        $this->assertTrue(in_array($this->questions['q1']->id, $questionids));
        $this->assertTrue(in_array($this->questions['q2']->id, $questionids));
        $this->assertTrue(in_array($this->questions['q3']->id, $questionids));
        $this->assertTrue(in_array($this->questions['q5']->id, $questionids));
        $this->assertFalse(in_array($this->questions['q4']->id, $questionids));
        $this->assertFalse(in_array($this->questions['q6']->id, $questionids));
    }

    /**
     * Test filtering with multiple tags using ANY jointype.
     *
     * @covers ::build_query_from_filter
     */
    public function test_filter_multiple_tags_any(): void {
        $filter = [
            'values' => [$this->tags['math']->id, $this->tags['geometry']->id],
            'jointype' => datafilter::JOINTYPE_ANY,
        ];

        $questionids = $this->get_questions_from_filter($filter);

        // Should return questions tagged with 'math' OR 'geometry': q1, q2, q3, q4, q5.
        $this->assertCount(5, $questionids);
        $this->assertTrue(in_array($this->questions['q1']->id, $questionids));
        $this->assertTrue(in_array($this->questions['q2']->id, $questionids));
        $this->assertTrue(in_array($this->questions['q3']->id, $questionids));
        $this->assertTrue(in_array($this->questions['q4']->id, $questionids));
        $this->assertTrue(in_array($this->questions['q5']->id, $questionids));
        $this->assertFalse(in_array($this->questions['q6']->id, $questionids));
    }

    /**
     * Test filtering with multiple tags using ALL jointype.
     *
     * @covers ::build_query_from_filter
     */
    public function test_filter_multiple_tags_all(): void {
        $filter = [
            'values' => [$this->tags['math']->id, $this->tags['algebra']->id],
            'jointype' => datafilter::JOINTYPE_ALL,
        ];

        $questionids = $this->get_questions_from_filter($filter);

        // Should return questions tagged with both 'math' AND 'algebra': q2, q3.
        $this->assertCount(2, $questionids);
        $this->assertTrue(in_array($this->questions['q2']->id, $questionids));
        $this->assertTrue(in_array($this->questions['q3']->id, $questionids));
        $this->assertFalse(in_array($this->questions['q1']->id, $questionids));
        $this->assertFalse(in_array($this->questions['q4']->id, $questionids));
        $this->assertFalse(in_array($this->questions['q5']->id, $questionids));
        $this->assertFalse(in_array($this->questions['q6']->id, $questionids));
    }

    /**
     * Test filtering with three tags using ALL jointype.
     *
     * @covers ::build_query_from_filter
     */
    public function test_filter_three_tags_all(): void {
        $filter = [
            'values' => [$this->tags['math']->id, $this->tags['algebra']->id, $this->tags['advanced']->id],
            'jointype' => datafilter::JOINTYPE_ALL,
        ];

        $questionids = $this->get_questions_from_filter($filter);

        // Should return questions tagged with all three tags: only q3.
        $this->assertCount(1, $questionids);
        $this->assertTrue(in_array($this->questions['q3']->id, $questionids));
        $this->assertFalse(in_array($this->questions['q1']->id, $questionids));
        $this->assertFalse(in_array($this->questions['q2']->id, $questionids));
        $this->assertFalse(in_array($this->questions['q4']->id, $questionids));
        $this->assertFalse(in_array($this->questions['q5']->id, $questionids));
        $this->assertFalse(in_array($this->questions['q6']->id, $questionids));
    }

    /**
     * Test filtering with NONE jointype (exclude questions with specified tags).
     *
     * @covers ::build_query_from_filter
     */
    public function test_filter_tags_none(): void {
        $filter = [
            'values' => [$this->tags['math']->id],
            'jointype' => datafilter::JOINTYPE_NONE,
        ];

        $questionids = $this->get_questions_from_filter($filter);

        // Should return questions NOT tagged with 'math': q4, q6.
        $this->assertCount(2, $questionids);
        $this->assertTrue(in_array($this->questions['q4']->id, $questionids));
        $this->assertTrue(in_array($this->questions['q6']->id, $questionids));
        $this->assertFalse(in_array($this->questions['q1']->id, $questionids));
        $this->assertFalse(in_array($this->questions['q2']->id, $questionids));
        $this->assertFalse(in_array($this->questions['q3']->id, $questionids));
        $this->assertFalse(in_array($this->questions['q5']->id, $questionids));
    }

    /**
     * Test filtering with multiple tags using NONE jointype.
     *
     * @covers ::build_query_from_filter
     */
    public function test_filter_multiple_tags_none(): void {
        $filter = [
            'values' => [$this->tags['math']->id, $this->tags['geometry']->id],
            'jointype' => datafilter::JOINTYPE_NONE,
        ];

        $questionids = $this->get_questions_from_filter($filter);

        // Should return questions NOT tagged with either 'math' or 'geometry': only q6.
        $this->assertCount(1, $questionids);
        $this->assertTrue(in_array($this->questions['q6']->id, $questionids));
        $this->assertFalse(in_array($this->questions['q1']->id, $questionids));
        $this->assertFalse(in_array($this->questions['q2']->id, $questionids));
        $this->assertFalse(in_array($this->questions['q3']->id, $questionids));
        $this->assertFalse(in_array($this->questions['q4']->id, $questionids));
        $this->assertFalse(in_array($this->questions['q5']->id, $questionids));
    }

    /**
     * Test filtering with non-existent tag ID.
     *
     * @covers ::build_query_from_filter
     */
    public function test_filter_nonexistent_tag(): void {
        $filter = [
            'values' => [99999],
            'jointype' => datafilter::JOINTYPE_ANY,
        ];

        $questionids = $this->get_questions_from_filter($filter);

        // Should return no questions.
        $this->assertCount(0, $questionids);
    }

    /**
     * Test get_condition_key returns expected value.
     *
     * @covers ::get_condition_key
     */
    public function test_get_condition_key(): void {
        $this->assertSame('qtagids', tag_condition::get_condition_key());
    }

    /**
     * Test the JOINTYPE_DEFAULT constant.
     *
     * @covers ::JOINTYPE_DEFAULT
     */
    public function test_jointype_default_constant(): void {
        $this->assertSame(datafilter::JOINTYPE_ALL, tag_condition::JOINTYPE_DEFAULT);
    }

    /**
     * Test filtering with default jointype (should be ALL).
     *
     * @covers ::build_query_from_filter
     */
    public function test_filter_default_jointype(): void {
        $filter = [
            'values' => [$this->tags['math']->id, $this->tags['algebra']->id],
        ];

        $questionids = $this->get_questions_from_filter($filter);

        // Should behave like ALL jointype: return questions with both tags.
        $this->assertCount(2, $questionids);
        $this->assertTrue(in_array($this->questions['q2']->id, $questionids));
        $this->assertTrue(in_array($this->questions['q3']->id, $questionids));
    }

    /**
     * Test build_query_from_filter returns empty when no tags provided.
     *
     * @covers ::build_query_from_filter
     */
    public function test_build_query_empty_filter(): void {
        $filter = [
            'values' => [],
            'jointype' => datafilter::JOINTYPE_ALL,
        ];

        [$where, $params] = tag_condition::build_query_from_filter($filter);

        $this->assertSame('', $where);
        $this->assertSame([], $params);
    }
}
