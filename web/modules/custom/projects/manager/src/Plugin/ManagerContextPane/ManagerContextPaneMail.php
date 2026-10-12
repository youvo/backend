<?php

namespace Drupal\manager\Plugin\ManagerContextPane;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\mailer\Manager\ManagerMailAccess;
use Drupal\mailer\Manager\ManagerMailRegistry;
use Drupal\manager\Attribute\ManagerContextPane;
use Drupal\manager\Form\ProjectMailForm;
use Drupal\projects\Entity\Project;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the mail manager context pane.
 */
#[ManagerContextPane(id: "mail")]
class ManagerContextPaneMail extends ManagerContextPaneBase implements ManagerContextPaneViewInterface {

  use PaneMessagesTrait;
  use StringTranslationTrait;

  /**
   * The current user.
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The registry of manager mails.
   */
  protected ManagerMailRegistry $registry;

  /**
   * The access service.
   */
  protected ManagerMailAccess $access;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The date formatter.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The form builder.
   */
  protected FormBuilderInterface $formBuilder;

  /**
   * The messenger.
   */
  protected MessengerInterface $messenger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults): static {
    $instance = parent::create($container, ...$defaults);
    $instance->currentUser = $container->get('current_user');
    $instance->registry = $container->get('mailer.manager_mail_registry');
    $instance->access = $container->get('mailer.manager_mail_access');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->formBuilder = $container->get('form_builder');
    $instance->messenger = $container->get('messenger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build(Project $project): array {
    return $this->buildIndex($project);
  }

  /**
   * {@inheritdoc}
   */
  public function buildView(Project $project, Request $request): array {
    $view = $request->query->get('view');
    if ($view === 'mail') {
      return $this->buildMailView($project, (string) $request->query->get('subtype', ''), $request);
    }
    return $this->buildIndex($project);
  }

  /**
   * Builds the empty pane.
   */
  protected function emptyPane(Project $project): array {
    return ['#theme' => 'context_pane', '#type' => 'mail', '#project' => $project, 'content' => []];
  }

  /**
   * Builds the mail form view; access is checked on every request.
   *
   * A valid POST sends the mail in the form's submit handler and returns the
   * index view with a confirmation; a failed send returns the form again.
   */
  protected function buildMailView(Project $project, string $subtype, Request $request): array {
    if ($subtype === '' || !$this->access->access($this->currentUser, $project, $subtype)->isAllowed()) {
      return $this->emptyPane($project);
    }
    $form_state = new FormState();
    // Without this the form API would try to produce a redirect response.
    $form_state->disableRedirect();
    $form_state->addBuildInfo('args', [$project, $subtype]);
    // A POST with a form_build_id restores the build info of the earlier GET:
    // tell the form which project and subtype this request is for.
    $request->attributes->set('manager_pane_mail', [(string) $project->id(), $subtype]);
    $form = $this->formBuilder->buildForm(ProjectMailForm::class, $form_state);
    $args = $form_state->getBuildInfo()['args'] ?? [];
    $built_for = ($args[0] ?? NULL) instanceof Project ? [(string) $args[0]->id(), $args[1] ?? NULL] : NULL;
    if ($built_for !== NULL && $built_for !== [(string) $project->id(), $subtype]) {
      return $this->emptyPane($project);
    }
    if ($form_state->get('send_denied')) {
      return $this->emptyPane($project);
    }
    if ($form_state->get('already_submitted')) {
      $this->messenger->addStatus($this->t('This form was already submitted.'));
      return $this->buildIndex($project);
    }
    if ($form_state->get('sent')) {
      $this->messenger->addStatus($this->t('The mail was sent.'));
      return $this->buildIndex($project);
    }
    if ($form_state->get('send_failed')) {
      // symfony_mailer reports the transport error (host, port) on the
      // messenger as error or warning: show the manager only our own message.
      $this->messenger->deleteByType(MessengerInterface::TYPE_ERROR);
      $this->messenger->deleteByType(MessengerInterface::TYPE_WARNING);
      $this->messenger->addError($this->t('The mail could not be sent. Please try again later.'));
    }
    $build = $this->emptyPane($project);
    $build['content']['messages'] = $this->buildPaneMessages($this->messenger);
    $build['content']['form'] = $form;
    return $build;
  }

  /**
   * Builds the index view: the mails of the state and the last sent.
   */
  protected function buildIndex(Project $project): array {
    $build = $this->emptyPane($project);
    $may_mail = $this->access->access($this->currentUser, $project)->isAllowed();
    if (!$may_mail) {
      return $build;
    }
    $build['content']['messages'] = $this->buildPaneMessages($this->messenger);
    $this->buildMails($build, $project);
    if ($last = $this->lastSent($project)) {
      $build['content']['last'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['manager-mail-pane-last']],
        '#value' => $this->t('Last manager mail sent @time ago.', [
          '@time' => $this->dateFormatter->formatTimeDiffSince($last),
        ]),
      ];
    }
    return $build;
  }

  /**
   * Adds the links of the mails that can be sent in the project state.
   */
  protected function buildMails(array &$build, Project $project): void {
    foreach ($this->registry->available($project) as $definition) {
      $build['content']['mails'][$definition->subtype] = [
        '#type' => 'link',
        '#title' => $definition->label,
        '#url' => Url::fromRoute('manager.context_pane', ['project' => $project->id()], [
          'query' => ['type' => 'mail', 'view' => 'mail', 'subtype' => $definition->subtype],
        ]),
        '#attributes' => [
          'class' => ['button', 'button--small'],
          'data-pane-view' => 'mail',
          'data-pane-params' => http_build_query(['subtype' => $definition->subtype]),
        ],
      ];
    }
    if (!isset($build['content']['mails'])) {
      $build['content']['empty'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['manager-mail-pane-empty']],
        '#value' => $this->t('No mail to send in this status.'),
      ];
    }
  }

  /**
   * Gets the time of the latest sent manager mail of the project.
   *
   * @return int|null
   *   The timestamp, or NULL if there is none or it is not viewable.
   */
  protected function lastSent(Project $project): ?int {
    $storage = $this->entityTypeManager->getStorage('correspondence');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('project', $project->id())
      ->condition('origin', 'manager')
      ->condition('result', 'sent')
      ->sort('occurred', 'DESC')
      ->range(0, 1)
      ->execute();
    if (!$ids) {
      return NULL;
    }
    $entry = $storage->load(reset($ids));
    if (!$entry instanceof ContentEntityInterface || !$entry->access('view', $this->currentUser)) {
      return NULL;
    }
    return (int) $entry->get('occurred')->value;
  }

}
