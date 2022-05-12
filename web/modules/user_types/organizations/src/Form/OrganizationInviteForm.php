<?php

namespace Drupal\organizations\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\organizations\Entity\Organization;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Form controller for the organization invite forms.
 *
 * Organizations followed the link in the email, now they can enter a new
 * password.
 *
 * @internal
 */
final class OrganizationInviteForm extends FormBase {

  /**
   * Constructs a OrganizationInviteForm object.
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
    return 'organization_invite_password_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\Core\Session\AccountInterface $organization
   *   Organization requesting invite.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   The form or a redirect.
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    AccountInterface $organization = NULL
  ) {

    // Verify that the organization is active and a prospect.
    if (
      !$organization instanceof Organization ||
      !$organization->isActive() ||
      !$organization->hasRoleProspect()
    ) {
      throw new AccessDeniedHttpException();
    }

    // Get timestamp and hash from form_state on submit.
    $input = $form_state->getUserInput();
    $timestamp = $input['timestamp'] ?? NULL;
    $hash = $input['hash'] ?? NULL;

    // Get timestamp and hash from session on initial load.
    if (empty($timestamp) || empty($hash)) {
      $timestamp = $this->session->get('organization_invite_timeout');
      $hash = $this->session->get('organization_invite_hash');
      $this->session->remove('organization_invite_timeout');
      $this->session->remove('organization_invite_hash');
    }

    if (empty($timestamp) || empty($hash)) {
      throw new AccessDeniedHttpException();
    }

    // Compare current time with timeout in one week.
    $current = $this->time->getCurrentTime();
    $timeout = 2592000;

    // Redirect to front with message if timed out.
    if ($current - $timestamp > $timeout) {
      // @todo Adjust message.
      $this->messenger()
        ->addError($this->t('You have tried to use an invitation link that has expired. Please contact hello@youvo.org.'));
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

    $form['organization'] = [
      '#type' => 'value',
      '#value' => $organization,
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

    $form['#theme'] = 'organization_invite_password_form';

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
    /** @var \Drupal\organizations\Entity\Organization $organization */
    $organization = $form_state->getValue('organization');

    if (
      $organization->isAuthenticated() &&
      $timestamp <= $this->time->getCurrentTime() &&
      hash_equals($hash, user_pass_rehash($organization, $timestamp)) &&
      !empty($password)
    ) {
      $organization->promoteProspect();
      $organization->setPassword($password);
      $organization->save();
      $this->loginUser($organization);
      $this->flood->clear('user.password_request_user', $organization->id());

      return $this->redirect('<front>');
    }

    $this->messenger()->addError($this->t('You have tried to use a one-time login link that has either been used or is no longer valid. Please request a new one using the form below.'));
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
