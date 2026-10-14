<?php

namespace Drupal\manager\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\logbook\LogInterface;
use Drupal\manager\ProjectSeenTracker;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Shows the unfinished projects of a manager and what is new in them.
 */
#[Block(
  id: 'manager_projects',
  admin_label: new TranslatableMarkup('Manager projects'),
  category: new TranslatableMarkup('Project manager'),
)]
class ManagerProjectsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The number of projects the block shows at most.
   */
  public const LIMIT = 20;

  /**
   * Constructs the block.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ProjectSeenTracker $tracker,
    protected AccountProxyInterface $currentUser,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RequestStack $requestStack,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('manager.project_seen'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'general manager access');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $uid = (int) $this->currentUser->id();
    // One more than shown tells whether there are more, without loading all.
    $projects = $this->tracker->getManagedProjects($this->currentUser, self::LIMIT + 1);
    $shown = array_slice($projects, 0, self::LIMIT, TRUE);
    $activity = $this->tracker->getActivity($this->currentUser, $shown);
    $destination = $this->requestStack->getCurrentRequest()?->getPathInfo() ?: '/admin/dashboard';
    $options = ['query' => ['destination' => $destination]];

    $cards = [];
    $any_new = FALSE;
    foreach ($shown as $id => $project) {
      $item = $activity[$id];
      $any_new = $any_new || $item['is_new'];
      $cards[] = [
        'id' => $id,
        'title' => $project->label(),
        'url' => $project->toUrl()->toString(),
        'organization' => $project->getOwner()->getDisplayName(),
        'state' => ucfirst((string) $project->get('field_lifecycle')->value),
        'is_new' => $item['is_new'],
        'count' => $item['count'],
        'seen_url' => $item['is_new'] ? Url::fromRoute('manager.project_seen', ['project' => $id], $options)->toString() : NULL,
        'progress' => $this->buildProgress($project),
        'logs' => array_map(static fn (LogInterface $log) => [
          'label' => trim(str_replace('Project ', '', (string) $log->getPattern()->label())),
          'text' => ['#markup' => $log->getMarkup()],
        ], $item['latest']),
      ];
    }

    $list_tags = array_merge(
      $this->entityTypeManager->getDefinition('project')->getListCacheTags(),
      $this->entityTypeManager->getDefinition('log')->getListCacheTags(),
      $this->entityTypeManager->getDefinition('log_pattern')->getListCacheTags(),
      $this->entityTypeManager->getDefinition('user')->getListCacheTags(),
    );

    return [
      '#theme' => 'manager_projects_block',
      '#cards' => $cards,
      '#more_url' => count($projects) > self::LIMIT ? Url::fromUserInput('/projects/manage')->toString() : NULL,
      '#seen_all_url' => $any_new ? Url::fromRoute('manager.project_seen_all', [], $options)->toString() : NULL,
      '#attached' => ['library' => ['manager/projects_block']],
      '#cache' => [
        // The buttons carry a CSRF token, which belongs to the session.
        'contexts' => ['user', 'session', 'url.path'],
        'tags' => array_merge($list_tags, [ProjectSeenTracker::cacheTag($uid)]),
      ],
    ];
  }

  /**
   * Builds the steps of the lifecycle progress bar of a project.
   *
   * The steps are the lifecycle states without archived, like the progress bar
   * of the manager pane. A step is completed if the project reached it.
   *
   * @return array<int, array{label: string, completed: bool, next_completed: bool}>
   *   The steps.
   */
  protected function buildProgress(ProjectInterface $project): array {
    $reached = [(string) $project->get('field_lifecycle')->value => TRUE];
    foreach ($project->lifecycle()->history() as $item) {
      $reached[$item->to] = TRUE;
    }
    $states = array_values(array_filter(ProjectState::cases(), static fn (ProjectState $state) => $state !== ProjectState::Archived));
    $steps = [];
    foreach ($states as $index => $state) {
      $next = $states[$index + 1] ?? NULL;
      $steps[] = [
        'label' => ucfirst($state->value),
        'completed' => isset($reached[$state->value]),
        'next_completed' => $next !== NULL && isset($reached[$next->value]),
      ];
    }
    return $steps;
  }

}
