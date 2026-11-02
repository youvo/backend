<?php

namespace Drupal\manager\Plugin\ManagerContextPane;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\mailer\Manager\ManagerMailAccess;
use Drupal\mailer\Manager\ManagerMailRegistry;
use Drupal\manager\Form\ProjectArchiveForm;
use Drupal\manager\Form\ProjectDeclineForm;
use Drupal\projects\ProjectInterface;
use Drupal\rest\Plugin\Type\ResourcePluginManager;
use Symfony\Component\HttpFoundation\Request;

/**
 * Offers the project actions of the REST resources in a pane.
 *
 * A pane shows an action only if the user may post it, so the pane follows
 * the resources instead of repeating their rules.
 */
trait ProjectActionPaneTrait {

  /**
   * The mail editors of the actions that mail.
   */
  protected const array MAIL_FORMS = [
    'decline' => ProjectDeclineForm::class,
    'archive' => ProjectArchiveForm::class,
  ];

  /**
   * The current user.
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The REST resource plugin manager.
   */
  protected ResourcePluginManager $resourceManager;

  /**
   * The registry of manager mails.
   */
  protected ManagerMailRegistry $registry;

  /**
   * The access service of manager mails.
   */
  protected ManagerMailAccess $access;

  /**
   * The form builder.
   */
  protected FormBuilderInterface $formBuilder;

  /**
   * Whether the current user may post the action of a project resource.
   *
   * The route checks the REST permission besides the resource's access().
   */
  protected function mayPost(ProjectInterface $project, string $action): bool {
    if (!$this->currentUser->hasPermission('restful post project:' . $action)) {
      return FALSE;
    }
    $class = $this->resourceManager->getDefinition('project:' . $action)['class'];
    return $class::access($this->currentUser, $project)->isAllowed();
  }

  /**
   * Whether the current user may post the action with the mail subtype.
   *
   * The editor needs the mail preview, which the mail access guards.
   */
  protected function mayMail(ProjectInterface $project, string $action, string $subtype): bool {
    return $this->mayPost($project, $action)
      && $this->access->access($this->currentUser, $project, $subtype)->isAllowed();
  }

  /**
   * Builds the link that opens the mail editor of an action, if permitted.
   */
  protected function buildMailActionLink(ProjectInterface $project, string $action, TranslatableMarkup $title): ?array {
    if (!$this->mayMail($project, $action, $action . '_custom')) {
      return NULL;
    }
    return [
      '#type' => 'link',
      '#title' => $title,
      '#url' => Url::fromRoute('manager.context_pane', ['project' => $project->id()], [
        'query' => ['type' => $this->getPluginId(), 'view' => $action],
      ]),
      '#attributes' => [
        'class' => ['button', 'button--small', 'button--danger'],
        'data-pane-view' => $action,
      ],
    ];
  }

  /**
   * Builds the mail editor view of an action; access is checked every time.
   */
  protected function buildMailActionView(ProjectInterface $project, string $action, Request $request): array {
    // The mail subtypes are '<action>_<reason>': the access refuses unknown
    // reasons.
    $subtype = $action . '_' . $request->query->get('reason', 'custom');
    $build = ['#theme' => 'context_pane', '#type' => $action, '#project' => $project, 'content' => []];
    if (!$this->mayMail($project, $action, $subtype)) {
      return $build;
    }
    // Like the mail pane: no redirect, if a form API POST arrives anyway.
    $form_state = (new FormState())->disableRedirect()->addBuildInfo('args', [$project, $subtype]);
    $build['content']['form'] = $this->formBuilder->buildForm(static::MAIL_FORMS[$action], $form_state);
    $definitions = $action === 'archive'
      ? $this->registry->availableArchives($project)
      : $this->registry->availableDeclines($project);
    foreach ($definitions as $definition) {
      $reason = substr($definition->subtype, strlen($action) + 1);
      $is_active = $definition->subtype === $subtype;
      $build['content']['reasons'][$reason] = [
        '#type' => 'link',
        '#title' => $definition->label,
        '#url' => Url::fromRoute('manager.context_pane', ['project' => $project->id()], [
          'query' => ['type' => $this->getPluginId(), 'view' => $action, 'reason' => $reason],
        ]),
        '#attributes' => [
          'class' => $is_active ? ['manager-mail-type', 'is-active'] : ['manager-mail-type'],
          'data-pane-view' => $action,
          'data-pane-params' => http_build_query(['reason' => $reason]),
        ] + ($is_active ? ['aria-current' => 'true'] : []),
      ];
    }
    return $build;
  }

}
