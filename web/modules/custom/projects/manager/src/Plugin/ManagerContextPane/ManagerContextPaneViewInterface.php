<?php

namespace Drupal\manager\Plugin\ManagerContextPane;

use Drupal\projects\Entity\Project;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a context pane that has several views and processes form requests.
 *
 * The controller calls buildView() instead of build() for panes that implement
 * this interface. The pane decides the view from the request query and must
 * check access on every call (GET and POST): the route only requires the
 * general manager access permission.
 */
interface ManagerContextPaneViewInterface extends ManagerContextPaneInterface {

  /**
   * Builds the render array for the view requested by the request.
   *
   * @param \Drupal\projects\Entity\Project $project
   *   The project entity.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request (GET or POST).
   *
   * @return array
   *   A render array for the context pane.
   */
  public function buildView(Project $project, Request $request): array;

}
