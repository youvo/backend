<?php

namespace Drupal\projects\Access;

use Drupal\child_entities\ChildEntityAccessControlHandler;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Extends child entity access for file upload.
 */
class ProjectResultUploadAccess extends ChildEntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResultInterface {

    // Only allow logic for project results.
    if ($context['entity_type_id'] !== 'project_result') {
      return AccessResult::forbidden();
    }

    // Grants access to broad user group for uploading files.
    if (
      in_array('creative', $account->getRoles(), TRUE) ||
      in_array('organization', $account->getRoles(), TRUE) ||
      in_array('manager', $account->getRoles(), TRUE) ||
      in_array('supervisor', $account->getRoles(), TRUE) ||
      in_array('administrator', $account->getRoles(), TRUE)
    ) {
      return AccessResult::allowed()->cachePerUser();
    }

    return AccessResult::neutral();
  }

}
