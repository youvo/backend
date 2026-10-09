<?php

namespace Drupal\manager\Plugin\ManagerContextPane;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\manager\Attribute\ManagerContextPane;
use Drupal\projects\Entity\Project;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a project logbook manager context pane.
 */
#[ManagerContextPane(id: "logbook")]
class ManagerContextPaneLogbook extends ManagerContextPaneBase {

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

    $logs = array_reverse($this->entityTypeManager
      ->getStorage('log')
      ->loadByProperties(['project' => $project->id()]));

    $log_builds = [];
    foreach ($logs as $log) {
      $log_builds[] = $this->entityTypeManager
        ->getViewBuilder('log')
        ->view($log);
    }

    return [
      '#theme' => 'context_pane',
      '#type' => 'logbook',
      '#project' => $project,
      'content' => [
        'logs' => $log_builds,
      ],
    ];
  }

}
