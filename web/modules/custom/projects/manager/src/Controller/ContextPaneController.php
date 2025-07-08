<?php

namespace Drupal\manager\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\manager\ViewsContextPaneManager;
use Drupal\projects\Entity\Project;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides a context pane controller.
 */
class ContextPaneController implements ContainerInjectionInterface {

  public function __construct(
    protected ViewsContextPaneManager $contextPaneManager,
    protected RendererInterface $renderer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.views_context_pane'),
      $container->get('renderer')
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
    if (!$this->contextPaneManager->hasDefinition($type)) {
      $build['#theme'] = 'context_pane';
      $content = $this->renderer->render($build);
      return new Response($content, 404);
    }

    $build = $this->contextPaneManager->createInstance($type)->build($project);
    $build['#type'] = $type;
    $build['#project'] = $project;

    $content = $this->renderer->render($build);

    return new Response($content);
  }

}
