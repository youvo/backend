<?php

namespace Drupal\academy_child_entities\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\academy_child_entities\Context\ChildEntityRouteContextTrait;

/**
 *
 */
class ChildContentEntityForm extends ContentEntityForm {

  use ChildEntityRouteContextTrait;

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\academy_child_entities\ChildEntityInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    $this->entity->setParentEntity($this->getParentEntity());
    return parent::save($form, $form_state);
  }

  /**
   * Get the Parent Entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The Parent Entity.
   */
  protected function getParentEntity() {
    if ($this->entity->isNew()) {
      return $this->getParentEntityFromRoute($this->entity->getParentEntityTypeId());
    }
    else {
      return $this->entity->getParentEntity();
    }
  }

}
