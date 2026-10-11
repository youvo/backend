<?php

namespace Drupal\manager\Plugin\ManagerContextPane;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\manager\Attribute\ManagerContextPane;
use Drupal\manager\Timeline\TimelineBuilder;
use Drupal\projects\Entity\Project;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a project logbook manager context pane.
 */
#[ManagerContextPane(id: "logbook")]
class ManagerContextPaneLogbook extends ManagerContextPaneBase {

  /**
   * The timeline builder.
   */
  protected TimelineBuilder $timelineBuilder;

  /**
   * The current user.
   */
  protected AccountProxyInterface $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults): static {
    $instance = parent::create($container, ...$defaults);
    $instance->timelineBuilder = $container->get('manager.timeline_builder');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build(Project $project): array {
    return [
      '#theme' => 'context_pane',
      '#type' => 'logbook',
      '#project' => $project,
      'content' => [
        'rows' => $this->timelineBuilder->build($project, $this->currentUser),
      ],
    ];
  }

}
