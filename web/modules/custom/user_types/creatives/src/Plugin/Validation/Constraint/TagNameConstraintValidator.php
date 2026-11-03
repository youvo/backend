<?php

declare(strict_types=1);

namespace Drupal\creatives\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\creatives\TagName;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the name of a tag in its saved format.
 */
class TagNameConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = new static();
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function validate(mixed $value, Constraint $constraint): void {

    assert($value instanceof FieldItemListInterface);
    assert($constraint instanceof TagNameConstraint);

    // The constraint sits on the name of every term, but only tags are free.
    $term = $value->getEntity();
    if (!$term instanceof TermInterface || $term->bundle() !== TagName::VOCABULARY || $value->isEmpty()) {
      return;
    }

    // The term is saved in this format, so validate the formatted name.
    $name = (string) $value->value;
    $formatted = TagName::format($name);
    if ($formatted === '') {
      $this->context->addViolation($constraint->empty, ['%name' => $name]);
      return;
    }

    $query = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', TagName::VOCABULARY)
      ->condition('name', $formatted);
    if (!$term->isNew()) {
      $query->condition('tid', $term->id(), '<>');
    }
    if ($query->count()->execute()) {
      $this->context->addViolation($constraint->taken, ['%name' => $formatted]);
    }
  }

}
