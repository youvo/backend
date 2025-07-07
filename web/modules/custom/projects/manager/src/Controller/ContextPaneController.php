<?php

namespace Drupal\manager\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\projects\Entity\Project;
use Symfony\Component\HttpFoundation\Response;

/**
 *
 */
class ContextPaneController extends ControllerBase {

  /**
   *
   */
  public function renderPane(Project $project): Response {

    $build = [
      '#theme' => 'context_pane',
      '#elements' => [
        'project' => $project,
      ],
    ];

    return new Response($this->renderer()->renderRoot($build));
  }

  /**
   *
   */
  protected function renderer(): RendererInterface {
    return \Drupal::service('renderer');
  }

}
