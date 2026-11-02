<?php

namespace Drupal\projects\Form;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\projects\ProjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a base class for admin forms that dispatch a project event.
 *
 * The subscribers of the event guard the project state, so the forms show
 * their refusal as an error message.
 */
abstract class ProjectEventFormBase extends FormBase {

  /**
   * Constructs a ProjectEventFormBase object.
   */
  public function __construct(protected EventDispatcherInterface $eventDispatcher) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('event_dispatcher'));
  }

  /**
   * Gets the label of the form, used as title and submit button.
   */
  abstract protected function label(): TranslatableMarkup;

  /**
   * Gets the message after a successful submit.
   */
  abstract protected function successMessage(): TranslatableMarkup;

  /**
   * Builds the event to dispatch on submit.
   */
  abstract protected function event(ProjectInterface $project, FormStateInterface $form_state): Event;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ProjectInterface $project = NULL): array {

    $form['#title'] = $this->t('@label: %title', [
      '@label' => $this->label(),
      '%title' => $project?->getTitle() ?? '',
    ]);

    $form['project'] = [
      '#type' => 'value',
      '#value' => $project,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->label(),
      '#weight' => 100,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    /** @var \Drupal\projects\ProjectInterface $project */
    $project = $form_state->getValue('project');

    try {
      $this->eventDispatcher->dispatch($this->event($project, $form_state));
      $this->messenger()->addStatus($this->successMessage());
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($e->getMessage());
    }

    $form_state->setRedirect('entity.project.edit_form', ['project' => $project->id()]);
  }

}
