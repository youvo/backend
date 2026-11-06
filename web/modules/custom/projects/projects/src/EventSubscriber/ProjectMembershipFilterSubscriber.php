<?php

namespace Drupal\projects\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\jsonapi\Context\FieldResolver;
use Drupal\jsonapi\Query\EntityCondition;
use Drupal\jsonapi\Query\EntityConditionGroup;
use Drupal\jsonapi\Query\Filter;
use Drupal\jsonapi\Query\Sort;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\Routing\Routes;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Lets creatives filter projects only by their own applications.
 *
 * Creatives may filter by the applicants and participants, so that their
 * dashboard lists where they applied and participate. Any other filter or sort
 * on these fields would reveal where another creative applied. This allows
 * only the condition that the creative is one of them: whatever conditions it
 * is combined with, the result depends on no one else's applications.
 *
 * @see \Drupal\projects\Access\ProjectFieldAccess::checkFieldAccessWithoutEntity()
 */
class ProjectMembershipFilterSubscriber implements EventSubscriberInterface {

  const array MEMBERSHIP_FIELDS = [
    'field_applicants',
    'field_participants',
  ];

  const array ALLOWED_PATHS = [
    'field_applicants.entity:user.uuid',
    'field_participants.entity:user.uuid',
  ];

  public function __construct(
    protected AccountInterface $currentUser,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ?FieldResolver $fieldResolver = NULL,
  ) {}

  /**
   * Refuses filters and sorts on the applicants or participants of others.
   *
   * Runs after the routing and before the dynamic page cache, so that a cached
   * response of one creative is never served to another.
   */
  public function onRequest(RequestEvent $event): void {

    // Without JSON:API, there are no filters to check.
    if ($this->fieldResolver === NULL) {
      return;
    }

    $request = $event->getRequest();
    if (!Routes::isJsonApiRequest($request->attributes->all()) ||
      (!$request->query->has('filter') && !$request->query->has('sort'))
    ) {
      return;
    }

    // Only creatives pass the field access for these filters. Administrators
    // filter freely, all others are refused by the field access.
    if (!$this->currentUser->hasPermission('general creative access') ||
      $this->currentUser->hasPermission('administer projects')
    ) {
      return;
    }

    $resource_type = $request->attributes->get(Routes::RESOURCE_TYPE_KEY);
    if (!$resource_type instanceof ResourceType) {
      return;
    }

    // No sort on these fields.
    if ($request->query->has('sort')) {
      $sort = Sort::createFromQueryParameter($request->query->all()['sort']);
      foreach ($sort->fields() as $field) {
        $path = $this->fieldResolver->resolveInternalEntityQueryPath($resource_type, $field[Sort::PATH_KEY]);
        if (array_intersect(explode('.', $path), static::MEMBERSHIP_FIELDS)) {
          throw new AccessDeniedHttpException('Sorting by applicants or participants is not allowed.');
        }
      }
    }

    // Only the condition that the creative is one of them.
    if ($request->query->has('filter')) {
      $filter = Filter::createFromQueryParameter($request->query->all('filter'), $resource_type, $this->fieldResolver);
      $uuid = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id())?->uuid();
      $conditions = [$filter->root()];
      while ($condition = array_pop($conditions)) {
        if ($condition instanceof EntityConditionGroup) {
          array_push($conditions, ...$condition->members());
          continue;
        }
        if (!$condition instanceof EntityCondition ||
          !array_intersect(explode('.', $condition->field()), static::MEMBERSHIP_FIELDS)
        ) {
          continue;
        }
        if ($resource_type->getEntityTypeId() !== 'project' ||
          !in_array($condition->field(), static::ALLOWED_PATHS, TRUE) ||
          $condition->operator() !== '=' ||
          $uuid === NULL ||
          $condition->value() !== $uuid
        ) {
          throw new AccessDeniedHttpException('Filtering by applicants or participants is only allowed for yourself.');
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // After the router (32), before the dynamic page cache (27).
    return [KernelEvents::REQUEST => ['onRequest', 30]];
  }

}
