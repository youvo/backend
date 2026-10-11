<?php

namespace Drupal\manager\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\manager\ManagerContextPanes;
use Drupal\manager\Plugin\ManagerContextPane\ManagerContextPaneViewInterface;
use Drupal\projects\Entity\Project;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides a context pane controller.
 */
class ContextPaneController implements ContainerInjectionInterface {

  public function __construct(
    protected ManagerContextPanes $managerContextPanes,
    protected RendererInterface $renderer,
    protected MessengerInterface $messenger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.manager_context_panes'),
      $container->get('renderer'),
      $container->get('messenger'),
    );
  }

  /**
   * Returns the context pane for a project and type.
   *
   * @param \Drupal\projects\Entity\Project $project
   *   The project entity (adjust namespace if needed).
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A context pane response.
   */
  public function contextPane(Project $project, Request $request): Response {

    $type = $request->query->get('type', '');
    if (!$this->managerContextPanes->hasDefinition($type)) {
      // Builds an empty context pane.
      $build['#theme'] = 'context_pane';
      $content = $this->renderer->renderInIsolation($build);
      return new Response($content, 404);
    }

    $pane = $this->managerContextPanes->createInstance($type);
    $has_views = $pane instanceof ManagerContextPaneViewInterface;
    $build = $has_views ? $pane->buildView($project, $request) : $pane->build($project);
    // The response is a bare Response that never runs the placeholder
    // processor, so render in isolation to get the form action and token.
    $content = $this->renderer->renderInIsolation($build);
    // Panes with views render their messages themselves (status_messages
    // clears them). Whatever is left (denied or empty panes, form API errors
    // that nobody rendered) must not leak into the next full page of the
    // session. The other panes behave as before.
    if ($has_views) {
      $this->messenger->deleteAll();
    }

    return new Response($content);
  }

}
