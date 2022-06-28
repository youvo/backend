<?php

namespace Drupal\creatives\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\creatives\Entity\Creative;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
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
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current user.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   The flood service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   The session.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\user\UserStorageInterface $userStorage
   *   The user storage.
   */
  public function __construct(
    protected AccountProxyInterface $account,
    protected EventDispatcherInterface $eventDispatcher,
    protected FloodInterface $flood,
    protected ModuleHandlerInterface $moduleHandler,
    protected SessionInterface $session,
    protected TimeInterface $time,
    protected UserStorageInterface $userStorage
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('current_user'),
      $container->get('event_dispatcher'),
      $container->get('flood'),
      $container->get('module_handler'),
      $container->get('session'),
      $container->get('datetime.time'),
      $container->get('entity_type.manager')->getStorage('user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'creative_register_password_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   Creative that request to register.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   The form or a redirect.
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    AccountInterface $user = NULL
  ) {

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
        ->addError($this->t('You have tried to use an registration link that has expired. Please contact hello@youvo.org.'));
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

    $form['#title'] = $this->t('Welcome');

    $form['password'] = [
      '#type' => 'password_confirm',
      '#size' => 25,
      '#description' => $this->t('To change the current user password, enter the new password in both fields.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Set password'),
      ],
    ];

    $form['#theme'] = 'creative_register_password_form';

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $timestamp = $form_state->getValue('timestamp');
    $hash = $form_state->getValue('hash');
    $password = $form_state->getValue('password');
    /** @var \Drupal\creatives\Entity\Creative $creative */
    $creative = $form_state->getValue('user');

    if (
      $creative->isAuthenticated() &&
      $timestamp <= $this->time->getCurrentTime() &&
      hash_equals($hash, user_pass_rehash($creative, $timestamp)) &&
      !empty($password)
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
        $redirect_url = Url::fromUri('https://app.dev.youvo.org' . $path)->toString();
      }
      $response = new TrustedRedirectResponse($redirect_url);
      $form_state->setResponse($response);
    }

    $this->messenger()->addError($this->t('You have tried to use a one-time login link that has either been used or is no longer valid. Please contact hello@youvo.org.'));
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
    $this->userStorage->updateLastLoginTimestamp($account);
    $this->session->migrate();
    $this->session->set('uid', $account->id());
    $this->session->set('check_logged_in', TRUE);

    // Call all login hooks for newly logged-in user.
    $this->moduleHandler->invokeAll('user_login', [$account]);
  }

}
