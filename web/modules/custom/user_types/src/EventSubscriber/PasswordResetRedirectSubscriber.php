<?php

namespace Drupal\user_types\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Entity\Organization;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Leads organizations and creatives from the one-time login to a new password.
 *
 * Core's UserController::resetPassLogin() checks the link (the user is
 * active, the hash, its expiry and that it was not used), logs the user in,
 * stores the pass_reset_<uid> token in the session and redirects to the user
 * edit form. That form belongs to the staff-only backend, so for
 * organizations and creatives only the redirect target changes, to
 * PasswordResetForm with the same token. Altering the response leaves
 * every check of core's controller as it is; a failed check never logs the
 * user in nor stores a token, so its response passes unchanged. Staff keep
 * core's edit form.
 */
class PasswordResetRedirectSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Constructs a PasswordResetRedirectSubscriber object.
   */
  public function __construct(
    protected AccountProxyInterface $account,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MessengerInterface $messenger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::RESPONSE => 'onResponse'];
  }

  /**
   * Redirects to the new password page after a successful one-time login.
   */
  public function onResponse(ResponseEvent $event): void {

    $request = $event->getRequest();
    $response = $event->getResponse();
    if (
      !$event->isMainRequest() ||
      $request->attributes->get('_route') !== 'user.reset.login' ||
      !$response instanceof RedirectResponse ||
      $this->account->isAnonymous() ||
      (string) $this->account->id() !== (string) $request->attributes->get('uid')
    ) {
      return;
    }

    $token = $request->getSession()->get('pass_reset_' . $this->account->id());
    $user = $this->entityTypeManager->getStorage('user')->load($this->account->id());
    if (
      !is_string($token) ||
      $user?->hasPermission('access site') !== FALSE ||
      !($user instanceof Organization || $user instanceof Creative)
    ) {
      return;
    }

    $response->setTargetUrl(Url::fromRoute('user_types.password_reset', [], [
      'query' => ['pass-reset-token' => $token],
      'absolute' => TRUE,
    ])->toString());

    // Core's English note points to the edit form; the new page explains
    // itself.
    $core = (string) $this->t('You have used a one-time login link. You can set your new password now.');
    foreach ($this->messenger->deleteByType(MessengerInterface::TYPE_STATUS) as $message) {
      if ((string) $message !== $core) {
        $this->messenger->addStatus($message);
      }
    }
  }

}
