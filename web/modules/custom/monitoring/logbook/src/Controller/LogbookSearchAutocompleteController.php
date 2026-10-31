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
  protected const int LIMIT = 10;

  /**
   * The length the text needs before people and projects are suggested.
   */
  protected const int MIN_LENGTH = 2;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

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
    $text = mb_substr(trim((string) $request->query->get('q', '')), 0, SearchTags::MAX_TEXT_LENGTH);
    if ($text === '') {
      return new JsonResponse([]);
    }

    $tags = [];
    if (mb_strlen($text) >= static::MIN_LENGTH) {
      $entities = array_merge(
        // People and organizations, not the anonymous user.
        $this->match('user', 'field_name', $text, [['uid', 0, '>'], ['type', ['user', 'organization'], 'IN']]),
        $this->match('project', 'title', $text),
      );
      $tags = array_map(SearchTags::tag(...), $entities);
      usort($tags, static fn (array $a, array $b) => strcasecmp($a['label'], $b['label']));
    }

    // Tagify itself leaves out the suggestions that are tags already.
    return new JsonResponse([
      SearchTags::textTag($text),
      ...array_slice($tags, 0, static::LIMIT),
    ]);
  }

  /**
   * Loads the entities whose name field contains the text, sorted by it.
   *
   * @param string $entity_type_id
   *   The entity type.
   * @param string $field
   *   The name field.
   * @param string $text
   *   The text.
   * @param array<array{string, mixed, string}> $conditions
   *   More conditions, each as field, value and operator.
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
