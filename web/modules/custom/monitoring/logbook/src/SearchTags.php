<?php

namespace Drupal\logbook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\user\UserInterface;

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
   *   A token, a list of tokens or anything else.
   *
   * @return array{user: int[], project: int[], text: string[]}
   *   The user ids, the project ids and the texts, in the order given.
   */
  public static function parse(mixed $raw): array {
    $parsed = ['user' => [], 'project' => [], 'text' => []];
    $tokens = is_array($raw) ? $raw : [$raw];
    $count = 0;
    foreach ($tokens as $token) {
      if ($count >= self::MAX_TOKENS) {
        break;
      }
      if (!is_string($token) || !str_contains($token, ':')) {
        continue;
      }
      [$kind, $value] = explode(':', $token, 2);
      if ($kind === 'text') {
        $value = mb_substr(trim($value), 0, self::MAX_TEXT_LENGTH);
        if ($value === '' || in_array($value, $parsed['text'], TRUE)) {
          continue;
        }
        $parsed['text'][] = $value;
        $count++;
      }
      elseif (($kind === 'user' || $kind === 'project') && preg_match('/^[1-9]\d{0,9}$/', $value)) {
        $id = (int) $value;
        if (in_array($id, $parsed[$kind], TRUE)) {
          continue;
        }
        $parsed[$kind][] = $id;
        $count++;
      }
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
      $token = 'user:' . $entity->id();
      $label = $entity->hasField('field_name') ? (string) $entity->get('field_name')->value : '';
      $info = $entity->bundle() === 'organization' ? 'Organisation' : 'Person';
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
