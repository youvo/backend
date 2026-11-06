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
use Drupal\Core\Utility\Error;
use Drupal\correspondence\CorrespondenceInterface;
use Drupal\mailer\Door\DoorLinks;
use Drupal\mailer\Door\DoorTarget;
use Drupal\password_policy\PasswordPolicyValidatorInterface;
use Drupal\projects\Event\ProjectFollowEvent;
use Drupal\projects\ProjectInterface;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The page behind a link in a mail.
 *
 * Mail security scanners open links, so opening the door does nothing. Only
 * pressing the button may log the recipient in and logs the followed link,
 * like the one-time login page of core.
 *
 * A prospect sets its password on a draft door. Pressing the button promotes
 * it to an organization and logs it in.
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
    protected LoggerInterface $logger,
    protected PasswordPolicyValidatorInterface $passwordPolicyValidator,
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
      $container->get('logger.channel.mailer'),
      $container->get('password_policy.validator'),
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
    if (!$recipient instanceof UserInterface) {
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

    // The signature holds, so the verdict decides from the age of the door.
    $verdict = $this->doorLinks->verdict($recipient, $project, $door_target, $timestamp, $this->currentUser->isAuthenticated() ? $this->currentUser : NULL);
    if ($verdict->rejected !== NULL) {
      throw new AccessDeniedHttpException();
    }

    $form_state->set('door', [
      'recipient' => $recipient,
      'project' => $project,
      'entry' => $entry,
      'target' => $door_target,
      'subject' => $subject,
      'login' => $verdict->login,
      'password' => $verdict->password,
      'spent' => $verdict->spent,
      'follow' => $verdict->follow,
    ]);

    $heading = $door_target->heading();
    if ($verdict->password) {
      $heading = $this->t('Willkommen bei youvo');
    }
    elseif ($verdict->spent && !$verdict->otherSignedIn) {
      $heading = $this->t('Der Link ist abgelaufen');
    }

    $note = '';
    if ($verdict->login) {
      $note = $this->t('Ihr werdet dabei angemeldet.');
    }
    elseif ($verdict->password) {
      $note = $this->t('Legt ein Passwort für euer Konto fest. Ihr werdet dabei angemeldet.');
    }
    elseif ($verdict->otherSignedIn) {
      $note = $this->t('Ihr seid als %name angemeldet. Der Link wurde für ein anderes Konto verschickt.', [
        '%name' => $this->currentUser->getDisplayName(),
      ]);
    }
    elseif ($verdict->spent) {
      $note = $this->t('Meldet euch bei youvo an. Für einen neuen Link schreibt eurem Ansprechpartner bei youvo.');
    }

    $form['#theme'] = 'mail_door';
    $form['#heading'] = $heading;
    $form['#context'] = $project->getTitle();
    $form['#note'] = $note;
    $form['#cache']['max-age'] = 0;

    if ($verdict->password) {
      $form['password'] = [
        '#type' => 'password_confirm',
        '#required' => TRUE,
        '#size' => 25,
        '#after_build' => [[static::class, 'germanPasswordTitles']],
      ];

      // The rules as a checklist. Without JavaScript it is a plain list; the
      // script ticks the rules off while typing and holds the button back.
      $rules = $this->passwordRules();
      $form['password_rules'] = [
        '#theme' => 'item_list',
        '#items' => array_map(static fn (array $rule) => [
          '#markup' => $rule['label'],
          '#wrapper_attributes' => ['data-rule' => $rule['id']],
        ], $rules),
        '#attributes' => ['class' => ['mail-door-rules'], 'id' => 'mail-door-rules'],
        '#attached' => [
          'library' => ['mailer/door_password'],
          'drupalSettings' => ['mailerDoorPassword' => ['rules' => $rules]],
        ],
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $verdict->password ? $this->t('Passwort speichern und weiter') : $this->t('Weiter zu youvo'),
      ],
    ];

    return $form;
  }

  /**
   * Rejects a password form posted from another site.
   *
   * Otherwise a foreign page could set the password of its own prospect door
   * and log its visitor in to that account (login CSRF). The error keeps the
   * account, the login and the follow log unchanged.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $door = $form_state->get('door');
    if (!$door['password']) {
      return;
    }
    if (!$this->isSameSiteRequest()) {
      $form_state->setErrorByName('', $this->t('Das Passwort konnte nicht gespeichert werden. Öffnet den Link aus der Mail bitte erneut.'));
      return;
    }

    // The prospect becomes an organization with this password.
    $report = $this->passwordPolicyValidator->validatePassword((string) $form_state->getValue('password'), $door['recipient'], ['organization' => 'organization']);
    if ($report->isInvalid()) {
      $form_state->setErrorByName('password', $report->getErrors());
    }
  }

  /**
   * Logs the recipient in, logs the follow and leaves for the target.
   *
   * A prospect is promoted and gets its password first.
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

    // The new password also spends the door, whose signature includes it.
    if ($door['password']) {
      /** @var \Drupal\organizations\Entity\Organization $recipient */
      $recipient->promoteProspect();
      $recipient->setPassword($form_state->getValue('password'));
      $recipient->save();
    }

    // The key is set only once, also when the button is pressed twice at once.
    // It is needed only as long as the door is valid.
    $key = $entry->uuid() . ':' . $target->value;
    if ($door['follow'] && $this->keyValue->get(static::FOLLOW_COLLECTION)->setWithExpireIfNotExists($key, $this->time->getRequestTime(), DoorLinks::LIFETIME)) {
      // A failing follow log must not cost the recipient the login.
      try {
        $this->eventDispatcher->dispatch(new ProjectFollowEvent($project, $recipient, $entry->getSubtype(), $entry->uuid(), $target->value));
      }
      catch (\Throwable $e) {
        $variables = Error::decodeException($e);
        $this->logger->error('Logging the mail door follow failed unexpectedly. %type: @message in %function (line %line of %file).', $variables);
      }
    }

    // The password form was checked to be posted from this site.
    if ($door['password'] || ($door['login'] && $this->isSameSiteRequest())) {
      user_login_finalize($recipient);
      $form_state->setResponse(new TrustedRedirectResponse($this->doorLinks->loginUrl()));
      return;
    }

    if ($door['spent']) {
      $form_state->setResponse(new TrustedRedirectResponse($this->doorLinks->loginUrl()));
      return;
    }

    $form_state->setResponse(new TrustedRedirectResponse($this->doorLinks->targetUrl($target, $project, $recipient, $door['subject'])));
  }

  /**
   * Names the two password fields in German, as the rest of the door.
   *
   * The site has no translations, so core's titles would stay English.
   */
  public static function germanPasswordTitles(array $element): array {
    $element['pass1']['#title'] = 'Passwort';
    $element['pass2']['#title'] = 'Passwort wiederholen';
    return $element;
  }

  /**
   * Lists the password rules of organizations for the door's checklist.
   *
   * The prospect becomes an organization with this password, see
   * validateForm(). The server still checks every rule; rules without a
   * German text here are only left out of the list.
   *
   * @return array<int, array<string, int|string>>
   *   The rules, each with an id and a label, and the values the script
   *   checks.
   */
  protected function passwordRules(): array {

    $rules = [];
    $policies = $this->entityTypeManager->getStorage('password_policy')->loadMultiple();
    foreach ($policies as $policy) {
      /** @var \Drupal\password_policy\PasswordPolicyInterface $policy */
      if (!in_array('organization', $policy->getRoles(), TRUE)) {
        continue;
      }
      foreach ($policy->getConstraints() as $constraint) {
        $length = (int) ($constraint['character_length'] ?? 0);
        if ($constraint['id'] === 'password_length' && $constraint['character_operation'] === 'minimum') {
          $min = max($length, $rules['length_min']['min'] ?? 0);
          $rules['length_min'] = [
            'id' => 'length_min',
            'label' => sprintf('Mindestens %d Zeichen', $min),
            'min' => $min,
          ];
        }
        elseif ($constraint['id'] === 'password_length' && $constraint['character_operation'] === 'maximum') {
          $max = min($length, $rules['length_max']['max'] ?? PHP_INT_MAX);
          $rules['length_max'] = [
            'id' => 'length_max',
            'label' => sprintf('Höchstens %d Zeichen', $max),
            'max' => $max,
          ];
        }
      }
    }
    $rules['match'] = ['id' => 'match', 'label' => 'Beide Passwörter stimmen überein'];

    return array_values($rules);
  }

  /**
   * Checks that the button was pressed on this site.
   *
   * Anonymous forms carry no form token, so a foreign page could post a door
   * and log its visitor in to the recipient account (login CSRF). Browsers
   * without these headers are allowed.
   *
   * The origin is compared by host and port, not by scheme: TLS ends at a
   * proxy, so PHP may see http while the browser sends https.
   */
  protected function isSameSiteRequest(): bool {

    $request = $this->getRequest();

    $fetch_site = $request->headers->get('Sec-Fetch-Site');
    if ($fetch_site !== NULL && $fetch_site !== 'same-origin' && $fetch_site !== 'none') {
      return FALSE;
    }

    $origin = $request->headers->get('Origin');
    if ($origin !== NULL) {
      $host = parse_url($origin, PHP_URL_HOST);
      if (!is_string($host)) {
        return FALSE;
      }
      if (strcasecmp($host, $request->getHost()) !== 0) {
        return FALSE;
      }
      $port = parse_url($origin, PHP_URL_PORT);
      if (is_int($port) && strcasecmp($host . ':' . $port, $request->getHttpHost()) !== 0) {
        return FALSE;
      }
    }

    return TRUE;
  }

}
