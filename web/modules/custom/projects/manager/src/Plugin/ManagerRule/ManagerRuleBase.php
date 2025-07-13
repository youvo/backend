<?php

namespace Drupal\manager\Plugin\ManagerRule;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\projects\ProjectInterface;
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
  public function applies(ProjectInterface $project): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function build(ProjectInterface $project): array {
    $severity = $this->getPluginDefinition()['severity'] ?? RuleSeverity::Normal;
    $category = $this->getPluginDefinition()['category'] ?? RuleCategory::Other;
    return [
      '#theme' => 'manager_rule',
      '#type' => $this->getPluginDefinition()['id'] ?? '',
      '#project' => $project,
      'content' => [
        'category' => lcfirst($category->name),
        'severity' => lcfirst($severity->name),
        'text' => $this->text($project),
      ],
    ];
  }

  /**
   * Gets the text for the notification.
   */
  abstract protected function text(ProjectInterface $project): TranslatableMarkup;

}
