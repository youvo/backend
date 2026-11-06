<?php

namespace Drupal\user_types\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;

/**
 * Hook implementations for core's login and password forms.
 *
 * Organizations and creatives meet these forms when they sign in to the hub
 * or the app, so they are German and look like the mails, see the yv theme's
 * page--public.html.twig. The site has no translations, so the German texts
 * are set here. They address nobody directly: organizations are addressed
 * with „ihr“, creatives with „du“.
 */
class AccountFormHooks {

  use StringTranslationTrait;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * Implements hook_gin_ignore_sticky_form_actions().
   *
   * The forms keep their buttons below the fields: Gin would move them into
   * the sticky bar, which visitors without the admin toolbar never see.
   *
   * @return string[]
   *   The ids of the forms.
   */
  #[Hook('gin_ignore_sticky_form_actions')]
  public function ginIgnoreStickyFormActions(): array {
    return ['user_login_form', 'user_pass', 'user_pass_reset', 'user_types_password_reset'];
  }

  /**
   * Implements hook_form_FORM_ID_alter() for user_login_form.
   *
   * The mail_login module only allows the e-mail address, and its English
   * description goes. youvo_user_login_form_submit() still sends staff to
   * their dashboard.
   */
  #[Hook('form_user_login_form_alter')]
  public function formUserLoginFormAlter(array &$form, FormStateInterface $form_state): void {
    $form['#title'] = $this->t('Anmelden');
    $form['name']['#title'] = $this->t('E-Mail-Adresse');
    unset($form['name']['#description'], $form['pass']['#description']);
    $form['pass']['#title'] = $this->t('Passwort');
    $form['actions']['submit']['#value'] = $this->t('Anmelden');
    $form['password_link'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#attributes' => ['class' => ['public-page-link']],
      'link' => Link::createFromRoute($this->t('Passwort vergessen?'), 'user.pass')->toRenderable(),
      '#weight' => 100,
    ];
  }

  /**
   * Implements hook_form_FORM_ID_alter() for user_pass.
   */
  #[Hook('form_user_pass_alter')]
  public function formUserPassAlter(array &$form, FormStateInterface $form_state): void {
    $form['#title'] = $this->t('Passwort vergessen');
    $form['name']['#title'] = $this->t('E-Mail-Adresse');
    // Signed in, core sends the link to the account's address.
    $form['mail'] = [
      '#prefix' => '<p class="public-page-note">',
      '#markup' => $form['name']['#type'] === 'value'
        ? $this->t('Wir schicken den Link für ein neues Passwort an %email. Er funktioniert erst nach dem Abmelden.', ['%email' => $form['name']['#value']])
        : $this->t('Wir schicken einen Link für ein neues Passwort an die E-Mail-Adresse des Kontos.'),
      '#suffix' => '</p>',
      '#weight' => -10,
    ];
    $form['actions']['submit']['#value'] = $this->t('Link senden');
    $form['#submit'][] = [static::class, 'germanPasswordMessage'];
    $form['login_link'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#attributes' => ['class' => ['public-page-link']],
      'link' => Link::createFromRoute($this->t('Zurück zur Anmeldung'), 'user.login')->toRenderable(),
      '#weight' => 100,
    ];
  }

  /**
   * Implements hook_form_FORM_ID_alter() for user_pass_reset.
   *
   * The page of the link in the password mail. Its button logs in and leads
   * to the page where the new password is set: PasswordResetForm for
   * organizations and creatives, the account's edit form for staff.
   */
  #[Hook('form_user_pass_reset_alter')]
  public function formUserPassResetAlter(array &$form, FormStateInterface $form_state): void {

    // See UserController::getResetPassForm(): core gives no expiry for an
    // account that never logged in, as its link does not expire.
    [$user, $expiration_date, $timestamp] = $form_state->getBuildInfo()['args'] + [NULL, NULL, NULL];
    if (!$user instanceof UserInterface) {
      return;
    }

    $form['#title'] = $this->t('Neues Passwort festlegen');
    if ($expiration_date) {
      $timeout = (int) $this->configFactory->get('user.settings')->get('password_reset_timeout');
      $form['message'] = [
        '#prefix' => '<p class="public-page-note">',
        '#markup' => $this->t('Dieser Link meldet %email einmal an. Danach lässt sich ein neues Passwort festlegen. Er gilt bis @date Uhr.', [
          '%email' => $user->getEmail(),
          '@date' => $this->dateFormatter->format((int) $timestamp + $timeout, 'custom', 'd.m.Y, H:i'),
        ]),
        '#suffix' => '</p>',
      ];
    }
    else {
      $form['message'] = [
        '#prefix' => '<p class="public-page-note">',
        '#markup' => $this->t('Dieser Link meldet %email einmal an. Danach lässt sich ein Passwort festlegen.', ['%email' => $user->getEmail()]),
        '#suffix' => '</p>',
      ];
    }
    unset($form['help']);
    $form['actions']['submit']['#value'] = $this->t('Weiter');
  }

  /**
   * Replaces core's English confirmation of a password request.
   *
   * A submit handler of user_pass, after core's. Other messages stay as they
   * are, in their order. Core then leads to the front page, the staff
   * dashboard, which visitors may not open. They go back to the login, where
   * the message shows the same for known and unknown addresses. Signed-in
   * users may not open the login and keep core's redirect.
   */
  public static function germanPasswordMessage(array &$form, FormStateInterface $form_state): void {
    $messenger = \Drupal::messenger();
    $identifier = $form_state->getValue('name');
    $core = (string) t('If %identifier is a valid account, an email will be sent with instructions to reset your password.', ['%identifier' => $identifier]);
    foreach ($messenger->deleteByType(MessengerInterface::TYPE_STATUS) as $message) {
      $messenger->addStatus((string) $message === $core
        ? t('Wenn %identifier zu einem Konto gehört, ist eine Mail mit einem Link für ein neues Passwort unterwegs.', ['%identifier' => $identifier])
        : $message);
    }
    if (\Drupal::currentUser()->isAnonymous()) {
      $form_state->setRedirect('user.login');
    }
  }

}
