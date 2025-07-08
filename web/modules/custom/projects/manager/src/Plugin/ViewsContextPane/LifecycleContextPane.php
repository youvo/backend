<?php

namespace Drupal\manager\Plugin\ViewsContextPane;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\manager\Attribute\ViewsContextPane;
use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectState;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a project lifecycle views context pane.
 */
#[ViewsContextPane(
  id: "lifecycle",
  label: "Lifecycle Context Pane"
)]
class LifecycleContextPane extends ContextPanePluginBase implements ContextPanePluginInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The date formatter.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults): static {
    $instance = parent::create($container, ...$defaults);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->dateFormatter = $container->get('date.formatter');
    return $instance;

  }

  /**
   * {@inheritdoc}
   */
  public function build(Project $project): array {

    $user_storage = $this->entityTypeManager->getStorage('user');

    $states = ProjectState::cases();

    // Get the "latest" history.
    $history = [];
    foreach ($project->lifecycle()->history() as $item) {
      if (isset($history[$item->to]) && $item->timestamp <= $history[$item->to]->timestamp) {
        continue;
      }
      $history[$item->to] = $item;
    }

    // Prepare data for the progression bar.
    $progression = [];

    foreach ($states as $state) {

      $info = NULL;
      $date = NULL;
      $completed = FALSE;

      if ($transition = $history[$state->value] ?? NULL) {
        $user = $user_storage->load($transition->uid);
        $info = $this->dateFormatter->format($transition->timestamp, 'short') .
          ' by ' . ($user instanceof UserInterface ? $user->getDisplayName() : $this->t('Unknown'));
        $date = $this->dateFormatter->format($transition->timestamp, 'html_date');
        $completed = TRUE;
      }

      $progression[] = [
        'label' => ucfirst($state->value),
        'completed' => $completed,
        'date' => $date ?? '',
        'info' => $info ?? '',
      ];
    }
    return [
      '#theme' => 'context_pane',
      'content' => [
        'progression' => $progression,
      ],
    ];
  }

}
