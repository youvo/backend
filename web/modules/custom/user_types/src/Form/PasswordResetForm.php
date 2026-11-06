<?php

namespace Drupal\user_types\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\organizations\Entity\Organization;
use Drupal\password_policy\PasswordPolicyValidatorInterface;
use Drupal\user\UserInterface;
use Drupal\user_types\PasswordChecklist;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Sets a new password after the one-time login of the password mail.
 *
 * Organizations and creatives land here instead of core's user edit form,
 * see PasswordResetRedirectSubscriber. Like that form, this one only works
 * with the pass_reset_<uid> token that core's one-time login put into the
 * session; the token is used up with the new password. Afterwards the user
 * signs in to the hub or the app, which finds the session of the one-time
 * login and completes OAuth without asking again.
 */
class PasswordResetForm extends FormBase {

  /**
   * The page the frontends open after signing in.
   */
  const string FRONTEND_TARGET = '/dashboard';

  /**
   * Constructs a PasswordResetForm object.
   */
  public function __construct(
    protected AccountProxyInterface $account,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected PasswordPolicyValidatorInterface $passwordPolicyValidator,
    protected PasswordChecklist $passwordChecklist,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('password_policy.validator'),
      $container->get(PasswordChecklist::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'user_types_password_reset';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): RedirectResponse|array {

    // Without the token of a fresh one-time login, the page leads to a new
    // link. This is friendlier than a 403 for the one who reopens an old tab
    // or goes back after setting the password, and tells nothing about
    // anyone. The form action keeps the query, so this check also guards the
    // submission.
    $token = $this->getRequest()->query->get('pass-reset-token');
    $stored = $this->getRequest()->getSession()->get('pass_reset_' . $this->account->id());
    if (
      $this->account->isAnonymous() ||
      !is_string($token) ||
      !is_string($stored) ||
      !hash_equals($stored, $token)
    ) {
      $this->messenger()->addError($this->t('Der Link für ein neues Passwort wurde schon benutzt oder ist nicht mehr gültig. Hier lässt sich ein neuer anfordern.'));
      return $this->redirect('user.pass');
    }

    $user = $this->entityTypeManager->getStorage('user')->load($this->account->id());
    if (!$user instanceof UserInterface) {
      return $this->redirect('user.pass');
    }
    $form_state->set('user', $user);

    $form['#title'] = $this->t('Neues Passwort festlegen');
    $form['note'] = [
      '#prefix' => '<p class="public-page-note">',
      '#markup' => $this->t('Das neue Passwort gilt für %email.', ['%email' => $user->getEmail()]),
      '#suffix' => '</p>',
    ];
    $form['password'] = [
      '#type' => 'password_confirm',
      '#size' => 25,
      '#required' => TRUE,
      '#after_build' => [[PasswordChecklist::class, 'germanPasswordTitles']],
    ];
    $form['password_rules'] = $this->passwordChecklist->build($this->checklistRole($user));
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Passwort speichern'),
      ],
    ];

    return $form;
  }

  /**
   * Checks the new password against the password policies.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $password = (string) $form_state->getValue('password');
    $user = $form_state->get('user');
    if ($password === '' || !$user instanceof UserInterface) {
      return;
    }
    $report = $this->passwordPolicyValidator->validatePassword($password, $user);
    if ($report->isInvalid()) {
      $form_state->setErrorByName('password', $report->getErrors());
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    /** @var \Drupal\user\UserInterface $user */
    $user = $form_state->get('user');
    $user->setPassword((string) $form_state->getValue('password'));
    $user->save();
    // The token allowed one change without the current password.
    $this->getRequest()->getSession()->remove('pass_reset_' . $user->id());

    // Staff only get here by typing the address; they stay in the backend.
    if ($user->hasPermission('access site')) {
      $this->messenger()->addStatus($this->t('Das neue Passwort ist gespeichert.'));
      $form_state->setRedirect('<front>');
      return;
    }
    $form_state->setResponse(new TrustedRedirectResponse($this->frontendLoginUrl($user)));
  }

  /**
   * Gets the role whose password rules the checklist lists.
   *
   * The validator applies the policies of all roles of the user. Staff have
   * the stricter staff policy, besides the creative role some of them have.
   */
  protected function checklistRole(UserInterface $user): string {
    if ($user->hasPermission('access site')) {
      $staff_roles = array_diff($user->getRoles(TRUE), ['creative', 'organization', 'prospect']);
      if ($staff_roles) {
        return reset($staff_roles);
      }
    }
    return $user instanceof Organization ? 'organization' : 'creative';
  }

  /**
   * Gets the login of the hub (organizations) or the app (creatives).
   *
   * This follows DoorLinks::loginUrl() and HubUrls in mailer_door, which
   * depends on this module and so cannot be used here. The frontend opens
   * the redirect path on its own host after signing in.
   */
  protected function frontendLoginUrl(UserInterface $user): string {

    if ($this->config('oauth_grant.settings')->get('local')) {
      $base = 'http://localhost:3000';
    }
    else {
      $base = rtrim((string) $this->config('youvo.settings')->get($user instanceof Organization ? 'hub_url' : 'app_url'), '/');
    }
    $url = $base . $this->config('oauth_grant.settings')->get('auth_success_redirect');
    $query = http_build_query(['redirect' => static::FRONTEND_TARGET], '', '&', PHP_QUERY_RFC3986);

    return $url . (str_contains($url, '?') ? '&' : '?') . $query;
  }

}
