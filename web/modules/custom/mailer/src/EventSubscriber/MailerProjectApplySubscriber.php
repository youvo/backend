<?php

namespace Drupal\mailer\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\creatives\Entity\Creative;
use Drupal\mailer\Entity\TransactionalEmail;

/**
 * Mailer project apply event subscriber.
 */
class MailerProjectApplySubscriber extends MailerSubscriberBase {

  use StringTranslationTrait;

  const EMAIL_ID_CREATIVE = 'project_apply_creative';
  const EMAIL_ID_ORGANIZATION = 'project_apply_organization';

  /**
   * Sends mail during project apply event.
   */
  public function mail(Event $event): void {

    /** @var \Drupal\projects\Event\ProjectApplyEvent $event */
    $project = $event->getProject();
    $organization = $project->getOwner();
    $manager = $organization->getManager();
    $creative = $event->getApplicant();

    // Send email to organization.
    $email = $this->loadTransactionalEmail(self::EMAIL_ID_ORGANIZATION);
    if ($email instanceof TransactionalEmail && $creative instanceof Creative) {

      $replacements = [
        '%TitleProject' => $project->getTitle(),
        '%Message' => $event->getMessage(),
        '%NameCreative' => $creative->getName(),
        '%LinkCreative' => 'https://hub.youvo.org/users/' . $creative->uuid(),
        '%EmailCreative' => $creative->getEmail(),
        '%PhoneCreative' => $event->getPhoneNumber(),
        '%Manager' => $manager?->getName() ?? $this->t('Dein youvo-Team'),
      ];

      $this->sendMail(
        $event->getProject()->getOwner()->getEmail(),
        $this->handleTokensSubject($email, $replacements),
        $this->handleTokensBody($email, $replacements),
        $manager?->getEmail()
      );
    }

    // Send email to creative.
    $email = $this->loadTransactionalEmail(self::EMAIL_ID_CREATIVE);
    if ($email instanceof TransactionalEmail && $creative instanceof Creative) {

      $replacements = [
        '%NameCreative' => $creative->getName(),
        '%TitleProject' => $project->getTitle(),
        '%NameOrganization' => $organization->getName(),
        '%ContactOrganization' => $organization->getContact(),
        '%AddressOrganization' => $organization->getAddress(),
        '%EmailOrganization' => $organization->getEmail(),
        '%PhoneOrganization' => $organization->getPhoneNumber(),
        '%EmailManager' => $manager?->getEmail() ?? $this->getSiteMail(),
        '%Manager' => $manager?->getName() ?? $this->t('Dein youvo-Team'),
      ];

      $this->sendMail(
        $creative->getEmail(),
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
    return ['Drupal\projects\Event\ProjectApplyEvent' => 'mail'];
  }

}
