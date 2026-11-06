<?php

namespace Drupal\mailer\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\projects\Service\ProjectLifecycle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Suggests the projects and people of the door link form.
 *
 * With text, the entities whose name contains it are suggested. Without, the
 * field opens with the recent projects or the people of the chosen project.
 * Each tag names its type in a chip. The route is limited to the staff who
 * make door links, so the queries skip access checks.
 */
class DoorLinkAutocompleteController implements ContainerInjectionInterface {

  /**
   * The number of suggestions.
   */
  const int LIMIT = 10;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Creates the controller with its services.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Returns the suggestions of a field.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request, with the text as 'q' and the chosen project as 'project'.
   * @param string $kind
   *   The field: project, recipient or subject.
   */
  public function handle(Request $request, string $kind): JsonResponse {

    $text = trim((string) $request->query->get('q', ''));
    if ($text !== '') {
      $entities = match ($kind) {
        'project' => $this->matchProjects($text),
        'recipient' => $this->matchUsers($text, ['user', 'organization']),
        default => $this->matchUsers($text, ['user']),
      };
    }
    elseif ($kind === 'project') {
      $entities = $this->recentProjects();
    }
    else {
      $entities = $this->projectPeople($kind, $request->query->get('project'));
    }

    $tags = array_map(static::tag(...), array_values($entities));
    return new JsonResponse(array_slice($tags, 0, static::LIMIT));
  }

  /**
   * Builds the tag of a project or user as Tagify reads it.
   *
   * The chip names the state of a project and the type of a user.
   *
   * @return array{value: string, entity_id: string, label: string, info_label: string}
   *   The tag.
   */
  public static function tag(EntityInterface $entity): array {
    $info_label = '';
    if ($entity instanceof ProjectInterface) {
      $info_label = match (ProjectState::tryFrom((string) $entity->get(ProjectLifecycle::LIFECYCLE_FIELD)->value)) {
        ProjectState::Draft => 'Entwurf',
        ProjectState::Pending => 'Eingereicht',
        ProjectState::Open => 'Offen',
        ProjectState::Ongoing => 'Laufend',
        ProjectState::Completed => 'Abgeschlossen',
        ProjectState::Declined => 'Abgelehnt',
        NULL => '',
      };
    }
    elseif ($entity instanceof Organization) {
      $info_label = $entity->hasRoleProspect() ? 'Prospect' : 'Organisation';
    }
    elseif ($entity instanceof Creative) {
      $info_label = 'Creative';
    }
    return [
      'value' => (string) $entity->id(),
      'entity_id' => (string) $entity->id(),
      'label' => (string) $entity->label(),
      'info_label' => $info_label,
    ];
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

  /**
   * Loads the users whose name, account name or mail contains the text.
   *
   * @param string $text
   *   The text.
   * @param string[] $bundles
   *   The user bundles.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The users.
   */
  protected function matchUsers(string $text, array $bundles): array {
    $storage = $this->entityTypeManager->getStorage('user');
    $query = $storage->getQuery()->accessCheck(FALSE);
    $name = $query->orConditionGroup()
      ->condition('field_name', $text, 'CONTAINS')
      ->condition('name', $text, 'CONTAINS')
      ->condition('mail', $text, 'CONTAINS');
    $ids = $query
      ->condition($name)
      ->condition('uid', 0, '>')
      ->condition('type', $bundles, 'IN')
      ->sort('field_name')
      ->sort('name')
      ->range(0, static::LIMIT)
      ->execute();
    return $storage->loadMultiple($ids);
  }

  /**
   * Loads the most recently changed projects.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The projects.
   */
  protected function recentProjects(): array {
    $storage = $this->entityTypeManager->getStorage('project');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->sort('changed', 'DESC')
      ->range(0, static::LIMIT)
      ->execute();
    return $storage->loadMultiple($ids);
  }

  /**
   * Gets the people of the chosen project a field may name.
   *
   * The recipient may be the owner, an applicant or a participating creative,
   * the subject only an applicant.
   *
   * @param string $kind
   *   The field: recipient or subject.
   * @param mixed $project_id
   *   The ID of the chosen project, if any.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The people, keyed by user ID.
   */
  protected function projectPeople(string $kind, mixed $project_id): array {

    if (!is_string($project_id) || !ctype_digit($project_id) || (int) $project_id <= 0) {
      return [];
    }
    $project = $this->entityTypeManager->getStorage('project')->load($project_id);
    if (!$project instanceof ProjectInterface) {
      return [];
    }

    if ($kind === 'subject') {
      return $project->getApplicants();
    }

    $owner = $project->getOwner();
    // The later lists do not replace the people found before.
    return [$owner->id() => $owner] + $project->getApplicants() + $project->getParticipants('Creative');
  }

}
