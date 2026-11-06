<?php

namespace Drupal\creatives\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\creatives\Entity\Creative;
use Drupal\password_policy\PasswordPolicyValidatorInterface;
use Drupal\user\OneTimeAuthentication;
use Drupal\user\UserInterface;
use Drupal\user_types\PasswordChecklist;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Form controller for the creative register forms.
 *
 * Creatives followed the link in the email, now they can enter a new
 * password.
 *
 * @internal
 */
final class CreativeRegisterForm extends FormBase {

  /**
   * Constructs a CreativeRegisterForm object.
   */
  public function __construct(
    protected AccountProxyInterface $account,
    protected EventDispatcherInterface $eventDispatcher,
    protected FloodInterface $flood,
    protected ModuleHandlerInterface $moduleHandler,
    protected SessionInterface $session,
    protected TimeInterface $time,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected OneTimeAuthentication $oneTimeAuthentication,
    protected PasswordPolicyValidatorInterface $passwordPolicyValidator,
    protected PasswordChecklist $passwordChecklist,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('current_user'),
      $container->get('event_dispatcher'),
      $container->get('flood'),
      $container->get('module_handler'),
      $container->get('session'),
      $container->get('datetime.time'),
      $container->get('entity_type.manager'),
      $container->get(OneTimeAuthentication::class),
      $container->get('password_policy.validator'),
      $container->get(PasswordChecklist::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'creative_register_password_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    ?AccountInterface $user = NULL,
  ): RedirectResponse|array {

    // Verify that the creative is active.
    if (
      !$user instanceof Creative ||
      !$user->isActive()
    ) {
      throw new AccessDeniedHttpException();
    }

    // Get timestamp and hash from form_state on submit.
    $input = $form_state->getUserInput();
    $timestamp = $input['timestamp'] ?? NULL;
    $hash = $input['hash'] ?? NULL;

    // Get timestamp and hash from session on initial load.
    if (empty($timestamp) || empty($hash)) {
      $timestamp = $this->session->get('creative_register_timeout');
      $hash = $this->session->get('creative_register_hash');
      $this->session->remove('creative_register_timeout');
      $this->session->remove('creative_register_hash');
    }

    if (empty($timestamp) || empty($hash)) {
      throw new AccessDeniedHttpException();
    }

    // Compare current time with timeout in one week.
    $current = $this->time->getCurrentTime();
    // @todo Add configuration.
    $timeout = 2592000;

    // Redirect to front with message if timed out.
    if ($current - $timestamp > $timeout &&
      $user->getLastAccessedTime() > 0) {
      // @todo Adjust message.
      $this->messenger()
        ->addError($this->t('Der Link zur Registrierung ist abgelaufen. Schreib uns bitte an hello@youvo.org.'));
      return $this->redirect('<front>');
    }

    $form['timestamp'] = [
      '#type' => 'hidden',
      '#value' => $timestamp,
    ];

    $form['hash'] = [
      '#type' => 'hidden',
      '#value' => $hash,
    ];

    $form['user'] = [
      '#type' => 'value',
      '#value' => $user,
    ];

    // The texts follow the mail door's welcome of new organizations.
    $form['#title'] = $this->t('Willkommen bei youvo');

    $form['note'] = [
      '#prefix' => '<p class="public-page-note">',
      '#markup' => $this->t('Leg ein Passwort für dein Konto fest. Du wirst dabei angemeldet.'),
      '#suffix' => '</p>',
    ];

    $form['password'] = [
      '#type' => 'password_confirm',
      '#size' => 25,
      '#after_build' => [[PasswordChecklist::class, 'germanPasswordTitles']],
    ];

    $form['password_rules'] = $this->passwordChecklist->build('creative');

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Passwort speichern und weiter'),
      ],
    ];

    $form['#theme'] = 'creative_register_password_form';

    return $form;
  }

  /**
   * Checks the new password against the password policies.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $password = (string) $form_state->getValue('password');
    $creative = $form_state->getValue('user');
    if ($password === '' || !$creative instanceof UserInterface) {
      return;
    }
    $report = $this->passwordPolicyValidator->validatePassword($password, $creative);
    if ($report->isInvalid()) {
      $form_state->setErrorByName('password', $report->getErrors());
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state): RedirectResponse {

    $timestamp = $form_state->getValue('timestamp');
    $hash = $form_state->getValue('hash');
    $password = $form_state->getValue('password');
    /** @var \Drupal\creatives\Entity\Creative $creative */
    $creative = $form_state->getValue('user');

    if (
      !empty($password) &&
      $creative->isAuthenticated() &&
      $timestamp <= $this->time->getCurrentTime() &&
      hash_equals($hash, $this->oneTimeAuthentication->generateHmac($creative, $timestamp))
    ) {
      $creative->setPassword($password);
      $creative->save();
      $this->loginUser($creative);
      $this->flood->clear('user.password_request_user', $creative->id());

      // Resolve redirect.
      // @todo Clean up after development.
      $path = $this->config('oauth_grant.settings')
        ->get('auth_success_redirect');
      if ($this->config('oauth_grant.settings')->get('local')) {
        $redirect_url = Url::fromUri('http://localhost:3000' . $path)->toString();
      }
      else {
        $app_url = rtrim((string) $this->config('youvo.settings')->get('app_url'), '/');
        $redirect_url = Url::fromUri($app_url . $path)->toString();
      }
      $response = new TrustedRedirectResponse($redirect_url);
      $form_state->setResponse($response);
      return $response;
    }

    $this->messenger()->addError($this->t('Der Link wurde schon benutzt oder ist nicht mehr gültig. Schreib uns bitte an hello@youvo.org.'));
    return $this->redirect('user.pass');
  }

  /**
   * Programmatically login a user.
   */
  protected function loginUser(UserInterface $account): void {
    $this->account->setAccount($account);
    $this->getLogger('user')->notice('Session opened for %name.',
      ['%name' => $account->getAccountName()]);
    $account->setLastLoginTime($this->time->getRequestTime());
    /** @var \Drupal\user\UserStorageInterface $user_storage */
    $user_storage = $this->entityTypeManager->getStorage('user');
    $user_storage->updateLastLoginTimestamp($account);
    $this->session->migrate();
    $this->session->set('uid', $account->id());
    $this->session->set('check_logged_in', TRUE);

    // Call all login hooks for newly logged-in user.
    $this->moduleHandler->invokeAll('user_login', [$account]);
  }

}
