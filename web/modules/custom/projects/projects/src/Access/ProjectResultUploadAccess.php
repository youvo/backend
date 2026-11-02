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

    // Grants access to a broad user group for uploading files.
    return AccessResult::allowedIfHasPermission($account, 'upload project result files');
  }

}
