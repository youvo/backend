<?php

namespace Drupal\logbook\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\logbook\SearchTags;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Suggests the tags of the logbook search.
 *
 * The typed text comes first, so it can always be searched as text. People,
 * organizations and projects whose name contains it follow. The route is
 * limited to staff who can see the logbook, so the queries skip access checks.
 */
class LogbookSearchAutocompleteController implements ContainerInjectionInterface {

  /**
   * The number of people, organizations and projects suggested.
   */
  protected const LIMIT = 10;

  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * Returns the suggestions for the typed text.
   */
  public function handle(Request $request): JsonResponse {
    $query = mb_substr(trim((string) $request->query->get('q', '')), 0, SearchTags::MAX_TEXT_LENGTH);
    if ($query === '') {
      return new JsonResponse([]);
    }
    $selected = array_flip(explode(',', (string) $request->query->get('selected', '')));

    $entities = [];
    if (mb_strlen($query) >= 2) {
      $entities = array_merge(
        // People and organizations, not the anonymous user.
        $this->match('user', 'field_name', $query, [['uid', 0, '>'], ['type', ['user', 'organization'], 'IN']]),
        $this->match('project', 'title', $query),
      );
    }
    $tags = array_map(SearchTags::tag(...), $entities);
    $tags = array_values(array_filter($tags, static fn (array $tag) => !isset($selected[$tag['entity_id']])));
    usort($tags, static fn (array $a, array $b) => strcasecmp($a['label'], $b['label']));
    $tags = array_slice($tags, 0, static::LIMIT);

    $text = SearchTags::textTag($query);
    if (!isset($selected[$text['entity_id']])) {
      array_unshift($tags, $text);
    }
    return new JsonResponse($tags);
  }

  /**
   * Loads the entities whose name field contains the text, sorted by it.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The entities.
   */
  protected function match(string $entity_type_id, string $field, string $text, array $conditions = []): array {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition($field, $text, 'CONTAINS')
      ->sort($field)
      ->range(0, static::LIMIT);
    foreach ($conditions as [$condition_field, $value, $operator]) {
      $query->condition($condition_field, $value, $operator);
    }
    return $storage->loadMultiple($query->execute());
  }

}
