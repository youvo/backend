<?php

namespace Drupal\Tests\logbook\Unit;

use Drupal\logbook\SearchTags;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests reading the tokens of the logbook search.
 */
#[CoversClass(SearchTags::class)]
#[Group('logbook')]
class SearchTagsTest extends UnitTestCase {

  /**
   * Tokens are validated, deduplicated and capped.
   */
  public function testParse(): void {
    $parsed = SearchTags::parse([
      'user:3',
      'project:7',
      'user:3',
      'text: Feed back ',
      'node:1',
      'user:abc',
      'user:0',
      'user:',
      'text:  ',
      ['user:5'],
    ]);
    $this->assertSame(['user' => [3], 'project' => [7], 'text' => ['Feed back']], $parsed);
    $this->assertSame(['user' => [], 'project' => [], 'text' => []], SearchTags::parse(NULL));
    $this->assertSame(['user' => [4], 'project' => [], 'text' => []], SearchTags::parse('user:4'));
    $many = array_map(static fn (int $i) => 'user:' . $i, range(1, 25));
    $this->assertCount(SearchTags::MAX_TOKENS, SearchTags::parse($many)['user']);
    $long = SearchTags::parse(['text:' . str_repeat('a', 150)]);
    $this->assertSame(SearchTags::MAX_TEXT_LENGTH, mb_strlen($long['text'][0]));
    // Without its script, the Tagify input submits a JSON list of tags.
    $json = json_encode([['value' => 'text:Zebra', 'entity_id' => 'text:Zebra'], ['entity_id' => 'project:2'], 'x']);
    $this->assertSame(['user' => [], 'project' => [2], 'text' => ['Zebra']], SearchTags::parse($json));
  }

}
