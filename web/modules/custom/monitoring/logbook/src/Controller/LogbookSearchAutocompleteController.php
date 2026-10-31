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
      $entities = array_merge($this->matchUsers($query), $this->matchProjects($query));
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
   * Loads the people and organizations whose name contains the text.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The users.
   */
  protected function matchUsers(string $text): array {
    $storage = $this->entityTypeManager->getStorage('user');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', 0, '>')
      ->condition('type', ['user', 'organization'], 'IN')
      ->condition('field_name', $text, 'CONTAINS')
      ->sort('field_name')
      ->range(0, static::LIMIT)
      ->execute();
    return $storage->loadMultiple($ids);
  }

  /**
   * Loads the projects whose title contains the text.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The projects.
   */
  protected function matchProjects(string $text): array {
    $storage = $this->entityTypeManager->getStorage('project');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('title', $text, 'CONTAINS')
      ->sort('title')
      ->range(0, static::LIMIT)
      ->execute();
    return $storage->loadMultiple($ids);
  }

}
