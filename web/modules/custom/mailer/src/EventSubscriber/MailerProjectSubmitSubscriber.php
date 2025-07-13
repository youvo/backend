<?php

namespace Drupal\mailer\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\creatives\Entity\Creative;
use Drupal\mailer\Entity\TransactionalEmail;
use Drupal\projects\Event\ProjectSubmitEvent;

/**
 * Mailer project submit event subscriber.
 *
 * @todo Placeholder implementation. Needs to be reviewed in project lifecycle.
 */
class MailerProjectSubmitSubscriber extends MailerSubscriberBase {

  use StringTranslationTrait;

  const EMAIL_ID_MANAGER = 'project_submit_manager';
  const EMAIL_ID_ORGANIZATION = 'project_submit_organization';

  /**
   * Sends mail during project submit event.
   */
  public function mail(Event $event): void {

    /** @var \Drupal\projects\Event\ProjectSubmitEvent $event */
    $project = $event->getProject();
    $organization = $project->getOwner();
    $manager = $organization->getManager();

    // Send email to manager.
    $email = $this->loadTransactionalEmail(self::EMAIL_ID_MANAGER);
    if ($email instanceof TransactionalEmail && $manager instanceof Creative) {

      $replacements = [
        '%Manager' => $manager->getName(),
        '%TitleProject' => $project->getTitle(),
        '%NameOrganization' => $organization->getName(),
        '%ContactOrganization' => $organization->getContact(),
        '%EmailOrganization' => $organization->getEmail(),
      ];

      $this->sendMail(
        $manager->getEmail(),
        $this->handleTokensSubject($email, $replacements),
        $this->handleTokensBody($email, $replacements)
      );
    }

    // Send email to organization.
    $email = $this->loadTransactionalEmail(self::EMAIL_ID_ORGANIZATION);
    if ($email instanceof TransactionalEmail) {

      $replacements = [
        '%Contact' => $organization->getContact(),
        '%TitleProject' => $project->getTitle(),
        '%Manager' => $manager?->getName() ?? $this->t('Dein youvo-Team'),
      ];

      $this->sendMail(
        $organization->getEmail(),
        $this->handleTokensSubject($email, $replacements),
        $this->handleTokensBody($email, $replacements),
        $manager?->getEmail()
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectSubmitEvent::class => 'mail'];
  }

}
