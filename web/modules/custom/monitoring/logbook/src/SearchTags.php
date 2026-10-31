<?php

namespace Drupal\logbook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\user\UserInterface;
use Drupal\user_types\Utility\Profile;

/**
 * The tags of the logbook search.
 *
 * A tag is a token: "user:12" for a person or an organization, "project:5" for
 * a project and "text:Feedback" for a text. The search bar submits them as
 * tags[]=… and shows them with a label.
 */
final class SearchTags {

  /**
   * The number of tokens that are honored.
   */
  public const MAX_TOKENS = 20;

  /**
   * The length a text is cut to.
   */
  public const MAX_TEXT_LENGTH = 100;

  /**
   * Parses tokens into ids and texts.
   *
   * Invalid tokens are ignored, duplicates are dropped and only the first
   * tokens up to the limit count.
   *
   * @param mixed $raw
   *   Input as SearchTags::fromInput() reads it.
   *
   * @return array{user: int[], project: int[], text: string[]}
   *   The user ids, the project ids and the texts, in the order given.
   */
  public static function parse(mixed $raw): array {
    $parsed = ['user' => [], 'project' => [], 'text' => []];
    foreach (self::fromInput($raw) as $token) {
      [$kind, $value] = explode(':', $token, 2);
      $parsed[$kind][] = $kind === 'text' ? $value : (int) $value;
    }
    return $parsed;
  }

  /**
   * Reads the tokens of submitted input.
   *
   * The script of the search bar submits a list of tokens. Without it, the
   * Tagify input submits its JSON list of tags, and the form value is that
   * JSON list as well.
   *
   * @param mixed $input
   *   A list of tokens, a JSON list of tags, a token or anything else.
   *
   * @return string[]
   *   The valid, distinct tokens up to the limit.
   */
  public static function fromInput(mixed $input): array {
    if (is_string($input) && str_starts_with(ltrim($input), '[')) {
      $decoded = json_decode($input, TRUE);
      $input = is_array($decoded) ? array_column(array_filter($decoded, 'is_array'), 'entity_id') : [];
    }
    return self::tokens($input);
  }

  /**
   * Normalizes tokens, keeping their order.
   *
   * @param mixed $raw
   *   A token, a list of tokens or anything else.
   *
   * @return string[]
   *   The valid, distinct tokens up to the limit, texts trimmed and cut.
   */
  public static function tokens(mixed $raw): array {
    $tokens = [];
    foreach (is_array($raw) ? $raw : [$raw] as $token) {
      if (count($tokens) >= self::MAX_TOKENS) {
        break;
      }
      if (!is_string($token) || !str_contains($token, ':')) {
        continue;
      }
      [$kind, $value] = explode(':', $token, 2);
      if ($kind === 'text') {
        $value = mb_substr(trim($value), 0, self::MAX_TEXT_LENGTH);
        if ($value === '') {
          continue;
        }
      }
      elseif (!in_array($kind, ['user', 'project'], TRUE) || !preg_match('/^[1-9]\d{0,9}$/', $value)) {
        continue;
      }
      $tokens[$kind . ':' . $value] = TRUE;
    }
    return array_keys($tokens);
  }

  /**
   * Builds the tag of a user or a project.
   *
   * @return array{value: string, entity_id: string, label: string, info_label: string}
   *   The tag as Tagify reads it.
   */
  public static function tag(EntityInterface $entity): array {
    if ($entity instanceof UserInterface) {
      $token = 'user:' . $entity->id();
      $label = $entity->hasField('field_name') ? (string) $entity->get('field_name')->value : '';
      $info = Profile::isOrganization($entity) ? 'Organisation' : 'Person';
    }
    else {
      $token = 'project:' . $entity->id();
      $label = (string) $entity->label();
      $info = 'Projekt';
    }
    return ['value' => $token, 'entity_id' => $token, 'label' => $label, 'info_label' => $info];
  }

  /**
   * Builds the tag of a text.
   *
   * @return array{value: string, entity_id: string, label: string, info_label: string}
   *   The tag as Tagify reads it.
   */
  public static function textTag(string $text): array {
    $token = 'text:' . $text;
    return ['value' => $token, 'entity_id' => $token, 'label' => $text, 'info_label' => 'Text'];
  }

}
