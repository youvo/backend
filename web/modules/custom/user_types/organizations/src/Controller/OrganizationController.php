<?php

namespace Drupal\organizations\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\organizations\Entity\Organization;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller routines for organization routes.
 */
final class OrganizationController extends ControllerBase {

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
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function invite(int $uid, int $timestamp, string $hash, Request $request): RedirectResponse {

    $user_storage = $this->entityTypeManager()->getStorage('user');

    // The invitation link should only be used for non-authenticated users.
    if ($this->currentUser()->isAuthenticated()) {
      // The current user is already logged in.
      if ($this->currentUser()->id() == $uid) {
        throw new AccessDeniedHttpException();
      }
      // A different user is already logged in on the computer.
      /** @var \Drupal\user\UserInterface|null $reset_link_user */
      $reset_link_user = $user_storage->load($uid);
      if ($reset_link_user) {
        $this->messenger()
          ->addWarning($this->t('Another organization (%other_user) is already logged into the site on this computer, but you tried to use a one-time link for user %resetting_user. Please <a href=":logout">log out</a> and try using the link again.',
            [
              '%other_user' => $this->currentUser()->getAccountName(),
              '%resetting_user' => $reset_link_user->getAccountName(),
              ':logout' => Url::fromRoute('user.logout')->toString(),
            ]));
      }
      else {
        // Invalid one-time link specifies an unknown user.
        $this->messenger()
          ->addError($this->t('The one-time login link you clicked is invalid.'));
      }
    }

    // Load the organization.
    $organization = $user_storage->load($uid);

    // Verify that the organization is active and a prospect.
    if (
      !$organization instanceof Organization ||
      !$organization->isActive() ||
      !$organization->hasRoleProspect()
    ) {
      throw new AccessDeniedHttpException();
    }

    $session = $request->getSession();
    $session->set('organization_invite_hash', $hash);
    $session->set('organization_invite_timeout', $timestamp);
    return $this->redirect(
      'organizations.invite.form',
      ['organization' => $uid]
    );
  }

}
