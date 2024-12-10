<?php

declare(strict_types=1);

namespace Drupal\lifecycle\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\lifecycle\Permissions;
use Drupal\lifecycle\Plugin\Field\FieldType\LifecycleItem;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the workflows field.
 */
class LifecycleConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

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
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function validate($value, Constraint $constraint): void {
    assert($value instanceof LifecycleItem);
    assert($constraint instanceof LifecycleConstraint);

    /** @var \Drupal\projects\ProjectInterface $entity */
    $entity = $value->getEntity();
    $workflow_type = $value->getWorkflow()->getTypePlugin();
    $newState = $value->value ?? NULL;

    // An entity can start its life in any state.
    if (!$newState || $entity->isNew()) {
      return;
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface $original_entity */
    $original_entity = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->loadUnchanged($entity->id());
    if (!$entity->isDefaultTranslation() && $original_entity->hasTranslation($entity->language()->getId())) {
      $original_entity = $original_entity->getTranslation($entity->language()->getId());
    }

    /** @var \Drupal\lifecycle\Plugin\Field\FieldType\LifecycleItem $originalItem */
    $originalItem = $original_entity->{$value->getFieldDefinition()->getName()};
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
      if (!Permissions::useTransition($this->currentUser, $value->getWorkflow()->id(), $transition)) {
        $this->context->addViolation($constraint->insufficientPermissionsTransition, [
          '%transition' => $transition->label(),
        ]);
      }
    }
  }

}
