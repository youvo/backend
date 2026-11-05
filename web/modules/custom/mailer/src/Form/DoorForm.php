<?php

namespace Drupal\mailer\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\correspondence\CorrespondenceInterface;
use Drupal\creatives\Entity\Creative;
use Drupal\mailer\Door\DoorLinks;
use Drupal\mailer\Door\DoorTarget;
use Drupal\projects\Event\ProjectFollowEvent;
use Drupal\projects\ProjectInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The page behind a link in a mail.
 *
 * Mail security scanners open links, so opening the door does nothing. Only
 * pressing the button may log the recipient in and logs the followed link,
 * like the one-time login page of core.
 */
class DoorForm extends FormBase {

  /**
   * The key/value collection of the followed doors.
   */
  const string FOLLOW_COLLECTION = 'mailer.door_follow';

  /**
   * Constructs a DoorForm object.
   */
  public function __construct(
    protected DoorLinks $doorLinks,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityRepositoryInterface $entityRepository,
    protected AccountProxyInterface $currentUser,
    protected KeyValueExpirableFactoryInterface $keyValue,
    protected EventDispatcherInterface $eventDispatcher,
    protected TimeInterface $time,
  ) {}

  /**
   * Creates the form with its services.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(DoorLinks::class),
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $container->get('current_user'),
      $container->get('keyvalue.expirable'),
      $container->get('event_dispatcher'),
      $container->get('datetime.time'),
    );
  }

  /**
   * Gets the form ID.
   */
  public function getFormId(): string {
    return 'mailer_door_form';
  }

  /**
   * Builds the door page from the route parameters.
   *
   * Runs again on submit, so the checks use the state at the time of the
   * press.
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    string $uid = '',
    string $correspondence = '',
    string $target = '',
    string $timestamp = '',
    string $hash = '',
    string $subject = '',
  ): array {

    // An unknown door or a door with an invalid signature reveals nothing.
    $recipient = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$recipient instanceof UserInterface || $recipient->isAnonymous() || $recipient->isBlocked()) {
      throw new AccessDeniedHttpException();
    }
    $door_target = DoorTarget::tryFrom($target);
    if ($door_target === NULL) {
      throw new AccessDeniedHttpException();
    }
    $entry = $this->entityRepository->loadEntityByUuid('correspondence', $correspondence);
    if (!$entry instanceof CorrespondenceInterface || $entry->bundle() !== 'mail') {
      throw new AccessDeniedHttpException();
    }
    $project = $entry->getParentEntity();
    if (!$project instanceof ProjectInterface) {
      throw new AccessDeniedHttpException();
    }
    $timestamp = (int) $timestamp;
    if (!hash_equals($this->doorLinks->hash($recipient, $correspondence, $door_target, $subject, $timestamp), $hash)) {
      throw new AccessDeniedHttpException();
    }

    // The signature holds, so a failing check is the age of the door.
    $expired = !$this->doorLinks->isValid($recipient, $correspondence, $door_target, $subject, $timestamp, $hash);

    // The owner of the project, or a creative of a project for feedback.
    $belongs = (int) $project->getOwnerId() === (int) $recipient->id()
      || ($door_target === DoorTarget::Feedback
        && $recipient instanceof Creative
        && array_key_exists((int) $recipient->id(), $project->getParticipants('Creative')));

    $signed_in = $this->currentUser->isAuthenticated();
    $other_signed_in = $signed_in && (int) $this->currentUser->id() !== (int) $recipient->id();

    $login = !$expired
      && $belongs
      && !$signed_in
      && $door_target->logsIn()
      && $this->doorLinks->allowsLogin($recipient, $timestamp);

    $follow = !$expired
      && $belongs
      && !$recipient->hasPermission('access site')
      && !$other_signed_in;

    $form_state->set('door', [
      'recipient' => $recipient,
      'project' => $project,
      'entry' => $entry,
      'target' => $door_target,
      'subject' => $subject,
      'login' => $login,
      'follow' => $follow,
    ]);

    $note = '';
    if ($login) {
      $note = $this->t('Ihr werdet dabei angemeldet.');
    }
    elseif ($other_signed_in) {
      $note = $this->t('Ihr seid als %name angemeldet. Der Link wurde für ein anderes Konto verschickt.', [
        '%name' => $this->currentUser->getDisplayName(),
      ]);
    }

    $form['#theme'] = 'mail_door';
    $form['#heading'] = $door_target->heading();
    $form['#context'] = $project->getTitle();
    $form['#note'] = $note;
    $form['#cache']['max-age'] = 0;

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Weiter zu youvo'),
      ],
    ];

    return $form;
  }

  /**
   * Logs the recipient in, logs the follow and leaves for the target.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    $door = $form_state->get('door');
    /** @var \Drupal\user\UserInterface $recipient */
    $recipient = $door['recipient'];
    /** @var \Drupal\projects\ProjectInterface $project */
    $project = $door['project'];
    /** @var \Drupal\correspondence\CorrespondenceInterface $entry */
    $entry = $door['entry'];
    /** @var \Drupal\mailer\Door\DoorTarget $target */
    $target = $door['target'];

    // The key is set only once, also when the button is pressed twice at once.
    // It is needed only as long as the door is valid.
    $key = $entry->uuid() . ':' . $target->value;
    if ($door['follow'] && $this->keyValue->get(static::FOLLOW_COLLECTION)->setWithExpireIfNotExists($key, $this->time->getRequestTime(), DoorLinks::LIFETIME)) {
      $this->eventDispatcher->dispatch(new ProjectFollowEvent($project, $recipient, $entry->getSubtype(), $entry->uuid(), $target->value));
    }

    if ($door['login'] && $this->isSameSiteRequest()) {
      user_login_finalize($recipient);
      $form_state->setResponse(new TrustedRedirectResponse($this->doorLinks->loginUrl()));
      return;
    }

    $form_state->setResponse(new TrustedRedirectResponse($this->doorLinks->targetUrl($target, $project, $recipient, $door['subject'])));
  }

  /**
   * Checks that the button was pressed on this site.
   *
   * Anonymous forms carry no form token, so a foreign page could post a door
   * and log its visitor in to the recipient account (login CSRF). Browsers
   * without these headers are allowed.
   */
  protected function isSameSiteRequest(): bool {

    $request = $this->getRequest();

    $fetch_site = $request->headers->get('Sec-Fetch-Site');
    if ($fetch_site !== NULL && $fetch_site !== 'same-origin' && $fetch_site !== 'none') {
      return FALSE;
    }

    $origin = $request->headers->get('Origin');
    if ($origin !== NULL && $origin !== $request->getSchemeAndHttpHost()) {
      return FALSE;
    }

    return TRUE;
  }

}
