<?php

namespace Drupal\Tests\projects\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\creatives\Entity\Creative;
use Drupal\projects\Event\ProjectMediateEvent;
use Drupal\projects\Form\ProjectCompleteForm;
use Drupal\projects\Form\ProjectMediateForm;
use Drupal\projects\Form\ProjectPublishForm;
use Drupal\projects\Form\ProjectResetForm;
use Drupal\projects\Form\ProjectSubmitForm;
use Drupal\projects\Form\ProjectUnarchiveForm;
use Drupal\projects\ProjectInterface;
use Drupal\Tests\projects\Kernel\EventSubscriber\ProjectEventSubscriberTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the project admin forms that dispatch a project event.
 */
#[Group('projects')]
class ProjectAdminFormRedirectTest extends ProjectEventSubscriberTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'child_entities',
    'content_translation',
    'language',
    'system',
  ];

  /**
   * The project under test, a draft of its organization.
   */
  protected ProjectInterface $project;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->project = $this->createProject();
    $this->project->save();
  }

  /**
   * Tests that the admin forms redirect to the project edit form.
   */
  #[DataProvider('transitionFormProvider')]
  public function testTransitionFormRedirect(string $form_class): void {

    $form_object = $this->container->get('class_resolver')->getInstanceFromDefinition($form_class);
    $form_state = (new FormState())->setValues([
      'project' => $this->project,
      'select_participants' => [],
    ]);
    $form = [];
    $form_object->submitForm($form, $form_state);

    $redirect = $form_state->getRedirect();
    $this->assertSame('entity.project.edit_form', $redirect->getRouteName());
    $this->assertSame(['project' => $this->project->id()], $redirect->getRouteParameters());
  }

  /**
   * Tests that the mediate form dispatches the selected applicants.
   */
  public function testMediateFormSelectsApplicants(): void {

    $selected = $this->createCreative();
    $other = $this->createCreative();
    $this->project->appendApplicant($selected);
    $this->project->appendApplicant($other);
    $events = [];
    $this->eventDispatcher->addListener(ProjectMediateEvent::class, static function (ProjectMediateEvent $event) use (&$events): void {
      $events[] = $event;
    }, 2000);

    $form_object = $this->container->get('class_resolver')->getInstanceFromDefinition(ProjectMediateForm::class);
    $form_state = (new FormState())->setValues([
      'project' => $this->project,
      'select_participants' => [$selected->id() => $selected->id(), $other->id() => 0],
    ]);
    $form = [];
    $form_object->submitForm($form, $form_state);

    $this->assertCount(1, $events);
    $creatives = $events[0]->getCreatives();
    $this->assertSame([(int) $selected->id()], array_keys($creatives));
    $this->assertInstanceOf(Creative::class, reset($creatives));
  }

  /**
   * Provides the admin forms.
   */
  public static function transitionFormProvider(): array {
    return [
      'submit' => [ProjectSubmitForm::class],
      'publish' => [ProjectPublishForm::class],
      'mediate' => [ProjectMediateForm::class],
      'complete' => [ProjectCompleteForm::class],
      'reset' => [ProjectResetForm::class],
      'unarchive' => [ProjectUnarchiveForm::class],
    ];
  }

}
