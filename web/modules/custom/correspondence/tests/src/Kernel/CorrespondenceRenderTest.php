<?php

namespace Drupal\Tests\correspondence\Kernel;

use Drupal\correspondence\Entity\Correspondence;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\Kernel\EventSubscriber\ProjectEventSubscriberTestBase;

/**
 * Tests the rendering of correspondence entries.
 *
 * @group correspondence
 */
class CorrespondenceRenderTest extends ProjectEventSubscriberTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'child_entities',
    'content_translation',
    'language',
    'filter',
    'text',
    'system',
    'correspondence',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('correspondence');
    $this->installConfig(['system', 'correspondence']);
  }

  /**
   * Renders an entry.
   */
  protected function renderEntry(Correspondence $entry): string {
    $builder = $this->container->get('entity_type.manager')->getViewBuilder('correspondence');
    $build = $builder->view($entry);
    return (string) $this->container->get('renderer')->renderInIsolation($build);
  }

  /**
   * Creates a saved mail with the given body.
   */
  protected function createMail(string $body, string $result = 'sent'): Correspondence {
    $project = $this->createProject(ProjectState::Draft);
    $project->save();
    $mail = Correspondence::create([
      'type' => 'mail',
      'project' => $project->id(),
      'subject' => 'S <i>x</i>',
      'body' => ['value' => $body, 'format' => 'email_html'],
      'subtype' => 'x',
      'origin' => 'automatic',
      'result' => $result,
    ]);
    $mail->save();
    return $mail;
  }

  /**
   * Stored bodies are filtered when rendered; notes are escaped plain text.
   */
  public function testRenderIsSafe(): void {
    $html = $this->renderEntry($this->createMail('<p onclick="x()">Hi</p><script>alert(1)</script><a href="javascript:alert(1)">b</a>'));
    $this->assertStringContainsString('Hi', $html);
    $this->assertStringNotContainsString('<script', $html);
    $this->assertStringNotContainsString('onclick', $html);
    $this->assertStringNotContainsString('javascript:', $html);
    $this->assertStringContainsString('logbook-timeline', $html);
    $this->assertStringContainsString('data-timeline-type="mail"', $html);
    $this->assertStringContainsString('logbook-timeline--mail', $html);
    $this->assertStringContainsString('<details', $html);
    $this->assertStringContainsString('S &lt;i&gt;x&lt;/i&gt;', $html);
    $this->assertStringNotContainsString('Not delivered', $html);

    $project = $this->createProject(ProjectState::Draft);
    $project->save();
    $note = Correspondence::create([
      'type' => 'note',
      'project' => $project->id(),
      'subject' => '<b>T</b>',
      'body' => ['value' => "<i>x</i>\nzwei", 'format' => 'plain_text'],
      'occurred' => 1700000000,
    ]);
    $note->save();
    $html = $this->renderEntry($note);
    $this->assertStringContainsString('&lt;b&gt;T&lt;/b&gt;', $html);
    $this->assertStringContainsString('&lt;i&gt;x&lt;/i&gt;', $html);
    $this->assertStringContainsString('<br', $html);
    $this->assertStringContainsString('data-timeline-type="note"', $html);
    $this->assertStringNotContainsString('<details', $html);
  }

  /**
   * Hostile stored bodies do not survive, legitimate markup does.
   */
  public function testHostileBodies(): void {
    $hostile = [
      '<script>alert(1)</script>',
      '<p onclick="alert(1)">x</p>',
      '<img src=x onerror=alert(1)>',
      '<a href="javascript:alert(1)">x</a>',
      '<a href="JaVaScRiPt:alert(1)">x</a>',
      '<style>body{display:none}</style>',
      '<iframe src="https://evil.example"></iframe>',
      '<a href="https://example.com" onmouseover="alert(1)">x</a>',
    ];
    foreach ($hostile as $body) {
      $html = $this->renderEntry($this->createMail($body));
      foreach (['<script', '<style', '<iframe', '<img', 'onerror', 'onclick', 'onmouseover', 'javascript:'] as $needle) {
        $this->assertStringNotContainsStringIgnoringCase($needle, $html, $body);
      }
    }
    $html = $this->renderEntry($this->createMail('<p>Hallo<br>Welt <strong>fett</strong> <a href="https://example.com/x" rel="noopener noreferrer">Link</a></p>'));
    $this->assertStringContainsString('<p>Hallo<br>Welt <strong>fett</strong> <a href="https://example.com/x" rel="noopener noreferrer">Link</a></p>', $html);
  }

  /**
   * Failed mails are marked.
   */
  public function testFailedMailIsMarked(): void {
    $html = $this->renderEntry($this->createMail('<p>x</p>', 'failed'));
    $this->assertStringContainsString('logbook-timeline-failed', $html);
  }

  /**
   * An unclosed element of a body cannot wrap the following timeline row.
   */
  public function testUnclosedMarkupDoesNotLeak(): void {
    $first = $this->renderEntry($this->createMail('<p>one <a href="https://x.example">y'));
    $second = $this->renderEntry($this->createMail('<p>two</p>'));
    $dom = new \DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8"?><div id="timeline"><div class="row">' . $first . '</div><div class="row">' . $second . '</div></div>', LIBXML_NOERROR);
    $rows = (new \DOMXPath($dom))->query('//div[@class="row"]');
    $this->assertSame(2, $rows->length);
    $this->assertSame(1, (new \DOMXPath($dom))->query('.//a', $rows->item(0))->length);
    $this->assertSame(0, (new \DOMXPath($dom))->query('.//a', $rows->item(1))->length);
    $this->assertStringNotContainsString('<a', substr($second, 0, strlen($second)));
    // The markup of each row is balanced on its own.
    $this->assertSame(substr_count($first, '<a '), substr_count($first, '</a>'));
    $this->assertSame(substr_count($first, '<p>'), substr_count($first, '</p>'));
  }

  /**
   * Links keep only href plus rel; other tags keep no attributes.
   */
  public function testAttributesAreStripped(): void {
    $html = $this->renderEntry($this->createMail('<a href="https://x" class="js-timeline-filter" id="i" target="_blank" style="color:red" data-x="1" onclick="x()">l</a><p class="c" id="d" style="x">p</p><strong class="c">s</strong><ul class="c"><li id="e">li</li></ul>'));
    $this->assertStringContainsString('<a href="https://x" rel="noopener noreferrer">l</a>', $html);
    foreach (['js-timeline-filter', 'target', 'style', 'data-x', 'onclick', 'class="c"', 'id="d"', 'id="e"', 'id="i"'] as $needle) {
      $this->assertStringNotContainsString($needle, $html);
    }
    $this->assertStringContainsString('<p>p</p>', $html);
    $this->assertStringContainsString('<strong>s</strong>', $html);
    $this->assertStringContainsString('<li>li</li>', $html);
  }

}
