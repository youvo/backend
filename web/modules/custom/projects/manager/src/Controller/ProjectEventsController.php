<?php

namespace Drupal\manager\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\manager\ProjectSeenTracker;
use Drupal\projects\ProjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lists all events of a project for the dropdown of the dashboard block.
 *
 * The block loads the list when a manager opens the dropdown, so the dashboard
 * does not render the events of every project.
 */
class ProjectEventsController extends ControllerBase {

  public function __construct(
    protected ProjectSeenTracker $tracker,
    protected RendererInterface $renderer,
    protected DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('manager.project_seen'),
      $container->get('renderer'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Checks if the user manages the organization of the project.
   */
  public function access(AccountInterface $account, ProjectInterface $project): AccessResultInterface {
    return AccessResult::allowedIf($this->tracker->isManagedBy($account, $project))
      ->addCacheContexts(['user'])
      ->addCacheableDependency($project);
  }

  /**
   * Returns the list of the events as HTML.
   */
  public function events(ProjectInterface $project): Response {
    $result = $this->tracker->getEvents($this->currentUser(), $project);
    $events = [];
    foreach ($result['events'] as $event) {
      $log = $event['log'];
      $events[] = [
        'date' => $this->dateFormatter->format($log->getCreatedTime(), 'custom', 'd.m.Y H:i'),
        'label' => trim(str_replace('Project ', '', (string) $log->getPattern()->label())),
        'text' => ['#markup' => $log->getMarkup()],
        'is_new' => $event['is_new'],
      ];
    }
    $build = [
      '#theme' => 'manager_project_events',
      '#events' => $events,
      '#total' => $result['total'],
    ];
    $response = new Response((string) $this->renderer->renderInIsolation($build));
    $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
    // The list is the list of one user: nothing may keep it.
    $response->headers->set('Cache-Control', 'no-store, private');
    return $response;
  }

}
