<?php

declare(strict_types = 1);

namespace Drupal\youvo_lifecycle\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\youvo_lifecycle\Permissions;
use Drupal\youvo_lifecycle\Plugin\Field\FieldType\YouvoLifecycleItem;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the workflows field.
 */
class YouvoLifecycleContraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function validate($field, Constraint $constraint): void {
    assert($field instanceof YouvoLifecycleItem);
    assert($constraint instanceof YouvoLifecycleContraint);

    $entity = $field->getEntity();
    $workflow_type = $field->getWorkflow()->getTypePlugin();
    $newState = $field->value ?? NULL;

    // An entity can start its life in any state.
    if (!$newState || $entity->isNew()) {
      return;
    }

    /** @var \Drupal\Core\Entity\FieldableEntityInterface $original_entity */
    $original_entity = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->loadUnchanged($entity->id());
    if (!$entity->isDefaultTranslation() && $original_entity->hasTranslation($entity->language()->getId())) {
      $original_entity = $original_entity->getTranslation($entity->language()->getId());
    }

    /** @var \Drupal\youvo_lifecycle\Plugin\Field\FieldType\YouvoLifecycleItem $originalItem */
    $originalItem = $original_entity->{$field->getFieldDefinition()->getName()};
    $originalState = $originalItem->value;

    // The state does not have to change. It can also be empty for existing
    // content that got enriched with a workflow.
    if ($originalState === $newState || empty($originalState)) {
      return;
    }

    if (!$workflow_type->hasTransitionFromStateToState($originalState, $newState)) {
      $this->context->addViolation($constraint->message, [
        '%state' => $newState,
        '%previous_state' => $originalState,
      ]);
    }
    else {
      $transition = $workflow_type->getTransitionFromStateToState($originalState, $newState);
      if (!Permissions::useTransition($this->currentUser, $field->getWorkflow()->id(), $transition)) {
        $this->context->addViolation($constraint->insufficientPermissionsTransition, [
          '%transition' => $transition->label(),
        ]);
      }
    }
  }

}
