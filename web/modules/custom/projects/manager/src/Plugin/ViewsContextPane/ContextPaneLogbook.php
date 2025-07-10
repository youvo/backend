<?php

namespace Drupal\manager\Plugin\ViewsContextPane;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\manager\Attribute\ViewsContextPane;
use Drupal\projects\Entity\Project;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a project logbook views context pane.
 */
#[ViewsContextPane(id: "logbook")]
class ContextPaneLogbook extends ContextPaneBase {

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
      'content' => [
        'logs' => $log_builds,
      ],
    ];
  }

}
