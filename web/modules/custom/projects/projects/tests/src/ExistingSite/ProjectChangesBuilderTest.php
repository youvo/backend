<?php

namespace Drupal\Tests\projects\ExistingSite;

use Drupal\file\Entity\File;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\projects\Service\ProjectChangesBuilder;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the entries of the changes of a project.
 */
#[Group('projects')]
class ProjectChangesBuilderTest extends ProjectResourceTestBase {

  /**
   * Builds the changes of a project.
   */
  protected function build(ProjectInterface $project): array {
    return $this->container->get('projects.changes_builder')->build($this->reloadProject($project));
  }

  /**
   * Gets the fields of an entry, keyed by label.
   */
  protected function fields(array $entry): array {
    return array_column($entry['fields'], NULL, 'label');
  }

  /**
   * Tests the entries, newest first, with authors and values.
   */
  public function testEntries(): void {

    $project = $this->createProject(ProjectState::Pending);
    $project->set('field_city', 'Hamburg')
      ->set('body', [
        'value' => "Erster Absatz.\n\nDie Plakate sollen im Format A3 gedruckt werden.\n\nLetzter Absatz.",
        'format' => 'plain_text',
      ])
      ->setSyncing(TRUE)
      ->save();
    $project->setSyncing(FALSE);
    $this->setCurrentUser($project->getOwner());
    $project->setTitle('Neuer Titel')->save();
    $this->setCurrentUser($project->getOwner()->getManager());
    $project->set('field_city', 'Kiel')
      ->set('body', [
        'value' => "Erster Absatz.\n\nDie Plakate sollen in A2 und A3 gedruckt werden.\n\nLetzter Absatz.",
        'format' => 'plain_text',
      ])
      ->save();

    $changes = $this->build($project);
    $this->assertSame(0, $changes['earlier']);
    [$newest, $middle, $oldest] = $changes['entries'];

    $this->assertStringStartsWith('Managerin ', $newest['author']);
    $fields = $this->fields($newest);
    $this->assertSame(['Project Details', 'City'], array_keys($fields));
    $this->assertSame(['Hamburg', 'Kiel'], [$fields['City']['old'], $fields['City']['new']]);
    $this->assertFalse($fields['City']['long']);
    $this->assertTrue($fields['Project Details']['long']);
    $this->assertStringContainsString('<del', $fields['Project Details']['diff']);
    $this->assertStringContainsString('<ins', $fields['Project Details']['diff']);
    $this->assertStringContainsString('A2', $fields['Project Details']['diff']);

    $this->assertStringStartsWith('Organisation ', $middle['author']);
    $this->assertSame(['Project Title'], array_keys($this->fields($middle)));
    $this->assertSame((int) $project->getRevisionId(), $newest['revision_id']);

    $this->assertTrue($oldest['created']);
    $this->assertSame([], $oldest['fields']);
  }

  /**
   * Tests that references, booleans and dates read as text.
   */
  public function testReadableValues(): void {

    $project = $this->createProject(ProjectState::Pending);
    $skill = Term::create(['vid' => 'skills', 'name' => 'Plakatgestaltung']);
    $skill->save();
    $this->markEntityForCleanup($skill);
    $project->set('field_skills', [$skill->id()])
      ->set('field_allowance', TRUE)
      ->set('field_deadline', '2030-03-01')
      ->save();

    $fields = $this->fields($this->build($project)['entries'][0]);
    $this->assertSame('Plakatgestaltung', $fields['Skills']['new']);
    $this->assertSame('On', $fields['Allowance']['new']);
    $this->assertSame('01.03.2030', $fields['Deadline']['new']);
  }

  /**
   * Tests that an image change has the old and the new image.
   */
  public function testImageValues(): void {

    $project = $this->createProject(ProjectState::Pending);
    $uri = $this->container->get('file_system')->copy('core/misc/druplicon.png', 'public://', 0);
    $file = File::create(['uri' => $uri, 'status' => 1]);
    $file->save();
    $this->markEntityForCleanup($file);
    $project->set('field_image', ['target_id' => $file->id(), 'alt' => 'Alt'])->save();

    $image = $this->fields($this->build($project)['entries'][0])['Project Image'];
    $this->assertNull($image['old_image']);
    $this->assertStringContainsString('druplicon', $image['new_image']);
  }

  /**
   * Tests that values carry no markup of the content.
   */
  public function testEscapesValues(): void {

    $project = $this->createProject(ProjectState::Pending);
    $project->setTitle('<script>alert(1)</script>Titel')->save();
    $project->set('body', ['value' => '<script>alert(1)</script><p>Text</p>', 'format' => 'plain_text'])->save();

    [$body_entry, $title_entry] = $this->build($project)['entries'];
    $title = $this->fields($title_entry)['Project Title'];
    $this->assertStringNotContainsString('<script', $title['diff']);
    $this->assertStringNotContainsString('<script', $this->fields($body_entry)['Project Details']['diff']);
  }

  /**
   * Tests that a long text shows the changed sentence with its neighbors.
   */
  public function testLongTextContext(): void {

    $project = $this->createProject(ProjectState::Pending);
    $old = 'Eins ist hier. Zwei ist hier. Drei ist hier. Vier ist hier. Fünf ist hier.';
    $project->set('body', ['value' => $old, 'format' => 'plain_text'])
      ->setSyncing(TRUE)
      ->save();
    $project->setSyncing(FALSE);
    $new = 'Eins ist hier. Zwei ist hier. Drei ist dort. Vier ist hier. Fünf ist hier.';
    $project->set('body', ['value' => $new, 'format' => 'plain_text'])->save();

    $diff = $this->fields($this->build($project)['entries'][0])['Project Details']['diff'];
    $this->assertStringContainsString('Zwei', $diff);
    $this->assertStringContainsString('Vier', $diff);
    $this->assertStringNotContainsString('Eins', $diff);
    $this->assertStringNotContainsString('Fünf', $diff);
    $this->assertStringStartsWith('<p>…', $diff);
    $this->assertStringEndsWith('…</p>', $diff);
  }

  /**
   * Tests that values are plain text, not escaped twice.
   */
  public function testPlainValues(): void {

    $project = $this->createProject(ProjectState::Pending);
    $project->setTitle('Plakate & Flyer')->save();
    $project->setTitle('Plakate > Flyer')->save();

    $title = $this->fields($this->build($project)['entries'][0])['Project Title'];
    $this->assertSame('Plakate & Flyer', $title['old']);
    $this->assertSame('Plakate > Flyer', $title['new']);
  }

  /**
   * Tests that only the newest revisions get entries.
   */
  public function testLimit(): void {

    $builder = new class(
      $this->container->get('entity_type.manager'),
      $this->container->get('diff.entity_comparison'),
      $this->container->get('language_manager'),
      $this->container->get('file_url_generator'),
    ) extends ProjectChangesBuilder {

      public const int LIMIT = 2;

    };
    $project = $this->createProject(ProjectState::Pending);
    foreach (['Eins', 'Zwei', 'Drei'] as $title) {
      $project->setTitle($title)->save();
    }

    $changes = $builder->build($this->reloadProject($project));
    $this->assertCount(2, $changes['entries']);
    $this->assertSame(2, $changes['earlier']);
    $this->assertFalse($changes['entries'][1]['created']);
  }

  /**
   * Tests that a change of another translation has no entry.
   */
  public function testOtherLanguageSkipped(): void {

    $project = $this->createProject(ProjectState::Pending);
    $project->addTranslation('en', ['title' => 'English'])->save();
    $count = count($this->build($project)['entries']);

    $project = $this->reloadProject($project);
    $project->getTranslation('en')->setTitle('English changed')->save();

    $this->assertCount($count, $this->build($project)['entries']);
  }

}
