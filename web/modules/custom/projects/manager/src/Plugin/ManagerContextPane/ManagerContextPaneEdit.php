<?php

namespace Drupal\manager\Plugin\ManagerContextPane;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\manager\Attribute\ManagerContextPane;
use Drupal\projects\Entity\Project;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a project edit manager context pane.
 *
 * Lists the project actions besides the lifecycle transitions; Archive opens
 * the mail editor (view 'archive').
 */
#[ManagerContextPane(id: "edit")]
class ManagerContextPaneEdit extends ManagerContextPaneBase implements ManagerContextPaneActionInterface, ManagerContextPaneViewInterface {

  use ProjectActionPaneTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults): static {
    $instance = parent::create($container, ...$defaults);
    $instance->currentUser = $container->get('current_user');
    $instance->resourceManager = $container->get('plugin.manager.rest');
    $instance->registry = $container->get('mailer.manager_mail_registry');
    $instance->access = $container->get('mailer.manager_mail_access');
    $instance->formBuilder = $container->get('form_builder');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function hasActions(Project $project): bool {
    return $this->permittedActions($project) !== []
      || $this->mayMail($project, 'archive', 'archive_custom');
  }

  /**
   * {@inheritdoc}
   */
  public function build(Project $project): array {

    $build = [
      '#theme' => 'context_pane',
      '#type' => 'edit',
      '#project' => $project,
      'content' => [],
    ];

    foreach ($this->permittedActions($project) as $action) {
      $build['content']['actions'][$action] = [
        'button' => [
          '#type' => 'button',
          '#value' => $this->label($project, $action),
          '#attributes' => [
            'class' => ['button', 'button--small', 'js-action-btn'],
            'data-action' => $action,
          ],
        ],
        'description' => $this->description($project, $action),
      ];
    }

    if ($archive = $this->buildMailActionLink($project, 'archive', $this->label($project, 'archive'))) {
      $build['content']['actions']['archive'] = [
        'button' => $archive,
        'description' => $this->description($project, 'archive'),
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildView(Project $project, Request $request): array {
    if ($request->query->get('view') !== 'archive') {
      return $this->build($project);
    }
    return $this->buildMailActionView($project, 'archive', $request);
  }

  /**
   * Gets the actions without mail editor that the current user may post.
   *
   * @return string[]
   *   The actions, as in project:<action>.
   */
  protected function permittedActions(Project $project): array {
    $actions = [
      $project->isPromoted() ? 'demote' : 'promote',
      $project->isHidden() ? 'show' : 'hide',
      'notify',
      'reset',
    ];
    return array_values(array_filter($actions, fn(string $action): bool => $this->mayPost($project, $action)));
  }

  /**
   * Gets the button label of an action.
   */
  protected function label(Project $project, string $action): TranslatableMarkup {
    return match ($action) {
      'promote' => $this->t('Promote'),
      'demote' => $this->t('Demote'),
      'hide' => $this->t('Hide'),
      'show' => $this->t('Show'),
      'notify' => $project->getOwner()->hasRoleProspect() ? $this->t('Invite') : $this->t('Notify'),
      'reset' => $this->t('Reset'),
      'archive' => $this->t('Archive'),
      default => throw new \InvalidArgumentException('Unknown action ' . $action . '.'),
    };
  }

  /**
   * Builds the description of an action: what happens when it is used.
   */
  protected function description(Project $project, string $action): array {
    $items = match ($action) {
      'promote' => [
        $this->t('The project is promoted to the front page.'),
        $this->t('Nobody receives an email.'),
      ],
      'demote' => [
        $this->t('The project is no longer promoted to the front page.'),
        $this->t('Nobody receives an email.'),
      ],
      'hide' => [
        $this->t('The project is no longer listed and not promoted.'),
        $this->t('Signed-in users can still open it by link.'),
        $this->t('Nobody receives an email.'),
      ],
      'show' => [
        $this->t('The project is listed again.'),
        $this->t('Nobody receives an email.'),
      ],
      'notify' => $project->getOwner()->hasRoleProspect()
        ? [$this->t('The organization receives an invitation to sign up by email.')]
        : [$this->t('The organization receives an email about the draft.')],
      'reset' => [
        $this->t('The project becomes a draft again.'),
        $this->t('The earlier logs are archived.'),
        $this->t('Nobody receives an email.'),
      ],
      'archive' => [
        $this->t('The project goes offline; only administrators can unarchive it.'),
        $this->t('The organization receives the edited email.'),
      ],
      default => throw new \InvalidArgumentException('Unknown action ' . $action . '.'),
    };
    return ['#theme' => 'item_list', '#items' => $items];
  }

}
