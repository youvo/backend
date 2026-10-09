<?php

namespace Drupal\mailer\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\creatives\Entity\Creative;
use Drupal\mailer\Entity\TransactionalEmail;
use Drupal\projects\Event\ProjectCompleteEvent;

/**
 * Mailer project complete event subscriber.
 *
 * @todo Placeholder implementation. Needs to be reviewed in project lifecycle.
 */
class MailerProjectCompleteSubscriber extends MailerSubscriberBase {

  use StringTranslationTrait;

  const EMAIL_ID_ORGANIZATION = 'project_complete_organization';
  const EMAIL_ID_CREATIVE = 'project_complete_creative';

  /**
   * Sends mail during project complete event.
   */
  public function mail(Event $event): void {

    /** @var \Drupal\projects\Event\ProjectCompleteEvent $event */
    $project = $event->getProject();
    $organization = $project->getOwner();
    $manager = $organization->getManager();
    /** @var \Drupal\creatives\Entity\Creative[] $participants */
    $participants = $project->getParticipants('Creative');

    // Send email to organization.
    $email = $this->loadTransactionalEmail(self::EMAIL_ID_ORGANIZATION);
    if ($email instanceof TransactionalEmail) {

      $participant_names = array_map(static fn ($p) => $p->getName(), $participants);

      $replacements = [
        '%Contact' => $organization->getContact(),
        '%TitleProject' => $project->getTitle(),
        '%Participants' => implode(', ', $participant_names),
        '%FeedbackLink' => 'https://youvo.org/feedback/organization/' . $project->uuid(),
        '%Manager' => $manager?->getName() ?? $this->t('Dein youvo-Team'),
      ];

      $this->sendMail(
        $organization->getEmail(),
        $this->handleTokensSubject($email, $replacements),
        $this->handleTokensBody($email, $replacements),
        $manager?->getEmail()
      );
    }

    // Send email to each participant.
    $email = $this->loadTransactionalEmail(self::EMAIL_ID_CREATIVE);
    if ($email instanceof TransactionalEmail) {

      foreach ($participants as $participant) {
        if (!$participant instanceof Creative) {
          continue;
        }

        $replacements = [
          '%NameCreative' => $participant->getName(),
          '%TitleProject' => $project->getTitle(),
          '%NameOrganization' => $organization->getName(),
          '%FeedbackLink' => 'https://youvo.org/feedback/creative/' . $project->uuid(),
          '%ResultsLink' => 'https://youvo.org/results/' . $project->uuid(),
          '%Manager' => $manager?->getName() ?? $this->t('Dein youvo-Team'),
        ];

        $this->sendMail(
          $participant->getEmail(),
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
    return [ProjectCompleteEvent::class => 'mail'];
  }

}
