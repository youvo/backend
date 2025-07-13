<?php

namespace Drupal\mailer\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\creatives\Entity\Creative;
use Drupal\mailer\Entity\TransactionalEmail;
use Drupal\projects\Event\ProjectMediateEvent;

/**
 * Mailer project mediate event subscriber.
 *
 * @todo Placeholder implementation. Needs to be reviewed in project lifecycle.
 */
class MailerProjectMediateSubscriber extends MailerSubscriberBase {

  use StringTranslationTrait;

  const EMAIL_ID_ORGANIZATION = 'project_mediate_organization';
  const EMAIL_ID_CREATIVE = 'project_mediate_creative';

  /**
   * Sends mail during project mediate event.
   */
  public function mail(Event $event): void {

    /** @var \Drupal\projects\Event\ProjectMediateEvent $event */
    $project = $event->getProject();
    $organization = $project->getOwner();
    $manager = $organization->getManager();
    $selected_creatives = $event->getCreatives();

    // Send email to organization.
    $email = $this->loadTransactionalEmail(self::EMAIL_ID_ORGANIZATION);
    if ($email instanceof TransactionalEmail) {

      $creative_names = array_map(static fn($c) => $c->getName(), $selected_creatives);

      $replacements = [
        '%Contact' => $organization->getContact(),
        '%TitleProject' => $project->getTitle(),
        '%SelectedCreatives' => implode(', ', $creative_names),
        '%Manager' => $manager?->getName() ?? $this->t('Dein youvo-Team'),
      ];

      $this->sendMail(
        $organization->getEmail(),
        $this->handleTokensSubject($email, $replacements),
        $this->handleTokensBody($email, $replacements),
        $manager?->getEmail()
      );
    }

    // Send email to each selected creative.
    $email = $this->loadTransactionalEmail(self::EMAIL_ID_CREATIVE);
    if ($email instanceof TransactionalEmail) {

      foreach ($selected_creatives as $creative) {
        if (!$creative instanceof Creative) {
          continue;
        }

        $replacements = [
          '%NameCreative' => $creative->getName(),
          '%TitleProject' => $project->getTitle(),
          '%NameOrganization' => $organization->getName(),
          '%ContactOrganization' => $organization->getContact(),
          '%AddressOrganization' => $organization->getAddress(),
          '%EmailOrganization' => $organization->getEmail(),
          '%PhoneOrganization' => $organization->getPhoneNumber(),
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
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectMediateEvent::class => 'mail'];
  }

}
