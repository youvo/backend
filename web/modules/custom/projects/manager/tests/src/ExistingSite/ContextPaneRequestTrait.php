<?php

namespace Drupal\Tests\manager\ExistingSite;

use Drupal\Core\Form\FormState;
use Drupal\manager\Controller\ContextPaneController;
use Drupal\projects\ProjectInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Drives the context pane controller in the test process.
 *
 * The panes need a session on the request: the messenger keeps its messages
 * in it and the form API caches forms with it. A request through the HTTP
 * kernel gets no session on the command line (the session middleware skips
 * the CLI), so the controller is called directly with a request that carries
 * a mock session, shared by all requests of a test. Route access is not part
 * of these requests; ManagerRouteAccessTest covers it.
 */
trait ContextPaneRequestTrait {

  /**
   * The session shared by all pane requests of a test.
   */
  protected ?Session $paneSession = NULL;

  /**
   * Runs the pane controller for a request and returns the HTML.
   *
   * @param \Drupal\projects\ProjectInterface $project
   *   The project of the pane URL.
   * @param array<string, string|int> $query
   *   The query, with the pane type.
   * @param array<string, mixed>|null $post
   *   POST values, or NULL for a GET request.
   *
   * @return string
   *   The response body.
   */
  protected function pane(ProjectInterface $project, array $query, ?array $post = NULL): string {
    $uri = '/project/manage/context/' . $project->id() . '?' . http_build_query($query);
    $request = Request::create($uri, $post === NULL ? 'GET' : 'POST', $post ?? []);
    $request->setSession($this->paneSession ??= new Session(new MockArraySessionStorage()));
    // The form builder and validator keep per-request state (the validator
    // remembers validated form ids): start every request fresh.
    $this->container->set('form_builder', NULL);
    $this->container->set('form_validator', NULL);
    // FormState::$anyErrors is a process-wide static that a real request never
    // carries over.
    (new \ReflectionProperty(FormState::class, 'anyErrors'))->setValue(NULL, FALSE);
    $stack = $this->container->get('request_stack');
    $stack->push($request);
    try {
      $response = ContextPaneController::create($this->container)->contextPane($project, $request);
    }
    finally {
      $stack->pop();
    }
    return (string) $response->getContent();
  }

  /**
   * Gets the hidden POST values of the form rendered in the HTML.
   *
   * @return array<string, string>
   *   The form_id, form_build_id, form_token and the button.
   */
  protected function hiddenValues(string $html, string $op): array {
    $values = ['op' => $op];
    foreach (['form_id', 'form_build_id', 'form_token'] as $name) {
      $this->assertSame(1, preg_match('/name="' . $name . '" value="([^"]*)"/', $html, $match), $name . ' is in the markup');
      $values[$name] = html_entity_decode($match[1]);
    }
    return $values;
  }

  /**
   * Builds a form with a form cache entry and returns its form_build_id.
   *
   * Plain GET-built forms are not cached, but a cached build info (a form
   * state that was cached, as ajax or other modules do) is restored on a POST
   * with its form_build_id: the build info of that earlier build must not be
   * trusted.
   *
   * @param class-string<\Drupal\Core\Form\FormInterface> $form_class
   *   The form class.
   * @param array<int, mixed> $args
   *   The build arguments.
   */
  protected function cachedBuildId(string $form_class, array $args): string {
    // Caching a form state is only allowed on non-GET requests.
    $request = Request::create('/', 'POST');
    $request->setSession($this->paneSession ??= new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);
    $form_state = new FormState();
    $form_state->setRequestMethod('POST');
    $form_state->disableRedirect()->setCached();
    $form_state->addBuildInfo('args', $args);
    $form = $this->container->get('form_builder')->buildForm($form_class, $form_state);
    $this->container->get('request_stack')->pop();
    return $form['#build_id'];
  }

  /**
   * Asserts that the messenger holds nothing.
   */
  protected function assertMessengerEmpty(): void {
    $this->assertSame([], $this->container->get('messenger')->all());
  }

}
