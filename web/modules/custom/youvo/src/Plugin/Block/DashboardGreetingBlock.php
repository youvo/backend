<?php

namespace Drupal\youvo\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a greeting with the current user's name to the dashboard.
 */
#[Block(
  id: 'youvo_dashboard_greeting',
  admin_label: new TranslatableMarkup('Greeting'),
  category: new TranslatableMarkup('Dashboard'),
)]
class DashboardGreetingBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   */
  protected AccountInterface $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->entityTypeManager
      ->getStorage('user')
      ->load($this->currentUser->id());
    $name = $user->hasField('field_name') ? $user->get('field_name')->value : NULL;
    // Nest the element, as top-level attributes are not rendered for blocks.
    return [
      'greeting' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Hello, @name!', ['@name' => $name ?: $user->getDisplayName()]),
        '#attributes' => ['class' => ['youvo-dashboard-greeting']],
        '#attached' => ['library' => ['youvo/dashboard']],
      ],
      '#cache' => [
        'contexts' => ['user'],
        'tags' => $user->getCacheTags(),
      ],
    ];
  }

}
