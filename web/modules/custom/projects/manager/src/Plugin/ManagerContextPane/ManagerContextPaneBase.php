<?php

namespace Drupal\manager\Plugin\ManagerContextPane;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\projects\Entity\Project;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for context pane plugins.
 */
abstract class ManagerContextPaneBase extends PluginBase implements ContainerFactoryPluginInterface, ManagerContextPaneInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Returns the render array for the context pane.
   *
   * @param \Drupal\projects\Entity\Project $project
   *   The project entity or object.
   *
   * @return array
   *   A render array for the context pane.
   */
  abstract public function build(Project $project): array;

}
