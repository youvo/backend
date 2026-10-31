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
  public const int MAX_TOKENS = 20;

  /**
   * The length a text is cut to.
   */
  public const int MAX_TEXT_LENGTH = 100;

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
   *   The valid, distinct tokens up to the limit, texts trimmed and cut.
   */
  public static function fromInput(mixed $input): array {
    if (is_string($input) && str_starts_with(ltrim($input), '[')) {
      $decoded = json_decode($input, TRUE);
      $input = is_array($decoded) ? array_column(array_filter($decoded, 'is_array'), 'entity_id') : [];
    }
    $tokens = [];
    foreach (is_array($input) ? $input : [$input] as $token) {
      if (count($tokens) >= self::MAX_TOKENS) {
        break;
      }
      if ($token = self::normalize($token)) {
        $tokens[$token] = TRUE;
      }
    }
    return array_keys($tokens);
  }

  /**
   * Parses input into ids and texts.
   *
   * @param mixed $input
   *   Input as SearchTags::fromInput() reads it.
   *
   * @return array{user: int[], project: int[], text: string[]}
   *   The user ids, the project ids and the texts, in the order given.
   */
  public static function parse(mixed $input): array {
    $parsed = ['user' => [], 'project' => [], 'text' => []];
    foreach (self::fromInput($input) as $token) {
      [$kind, $value] = explode(':', $token, 2);
      $parsed[$kind][] = $kind === 'text' ? $value : (int) $value;
    }
    return $parsed;
  }

  /**
   * Builds the tag of a user or a project.
   *
   * @return array{value: string, entity_id: string, label: string, info_label: string}
   *   The tag as Tagify reads it.
   */
  public static function tag(EntityInterface $entity): array {
    if ($entity instanceof UserInterface) {
      $label = $entity->hasField('field_name') ? (string) $entity->get('field_name')->value : '';
      return self::build('user:' . $entity->id(), $label, Profile::isOrganization($entity) ? 'Organisation' : 'Person');
    }
    return self::build('project:' . $entity->id(), (string) $entity->label(), 'Projekt');
  }

  /**
   * Builds the tag of a text.
   *
   * The class tells text tags apart from people, organizations and projects.
   *
   * @return array{value: string, entity_id: string, label: string, info_label: string, class: string}
   *   The tag as Tagify reads it.
   */
  public static function textTag(string $text): array {
    return self::build('text:' . $text, $text, 'Text') + ['class' => 'logbook-search__text'];
  }

  /**
   * Normalizes a token: texts are trimmed and cut, ids must be positive.
   *
   * @return string|null
   *   The token, or NULL if it is invalid.
   */
  private static function normalize(mixed $token): ?string {
    if (!is_string($token) || !str_contains($token, ':')) {
      return NULL;
    }
    [$kind, $value] = explode(':', $token, 2);
    if ($kind === 'text') {
      $value = mb_substr(trim($value), 0, self::MAX_TEXT_LENGTH);
      return $value === '' ? NULL : 'text:' . $value;
    }
    if (in_array($kind, ['user', 'project'], TRUE) && preg_match('/^[1-9]\d{0,9}$/', $value)) {
      return $token;
    }
    return NULL;
  }

  /**
   * Builds a tag as Tagify reads it.
   */
  private static function build(string $token, string $label, string $info): array {
    return ['value' => $token, 'entity_id' => $token, 'label' => $label, 'info_label' => $info];
  }

}
