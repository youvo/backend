<?php

namespace Drupal\manager\Plugin\ManagerContextPane;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\manager\Attribute\ManagerContextPane;
use Drupal\manager\ManagerRules;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\projects\ProjectTransition;
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
   * The time service.
   */
  protected TimeInterface $time;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults): static {
    $instance = parent::create($container, ...$defaults);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->managerRules = $container->get('plugin.manager.manager_rules');
    $instance->time = $container->get('datetime.time');
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
        'overview' => match(TRUE) {
          $project->lifecycle()->isDraft() => $this->buildOverviewDraft($project),
          $project->lifecycle()->isPending() => $this->buildOverviewPending($project),
          $project->lifecycle()->isOpen() => $this->buildOverviewOpen($project),
          $project->lifecycle()->isOngoing() => $this->buildOverviewOngoing($project),
          $project->lifecycle()->isCompleted() => $this->buildOverviewCompleted($project),
          default => [],
        },
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
   * Builds the project overview for draft project.
   */
  protected function buildOverviewDraft(Project $project): array {

    $organization = $project->getOwner();
    if (!$organization instanceof Organization) {
      return [];
    }

    // Draft information.
    if ($organization->hasRoleProspect()) {
      $build['draft'][] = [
        '#markup' => '<p>' . $this->t('The project belongs to a prospect organization.') . '</p>',
      ];
    }
    else {
      $projects_by_organization = $this->entityTypeManager
        ->getStorage('project')
        ->loadByProperties(['uid' => $organization->id()]);
      $build['draft'][] = [
        '#markup' => '<p>' . $this->t('The organization has @count projects on the platform.', ['@count' => count($projects_by_organization)]) . '</p>',
      ];
    }

    // Pending information.
    $build['pending'][] = [];

    // Open information.
    $build['open'][] = [];

    // Ongoing information.
    $build['ongoing'][] = [];

    // Completed information.
    if (!$project->get(ProjectInterface::FIELD_DEADLINE)->isEmpty()) {
      $current_time = DrupalDateTime::createFromTimestamp($this->time->getCurrentTime());
      $deadline = DrupalDateTime::createFromFormat('Y-m-d', $project->get(ProjectInterface::FIELD_DEADLINE)->value);
      $days_left = $current_time->diff($deadline);
      if ($days_left->invert === 0) {
        $build['completed'][] = [
          '#markup' => $this->t('The project deadline is in @days days.', ['@days' => $days_left->days]),
        ];
      }
      else {
        $build['completed'][] = [
          '#markup' => $this->t('The project deadline has passed.'),
        ];
      }
    }
    else {
      $build['completed'][] = [];
    }

    return $build;
  }

  /**
   * Builds the project overview for pending project.
   */
  protected function buildOverviewPending(Project $project): array {

    $organization = $project->getOwner();
    if (!$organization instanceof Organization) {
      return [];
    }

    // Draft information.
    $projects_by_organization = $this->entityTypeManager
      ->getStorage('project')
      ->loadByProperties(['uid' => $organization->id()]);
    $build['draft'][] = [
      '#markup' => '<p>' . $this->t('The organization has @count projects on the platform.', ['@count' => count($projects_by_organization)]) . '</p>',
    ];

    // Pending information.
    foreach ($project->lifecycle()->history() as $transition) {
      if ($transition->transition === ProjectTransition::Submit->value) {
        $submitted = $transition->timestamp;
      }
    }
    if (isset($submitted)) {
      $current_time = DrupalDateTime::createFromTimestamp($this->time->getCurrentTime());
      $submitted = DrupalDateTime::createFromTimestamp($submitted);
      $days_since = $current_time->diff($submitted)->days;
      $build['pending'][] = [
        '#markup' => $this->t('The project was submitted @days days ago.', ['@days' => $days_since]),
      ];
    }
    else {
      $build['pending'][] = [];
    }

    // Open information.
    $build['open'][] = [];

    // Ongoing information.
    $build['ongoing'][] = [];

    // Completed information.
    if (!$project->get(ProjectInterface::FIELD_DEADLINE)->isEmpty()) {
      $current_time = DrupalDateTime::createFromTimestamp($this->time->getCurrentTime());
      $deadline = DrupalDateTime::createFromFormat('Y-m-d', $project->get(ProjectInterface::FIELD_DEADLINE)->value);
      $days_left = $current_time->diff($deadline);
      if ($days_left->invert === 0) {
        $build['completed'][] = [
          '#markup' => $this->t('The project deadline is in @days days.', ['@days' => $days_left->days]),
        ];
      }
      else {
        $build['completed'][] = [
          '#markup' => $this->t('The project deadline has passed.'),
        ];
      }
    }
    else {
      $build['completed'][] = [];
    }

    return $build;
  }

  /**
   * Builds the project overview for open project.
   */
  protected function buildOverviewOpen(Project $project): array {

    $organization = $project->getOwner();
    if (!$organization instanceof Organization) {
      return [];
    }

    // Draft information.
    $projects_by_organization = $this->entityTypeManager
      ->getStorage('project')
      ->loadByProperties(['uid' => $organization->id()]);
    $build['draft'][] = [
      '#markup' => '<p>' . $this->t('The organization has @count projects on the platform.', ['@count' => count($projects_by_organization)]) . '</p>',
    ];

    // Pending information.
    $build['pending'][] = [];

    // Open information.
    if ($applicants = $project->getApplicants()) {
      $view_builder = $this->entityTypeManager->getViewBuilder('user');
      $build['open'][] = [
        '#markup' => '<div class="status-overview-creatives">',
      ];
      foreach ($applicants as $applicant) {
        $build['open'][] = $view_builder->view($applicant, 'avatar');
      }
      $build['open'][] = [
        '#markup' => '</div>' . $this->t('The project has @count applicant(s).', ['@count' => count($applicants)]),
      ];
    }
    else {
      $build['open'][] = [];
    }

    // Ongoing information.
    $build['ongoing'][] = [];

    // Completed information.
    if (!$project->get(ProjectInterface::FIELD_DEADLINE)->isEmpty()) {
      $current_time = DrupalDateTime::createFromTimestamp($this->time->getCurrentTime());
      $deadline = DrupalDateTime::createFromFormat('Y-m-d', $project->get(ProjectInterface::FIELD_DEADLINE)->value);
      $days_left = $current_time->diff($deadline);
      if ($days_left->invert === 0) {
        $build['completed'][] = [
          '#markup' => $this->t('The project deadline is in @days days.', ['@days' => $days_left->days]),
        ];
      }
      else {
        $build['completed'][] = [
          '#markup' => $this->t('The project deadline has passed.'),
        ];
      }
    }
    else {
      $build['completed'][] = [];
    }

    return $build;
  }

  /**
   * Builds the project overview for ongoing project.
   */
  protected function buildOverviewOngoing(Project $project): array {

    $organization = $project->getOwner();
    if (!$organization instanceof Organization) {
      return [];
    }

    // Draft information.
    $projects_by_organization = $this->entityTypeManager
      ->getStorage('project')
      ->loadByProperties(['uid' => $organization->id()]);
    $build['draft'][] = [
      '#markup' => '<p>' . $this->t('The organization has @count projects on the platform.', ['@count' => count($projects_by_organization)]) . '</p>',
    ];

    // Pending information.
    $build['pending'][] = [];

    // Open information.
    if ($applicants = $project->getApplicants()) {
      $view_builder = $this->entityTypeManager->getViewBuilder('user');
      $build['open'][] = [
        '#markup' => '<div class="status-overview-creatives">',
      ];
      foreach ($applicants as $applicant) {
        $build['open'][] = $view_builder->view($applicant, 'avatar');
      }
      $build['open'][] = [
        '#markup' => '</div>' . $this->t('The project had @count applicant(s).', ['@count' => count($applicants)]),
      ];
    }
    else {
      $build['open'][] = [];
    }

    // Ongoing information.
    if ($participants = $project->getParticipants('Creative')) {
      $view_builder = $this->entityTypeManager->getViewBuilder('user');
      $build['ongoing'][] = [
        '#markup' => '<div class="status-overview-creatives">',
      ];
      foreach ($participants as $participant) {
        $build['ongoing'][] = $view_builder->view($participant, 'avatar');
      }
      $build['ongoing'][] = [
        '#markup' => '</div>' . $this->t('The project has @count participant(s).', ['@count' => count($participants)]),
      ];
    }
    else {
      $build['ongoing'][] = [];
    }

    // Completed information.
    if (!$project->get(ProjectInterface::FIELD_DEADLINE)->isEmpty()) {
      $current_time = DrupalDateTime::createFromTimestamp($this->time->getCurrentTime());
      $deadline = DrupalDateTime::createFromFormat('Y-m-d', $project->get(ProjectInterface::FIELD_DEADLINE)->value);
      $days_left = $current_time->diff($deadline);
      if ($days_left->invert === 0) {
        $build['completed'][] = [
          '#markup' => $this->t('The project deadline is in @days days.', ['@days' => $days_left->days]),
        ];
      }
      else {
        $build['completed'][] = [
          '#markup' => $this->t('The project deadline has passed.'),
        ];
      }
    }
    else {
      $build['completed'][] = [];
    }

    return $build;
  }

  /**
   * Builds the project overview for completed project.
   */
  protected function buildOverviewCompleted(Project $project): array {

    $organization = $project->getOwner();
    if (!$organization instanceof Organization) {
      return [];
    }

    // Draft information.
    $projects_by_organization = $this->entityTypeManager
      ->getStorage('project')
      ->loadByProperties(['uid' => $organization->id()]);
    $build['draft'][] = [
      '#markup' => '<p>' . $this->t('The organization has @count projects on the platform.', ['@count' => count($projects_by_organization)]) . '</p>',
    ];

    // Pending information.
    $build['pending'][] = [];

    // Open information.
    if ($applicants = $project->getApplicants()) {
      $view_builder = $this->entityTypeManager->getViewBuilder('user');
      $build['open'][] = [
        '#markup' => '<div class="status-overview-creatives">',
      ];
      foreach ($applicants as $applicant) {
        $build['open'][] = $view_builder->view($applicant, 'avatar');
      }
      $build['open'][] = [
        '#markup' => '</div>' . $this->t('The project had @count applicant(s).', ['@count' => count($applicants)]),
      ];
    }
    else {
      $build['open'][] = [];
    }

    // Ongoing information.
    if ($participants = $project->getParticipants('Creative')) {
      $view_builder = $this->entityTypeManager->getViewBuilder('user');
      $build['ongoing'][] = [
        '#markup' => '<div class="status-overview-creatives">',
      ];
      foreach ($participants as $participant) {
        $build['ongoing'][] = $view_builder->view($participant, 'avatar');
      }
      $build['ongoing'][] = [
        '#markup' => '</div>' . $this->t('The project had @count participant(s).', ['@count' => count($participants)]),
      ];
    }
    else {
      $build['ongoing'][] = [];
    }

    // Completed information.
    $build['completed'][] = [
      '#markup' => $this->t('The project is finished.'),
    ];

    return $build;
  }

  /**
   * Builds the project manager rules.
   */
  protected function buildRules(Project $project): array {
    foreach ($this->managerRules->getRules($project) as $rule) {
      $build[] = $rule->build($project);
    }
    return $build ?? [];
  }

}
