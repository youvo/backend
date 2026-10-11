<?php

namespace Drupal\manager\Plugin\ManagerContextPane;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\mailer\Manager\ManagerMailAccess;
use Drupal\mailer\Manager\ManagerMailRegistry;
use Drupal\manager\Attribute\ManagerContextPane;
use Drupal\projects\Entity\Project;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the mail manager context pane.
 */
#[ManagerContextPane(id: "mail")]
class ManagerContextPaneMail extends ManagerContextPaneBase {

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults): static {
    $instance = parent::create($container, ...$defaults);
    $instance->currentUser = $container->get('current_user');
    $instance->registry = $container->get('mailer.manager_mail_registry');
    $instance->access = $container->get('mailer.manager_mail_access');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->dateFormatter = $container->get('date.formatter');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build(Project $project): array {
    $build = ['#theme' => 'context_pane', '#type' => 'mail', '#project' => $project, 'content' => []];
    $may_mail = $this->access->access($this->currentUser, $project)->isAllowed();
    $may_note = $this->access->noteAccess($this->currentUser, $project)->isAllowed();
    if (!$may_mail && !$may_note) {
      return $build;
    }
    if ($may_mail) {
      $this->buildMails($build, $project);
    }
    if ($may_note) {
      $build['content']['notes'] = [
        '#type' => 'link',
        '#title' => $this->t('Notiz hinzufügen'),
        '#url' => Url::fromRoute('manager.note_add', ['project' => $project->id()]),
        '#attributes' => [
          'class' => ['use-ajax', 'button', 'button--small'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode([
            'width' => 760,
            'title' => (string) $this->t('Notiz hinzufügen'),
          ]),
        ],
      ];
    }
    if ($may_mail && $last = $this->lastSent($project)) {
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
        '#url' => Url::fromRoute('manager.mail_form', [
          'project' => $project->id(),
          'subtype' => $definition->subtype,
        ]),
        '#attributes' => [
          'class' => ['use-ajax', 'button', 'button--small'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode([
            'width' => 760,
            'title' => (string) $definition->label,
          ]),
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
