<?php

namespace Drupal\manager\Plugin\ManagerContextPane;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\manager\Attribute\ManagerContextPane;
use Drupal\manager\ManagerRules;
use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectState;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a project status manager context pane.
 */
#[ManagerContextPane(id: "status")]
class ManagerContextPaneStatus extends ManagerContextPaneBase {

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
   * The manager rules.
   */
  protected ManagerRules $managerRules;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults): static {
    $instance = parent::create($container, ...$defaults);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->managerRules = $container->get('plugin.manager.manager_rules');
    return $instance;

  }

  /**
   * {@inheritdoc}
   */
  public function build(Project $project): array {
    return [
      '#theme' => 'context_pane',
      '#type' => 'status',
      '#project' => $project,
      'content' => [
        'progression' => $this->buildProgression($project),
        'rules' => $this->buildRules($project),
      ],
    ];
  }

  /**
   * Builds the project lifecycle progression.
   */
  protected function buildProgression(Project $project): array {

    $user_storage = $this->entityTypeManager->getStorage('user');
    $states = ProjectState::cases();

    // Get the latest history.
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
        if (isset($previous_state)) {
          $progression[$previous_state]['next_completed'] = TRUE;
        }
      }

      $progression[$state->value] = [
        'label' => ucfirst($state->value),
        'completed' => $completed,
        'next_completed' => FALSE,
        'date' => $date ?? '',
        'info' => $info ?? '',
      ];

      $previous_state = $state->value;
    }

    return $progression;
  }

  /**
   * Builds the project manager rules.
   */
  protected function buildRules(Project $project): array {

    $build = [];

    foreach ($this->managerRules->getRules($project) as $rule) {
      $build[] = $rule->build($project);
    }

    return $build;
  }

}
