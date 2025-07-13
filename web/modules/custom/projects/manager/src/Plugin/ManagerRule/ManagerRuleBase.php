<?php

namespace Drupal\manager\Plugin\ManagerRule;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\projects\Entity\Project;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for manager rule plugins.
 */
abstract class ManagerRuleBase extends PluginBase implements ContainerFactoryPluginInterface, ManagerRuleInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Project $project): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function status(): RuleStatus {
    return RuleStatus::Warning;
  }

  /**
   * {@inheritdoc}
   */
  public function priority(Project $project): int {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  abstract public function build(Project $project): array;

}
