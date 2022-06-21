<?php

namespace Drupal\youvo\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Controller for youvo_work landing pages.
 */
class DashboardController extends ControllerBase {

  /**
   * Simple Dashboard.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function dashboard() {

    $page = [];

    // Get current username and append to page variable.
    /** @var \Drupal\creatives\Entity\Creative $current_user */
    $current_user = $this->entityTypeManager()
      ->getStorage('user')
      ->load($this->currentUser()->id());
    $page['name'] = $current_user->get('field_name')->value;

    // Content moderation.
    if (array_intersect(['editor', 'administrator'], $this->currentUser()->getRoles())) {
      $page['moderation'] = TRUE;
      if ($this->moduleHandler()->moduleExists('lectures')) {
        $page['academy_administration'] = [
          '#type' => 'link',
          '#title' => $this->t('Academy Administration'),
          '#url' => Url::fromRoute('entity.lecture.collection'),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ];
      }
    }

    // Monitoring.
    if (array_intersect(['observer', 'administrator'], $this->currentUser()->getRoles())) {
      $page['monitoring'] = TRUE;
      if ($this->moduleHandler()->moduleExists('academy_log')) {
        $page['academy_participants'] = [
          '#type' => 'link',
          '#title' => $this->t('Academy Participants'),
          '#url' => Url::fromRoute('academy_log.overview'),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ];
      }
      if ($this->moduleHandler()->moduleExists('logbook')) {
        $page['logbook'] = [
          '#type' => 'link',
          '#title' => $this->t('Logbook'),
          '#url' => Url::fromRoute('entity.log.collection'),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ];
      }
      if ($this->moduleHandler()->moduleExists('stats')) {
        $page['stats'] = [
          '#type' => 'link',
          '#title' => $this->t('Stats'),
          '#url' => Url::fromRoute('stats.overview'),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ];
      }
    }

    // Configuration.
    if (array_intersect(['configurator', 'administrator'], $this->currentUser()->getRoles())) {
      $page['configuration'] = TRUE;
      if ($this->moduleHandler()->moduleExists('mailer') &&
        $this->currentUser()->hasPermission('edit transactional emails')) {
        $page['transactional_emails'] = [
          '#type' => 'link',
          '#title' => $this->t('Transactional Emails'),
          '#url' => Url::fromRoute('entity.transactional_email.collection'),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ];
      }
      if ($this->moduleHandler()->moduleExists('logbook') &&
        $this->currentUser()->hasPermission('edit log pattern')) {
        $page['log_patterns'] = [
          '#type' => 'link',
          '#title' => $this->t('Log Patterns'),
          '#url' => Url::fromRoute('entity.log_pattern.collection'),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ];
      }
      if ($this->currentUser()->hasPermission('edit terms in skills')) {
        $page['skills'] = [
          '#type' => 'link',
          '#title' => $this->t('Skills'),
          '#url' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => 'skills']),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ];
      }
    }

    return [
      '#theme' => 'dashboard',
      '#page' => $page,
    ];
  }

}
