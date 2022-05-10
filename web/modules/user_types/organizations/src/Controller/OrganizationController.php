<?php

namespace Drupal\organizations\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller routines for organization routes.
 */
final class OrganizationController extends ControllerBase {

  /**
   * Constructs a OrganizationController object.
   */
  public function __construct(
    protected AccountProxyInterface $account,
    protected LoggerInterface $logger,
    protected Session $session,
    protected TimeInterface $time,
    protected UserStorageInterface $userStorage
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('current_user'),
      $container->get('logger.factory')->get('user'),
      $container->get('session'),
      $container->get('datetime.time'),
      $container->get('entity_type.manager')->getStorage('user')
    );
  }

  /**
   * Validates user, hash, and timestamp; logs the user in if correct.
   *
   * @param int $uid
   *   User ID of the user requesting reset.
   * @param int $timestamp
   *   The current timestamp.
   * @param string $hash
   *   Login link hash.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Returns a redirect to the frontend.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function invite(int $uid, int $timestamp, string $hash, Request $request) {

    // The invitation link should only be used for non-authenticated users.
    if ($this->currentUser()->isAuthenticated()) {
      throw new AccessDeniedHttpException();
    }

    // Load the organization.
    /** @var \Drupal\organizations\Entity\Organization $organization */
    $organization = $this->userStorage->load($uid);

    // Verify that the organization exists, is active and a prospect.
    if ($organization === NULL || !$organization->isActive() || !$organization->hasRoleProspect()) {
      throw new AccessDeniedHttpException();
    }

    // @todo Expiry.
    if (hash_equals($hash, user_pass_rehash($organization, $timestamp))) {
      $organization->promoteProspect();
      $organization->save();
      $this->loginUser($organization);
    }

    // @todo Correct redirects.
    return new RedirectResponse('dashboard');
  }

  /**
   * Programmatically login a user.
   */
  protected function loginUser(UserInterface $account): void {
    $this->account->setAccount($account);
    $this->logger->notice('Session opened for %name.',
      ['%name' => $account->getAccountName()]);
    $account->setLastLoginTime($this->time->getRequestTime());
    $this->userStorage->updateLastLoginTimestamp($account);
    $this->session->migrate();
    $this->session->set('uid', $account->id());
    $this->session->set('check_logged_in', TRUE);

    // Call all login hooks for newly logged-in user.
    $this->moduleHandler()->invokeAll('user_login', [$account]);
  }

}
