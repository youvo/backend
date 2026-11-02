<?php

namespace Drupal\logbook\Hook;

use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;

/**
 * Restricts access-checked log queries by the project of the log.
 *
 * The query access event of the entity module only takes fields of the log:
 * conditions on the referenced project need a join.
 *
 * @see \Drupal\logbook\LogAccessControlHandler
 * @see \Drupal\logbook\EventSubscriber\LogQueryAccessSubscriber
 */
class LogQueryAccessHooks {

  public function __construct(protected AccountInterface $currentUser) {}

  /**
   * Implements hook_query_TAG_alter() for log_access.
   *
   * Hides logs of hidden projects on the public level. Views and entity
   * queries both carry the tag. The cached logbook view follows project_list.
   */
  #[Hook('query_log_access_alter')]
  public function hiddenProjects(AlterableInterface $query): void {

    $account = $query->getMetaData('account') ?? $this->currentUser;
    if (
      !$query instanceof SelectInterface ||
      $account->hasPermission('view detectable log') ||
      $account->hasPermission('view observable log')
    ) {
      return;
    }

    $tables = array_column($query->getTables(), 'table', 'alias');
    $log = array_search('log', $tables, TRUE);
    if ($log === FALSE) {
      return;
    }

    // Projects are translatable: join only the default translation, so that
    // logs are not repeated.
    $project = $query->leftJoin('project_field_data', 'log_project', "%alias.id = $log.project AND %alias.default_langcode = 1");
    $query->condition($query->orConditionGroup()
      ->isNull("$log.project")
      ->condition("$project.hidden", 0));
  }

}
