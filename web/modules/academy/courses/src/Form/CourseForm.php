<?php

namespace Drupal\courses\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the course entity edit forms.
 */
class CourseForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    /** @var \Drupal\courses\Entity\Course $course */
    $course = $this->getEntity();

    // Attach js to hide 'show row weights' buttons.
    $form['#attached']['library'][] = 'academy/hideweightbutton';

    // Add machine name form element.
    $form['machine_name'] = [
      '#type' => 'machine_name',
      '#default_value' => $course->getMachineName(),
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#disabled' => !$course->isNew(),
      '#machine_name' => [
        'exists' => ['Drupal\courses\Entity\Course', 'load'],
        'source' => ['title', 'widget', 0, 'value'],
      ],
      '#description' => $this->t('A unique machine-readable name for this content type. It must only contain lowercase letters, numbers, and underscores.'),
      '#weight' => -4,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function save(array $form, FormStateInterface $form_state) {

    $entity = $this->getEntity();
    $result = $entity->save();
    $link = $entity->toLink($this->t('View'))->toRenderable();

    $message_arguments = ['%label' => $this->entity->label()];
    $logger_arguments = $message_arguments + ['link' => render($link)];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New course %label has been created.', $message_arguments));
      $this->logger('courses')->notice('Created new course %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The course %label has been updated.', $message_arguments));
      $this->logger('courses')->notice('Updated new course %label.', $logger_arguments);
    }

    if ($this->moduleHandler->moduleExists('lectures')) {
      $form_state->setRedirect('entity.lecture.collection', [], [
        'query' => ['cr' => $entity->id()],
        'fragment' => 'edit-course-' . $entity->id(),
      ]);
    }
    else {
      $form_state->setRedirect('admin.content');
    }

  }

}
