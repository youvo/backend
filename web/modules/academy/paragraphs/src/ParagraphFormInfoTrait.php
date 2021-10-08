<?php

namespace Drupal\paragraphs;

use Drupal\courses\Entity\Course;
use Drupal\lectures\Entity\Lecture;

/**
 * Provides a trait for paragraph form info element.
 */
trait ParagraphFormInfoTrait {

  /**
   * Validates form fields for creating a question.
   */
  public function getParagraphInfo(array &$form, Course $course, Lecture $lecture) {

    $disabled_lecture = '';
    if (!$lecture->isEnabled()) {
      $disabled_lecture = ' ' . $this->t('(Disabled)');
    }

    $disabled_course = '';
    if (!$course->isEnabled()) {
      $disabled_course = ' ' . $this->t('(Disabled)');
    }

    /** @var \Drupal\user\Entity\User $author */
    $author = $lecture->get('uid')->entity;

    $form['info'] = [
      '#type' => 'fieldset',
      '#attributes' => ['class' => ['fieldset__description']],
    ];
    $form['info']['markup'] = [
      '#markup' => $this->t('Course: @s', ['@s' => $course->getTitle()]) . $disabled_course . '<br />' .
      $this->t('Lecture: @s', ['@s' => $lecture->getTitle()]) . $disabled_lecture . '<br />' .
      $this->t('Author: @s', ['@s' => $author->get('field_name')->value]),
    ];
  }

}
