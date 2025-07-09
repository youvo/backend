<?php

namespace Drupal\manager\Plugin\ViewsContextPane;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\manager\Attribute\ViewsContextPane;
use Drupal\projects\Entity\Project;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a project logbook views context pane.
 */
#[ViewsContextPane(id: "logbook")]
class ContextPaneLogbook extends ContextPaneBase {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults): static {
    $instance = parent::create($container, ...$defaults);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;

  }

  /**
   * {@inheritdoc}
   */
  public function build(Project $project): array {

    $log_storage = $this->entityTypeManager->getStorage('log');

    $log_ids = $log_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('project', $project->id())
      ->range(0, 10)
      ->sort('created', 'DESC')
      ->execute();
    if (empty($log_ids)) {
      return [];
    }

    $logs = $log_storage->loadMultiple($log_ids);

    $log_builds = [];
    foreach ($logs as $log) {
      $log_builds[] = $this->entityTypeManager
        ->getViewBuilder('log')
        ->view($log);
    }

    return [
      '#theme' => 'context_pane',
      'content' => [
        'logs' => $log_builds,
      ],
    ];
  }

}
