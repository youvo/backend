<?php

namespace Drupal\academy_child_entities;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides dynamic permissions for Child Entity of different types.
 *
 * @ingroup academy_paragraph
 */
class ChildEntityPermissions {

  use StringTranslationTrait;

  /**
   * Returns an array of node type permissions.
   *
   * @return array
   *   The Committee by bundle permissions.
   *   @see \Drupal\user\PermissionHandlerInterface::getPermissions()
   */
  public function generatePermissions() {
    $perms = [];

    foreach (\Drupal::entityTypeManager()->getDefinitions() as $entity_type) {
      if ($entity_type->entityClassImplements(ChildEntityInterface::class)) {
        if ($entity_type->getBundleEntityType()) {
          foreach (\Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type->id()) as $bundle_type) {
            $perms += $this->buildBundlePermissions($entity_type, $bundle_type);
          }
        }
        $perms += $this->buildEntityPermissions($entity_type);
      }
    }

    return $perms;
  }

  /**
   * Returns a list of node permissions for a given node type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity bundle type.
   *
   * @return array
   *   An associative array of permission names and descriptions.
   */
  protected function buildEntityPermissions(EntityTypeInterface $entity_type) {
    $type_name = $entity_type->getSingularLabel();
    $type_params = ['%type_name' => $entity_type->getSingularLabel()];

    $perms = [
      "create $type_name entities" => [
        'title' => $this->t('Create new %type_name', ['%type_name' => $entity_type->getPluralLabel()]),
        'description' => $this->t('To create an entity, you also need permission to view the parent.'),
      ],
      "edit own $type_name entities" => [
        'title' => $this->t('Edit own %type_name', ['%type_name' => $entity_type->getPluralLabel()]),
        'description' => $this->t('To edit an entity, you also need permission to view the parent.'),
      ],
      "edit any $type_name entities" => [
        'title' => $this->t('Edit any %type_name', $type_params),
        'description' => $this->t('To edit an entity, you also need permission to view the parent.'),
      ],
      "delete own $type_name entities" => [
        'title' => $this->t('Delete own %type_name', ['%type_name' => $entity_type->getPluralLabel()]),
        'description' => $this->t('To delete an entity, you also need permission to view the parent.'),
      ],
      "delete any $type_name entities" => [
        'title' => $this->t('Delete any %type_name', $type_params),
        'description' => $this->t('To delete an entity, you also need permission to view the parent.'),
      ],
    ];

    if ($entity_type->entityClassImplements(RevisionLogInterface::class)) {
      $perms += [
        "view $type_name revisions" => [
          'title' => $this->t('View %type_name revisions', $type_params),
          'description' => $this->t('To view a revision, you also need permission to view the entity item.'),
        ],
        "revert $type_name revisions" => [
          'title' => $this->t('Revert %type_name revisions', $type_params),
          'description' => $this->t('To revert a revision, you also need permission to edit the entity item.'),
        ],
        "delete $type_name revisions" => [
          'title' => $this->t('Delete %type_name revisions', $type_params),
          'description' => $this->t('To delete a revision, you also need permission to delete the entity item.'),
        ],
      ];
    }

    return $perms;
  }

  /**
   * Returns a list of node permissions for a given node type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The Child Entity Type.
   * @param array $bundle_type_info
   *   The entity bundle type.
   *
   * @return array
   *   An associative array of permission names and descriptions.
   */
  protected function buildBundlePermissions(EntityTypeInterface $entity_type, array $bundle_type_info) {
    $bundle_type_label = $bundle_type_info['label'];
    $entity_type_name = $entity_type->getSingularLabel();
    $type_params = [
      '%type_name' => $bundle_type_label,
      '%entity_type' => $entity_type_name,
    ];

    return [
      "create $bundle_type_label $entity_type_name" => [
        'title' => $this->t('%type_name: Create new %entity_type', $type_params),
      ],
      "edit own $bundle_type_label $entity_type_name" => [
        'title' => $this->t('%type_name: Edit own %entity_type', $type_params),
      ],
      "edit any $bundle_type_label $entity_type_name" => [
        'title' => $this->t('%type_name: Edit any %entity_type', $type_params),
      ],
      "delete own $bundle_type_label $entity_type_name" => [
        'title' => $this->t('%type_name: Delete own %entity_type', $type_params),
      ],
      "delete any $bundle_type_label $entity_type_name" => [
        'title' => $this->t('Delete any %type_name type %entity_type entities', $type_params),
      ],
      "view $bundle_type_label $entity_type_name revisions" => [
        'title' => $this->t('View %type_name type %entity_type revisions', $type_params),
        'description' => $this->t('To view a revision, you also need permission to view the entity item.'),
      ],
      "revert $bundle_type_label $entity_type_name revisions" => [
        'title' => $this->t('Revert %type_name type %entity_type revisions', $type_params),
        'description' => $this->t('To revert a revision, you also need permission to edit the entity item.'),
      ],
      "delete $bundle_type_label $entity_type_name revisions" => [
        'title' => $this->t('Delete %type_name type %entity_type revisions', $type_params),
        'description' => $this->t('To delete a revision, you also need permission to delete the entity item.'),
      ],
    ];
  }

}
