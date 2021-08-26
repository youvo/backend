<?php

namespace Drupal\youvo_lifecycle\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The ProjectMediateForm provides a simple UI for changing lifecycle state.
 */
class ProjectMediateForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new ProjectMediateForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'project_mediate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $nid = '') {

    // Store nid for submit handler.
    $form['nid'] = [
      '#type' => 'value',
      '#value' => $nid,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Projekt vermitteln'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Initialize.
    $nid = $form_state->getValues()['nid'];

    // Try to load project.
    /** @var \Drupal\youvo_projects\Entity\Project $project */
    $project = $this->entityTypeManager->getStorage('node')->load($nid);

    // Set redirect after submission.
    $messenger = \Drupal::messenger();
    $messenger->addMessage($project->getState());
    $form_state->setRedirect('entity.node.canonical', ['node' => $nid]);
  }

}
