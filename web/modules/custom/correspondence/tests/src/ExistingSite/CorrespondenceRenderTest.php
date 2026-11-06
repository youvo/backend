<?php

namespace Drupal\Tests\correspondence\ExistingSite;

use Drupal\Core\Render\RenderContext;
use Drupal\correspondence\CorrespondenceInterface;
use Drupal\correspondence\Entity\Correspondence;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the rendering of correspondence entries.
 *
 * The filter of mail bodies is tested in CorrespondenceBodyFilterTest. The
 * entries are not marked for cleanup: they are deleted with their project.
 */
#[Group('correspondence')]
class CorrespondenceRenderTest extends ProjectResourceTestBase {

  /**
   * Mail bodies are filtered, notes are escaped plain text.
   */
  public function testRender(): void {

    $project = $this->createProject(ProjectState::Draft);
    $mail_values = [
      'type' => 'mail',
      'project' => $project->id(),
      'subject' => 'S <i>x</i>',
      'body' => ['value' => '<p onclick="x()">Hi</p><script>alert(1)</script>', 'format' => 'email_html'],
      'subtype' => 'x',
      'origin' => 'automatic',
      'result' => 'sent',
    ];
    $mail = $this->createEntry($mail_values);
    $build = $this->container->get('entity_type.manager')->getViewBuilder('correspondence')->view($mail);
    $renderer = $this->container->get('renderer');
    $context = new RenderContext();
    $html = (string) $renderer->executeInRenderContext($context, static fn () => $renderer->render($build));
    $this->assertStringContainsString('<p>Hi</p>', $html);
    $this->assertStringNotContainsString('<script', $html);
    $this->assertStringNotContainsString('onclick', $html);
    $this->assertStringContainsString('logbook-timeline', $html);
    $this->assertStringContainsString('data-timeline-type="mail"', $html);
    $this->assertStringContainsString('data-timeline-type="mail"', $html);
    $this->assertStringContainsString('<details', $html);
    $this->assertStringContainsString('S &lt;i&gt;x&lt;/i&gt;', $html);
    $this->assertStringNotContainsString('logbook-timeline-failed', $html);
    // The rendered entry varies by time zone, as the date follows the user.
    $this->assertContains('timezone', $context->pop()->getCacheContexts());

    // Failed mails are marked.
    $failed = $this->createEntry(['result' => 'failed'] + $mail_values);
    $this->assertStringContainsString('logbook-timeline-failed', $this->renderEntry($failed));

    $note = $this->createEntry([
      'type' => 'note',
      'project' => $project->id(),
      'subject' => '<b>T</b>',
      'body' => ['value' => "<i>x</i>\nzwei", 'format' => 'plain_text'],
      'occurred' => 1700000000,
    ]);
    $html = $this->renderEntry($note);
    $this->assertStringContainsString('&lt;b&gt;T&lt;/b&gt;', $html);
    $this->assertStringContainsString('&lt;i&gt;x&lt;/i&gt;', $html);
    $this->assertStringContainsString('<br', $html);
    $this->assertStringContainsString('data-timeline-type="note"', $html);
    $this->assertStringNotContainsString('<details', $html);
  }

  /**
   * Link only entries are not marked as not delivered.
   */
  public function testLinkOnlyEntry(): void {

    $project = $this->createProject(ProjectState::Draft);
    $values = [
      'type' => 'mail',
      'project' => $project->id(),
      'subject' => 'S',
      'body' => ['value' => '<p>Hi</p>', 'format' => 'email_html'],
      'subtype' => 'x',
      'origin' => 'automatic',
    ];

    $link = $this->createEntry(['result' => 'link'] + $values);
    $this->assertCount(0, $link->validate()->getByField('result'));
    $html = $this->renderEntry($link);
    $this->assertStringContainsString('Link only', $html);
    $this->assertStringNotContainsString('Not delivered', $html);

    $failed = $this->createEntry(['result' => 'failed'] + $values);
    $html = $this->renderEntry($failed);
    $this->assertStringContainsString('Not delivered', $html);
    $this->assertStringNotContainsString('Link only', $html);
  }

  /**
   * Creates a saved entry.
   */
  protected function createEntry(array $values): CorrespondenceInterface {
    $entry = Correspondence::create($values);
    $entry->save();
    return $entry;
  }

  /**
   * Renders an entry.
   */
  protected function renderEntry(CorrespondenceInterface $entry): string {
    $build = $this->container->get('entity_type.manager')->getViewBuilder('correspondence')->view($entry);
    return (string) $this->container->get('renderer')->renderInIsolation($build);
  }

}
