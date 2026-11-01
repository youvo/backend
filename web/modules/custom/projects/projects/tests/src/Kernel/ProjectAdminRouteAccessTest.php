<?php

namespace Drupal\Tests\projects\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\Form\ProjectCompleteForm;
use Drupal\projects\Form\ProjectMediateForm;
use Drupal\projects\Form\ProjectPublishForm;
use Drupal\projects\Form\ProjectResetForm;
use Drupal\projects\Form\ProjectSubmitForm;
use Drupal\projects\ProjectInterface;
use Drupal\projects\Routing\ProjectHtmlRouteProvider;
use Drupal\Tests\projects\Kernel\EventSubscriber\ProjectEventSubscriberTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the project admin forms are for administrators only.
 */
#[CoversClass(ProjectHtmlRouteProvider::class)]
#[Group('projects')]
class ProjectAdminRouteAccessTest extends ProjectEventSubscriberTestBase {

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
   * The project admin routes.
   */
  protected const array ROUTES = [
    'entity.project.add_form',
    'entity.project.edit_form',
    'entity.project.delete_form',
    'entity.project.collection',
    'entity.project.settings',
    'project.submit',
    'project.publish',
    'project.mediate',
    'project.complete',
    'project.reset',
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
    // The first user bypasses all permissions. Create it up front.
    $this->createCreative();

    Role::create(['id' => 'organization', 'label' => 'Organization'])
      ->grantPermission('create project')
      ->grantPermission('update own project')
      ->grantPermission('delete own project')
      ->save();
    Role::create(['id' => 'manager', 'label' => 'Manager'])
      ->grantPermission('general manager access')
      ->grantPermission('create project')
      ->grantPermission('update any project')
      ->save();
    Role::create(['id' => 'supervisor', 'label' => 'Supervisor'])
      ->grantPermission('general manager access')
      ->grantPermission('administer projects')
      ->save();
    Role::create(['id' => 'administrator', 'label' => 'Administrator', 'is_admin' => TRUE])
      ->save();

    $this->project = $this->createProject();
    $this->project->save();
  }

  /**
   * Tests the route access per role.
   */
  public function testRouteAccess(): void {
    $this->container->get('router.builder')->rebuild();
    $organization = $this->project->getOwner();
    $organization->addRole('organization')->save();
    $manager = $organization->getManager();
    $manager->addRole('manager')->save();

    $accounts = [
      'organization' => [$organization, FALSE],
      'manager' => [$manager, FALSE],
      'supervisor' => [$this->createCreative()->addRole('supervisor'), FALSE],
      'administrator' => [$this->createCreative()->addRole('administrator'), TRUE],
    ];

    foreach ($accounts as $label => [$account, $expected]) {
      foreach (static::ROUTES as $route) {
        $this->assertSame($expected, $this->checkRoute($route, $account), "$label on $route");
      }
    }

    // The canonical route keeps the entity view access.
    $this->assertTrue($this->checkRoute('entity.project.canonical', $organization));
  }

  /**
   * Tests that the admin transition forms redirect to the project edit form.
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
   * Provides the admin transition forms.
   */
  public static function transitionFormProvider(): array {
    return [
      'submit' => [ProjectSubmitForm::class],
      'publish' => [ProjectPublishForm::class],
      'mediate' => [ProjectMediateForm::class],
      'complete' => [ProjectCompleteForm::class],
      'reset' => [ProjectResetForm::class],
    ];
  }

  /**
   * Checks the access of an account to a route, with the project if needed.
   */
  protected function checkRoute(string $route, AccountInterface $account): bool {
    $parameters = str_contains($this->container->get('router.route_provider')->getRouteByName($route)->getPath(), '{project}')
      ? ['project' => $this->project->id()]
      : [];
    return $this->container->get('access_manager')->checkNamedRoute($route, $parameters, $account);
  }

}
