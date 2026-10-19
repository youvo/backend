<?php

namespace Drupal\Tests\mailer\Kernel;

use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RenderContext;
use Drupal\mailer\Mailer\ProjectTransitionMailerInterface;
use Drupal\projects\ProjectState;

/**
 * Tests the compiled MJML wrapper and that it is applied to mailer mails.
 *
 * @group mailer
 */
class EmailWrapTemplateTest extends MailerKernelTestBase {

  /**
   * Returns the path of the compiled wrapper template.
   */
  protected function templatePath(): string {
    return DRUPAL_ROOT . '/themes/custom/yv/templates/email-wrap--mailer.html.twig';
  }

  /**
   * The compiled template keeps the body slot, doctype and html branch.
   */
  public function testCompiledTemplate(): void {
    $this->assertFileExists($this->templatePath());
    $source = file_get_contents($this->templatePath());
    $this->assertStringContainsString('{% if is_html %}', $source);
    $this->assertStringContainsString('{{ body }}', $source);
    $this->assertStringContainsString('<!doctype html>', strtolower($source));
    $this->assertStringNotContainsString('{{ body|raw }}', $source);
    $this->assertStringContainsString('{% else %}', $source);
  }

  /**
   * The yv wrapper is rendered around the policy body of a real mail.
   */
  public function testWrapperAppliedToMail(): void {
    $this->container->get('theme_installer')->install(['gin', 'yv']);
    $this->config('system.theme')->set('default', 'yv')->save();

    $project = $this->createProject(ProjectState::Open);
    $organization = $project->getOwner();
    $organization->set('mail', 'organization@example.com')->save();
    $organization->getManager()->set('mail', 'manager@example.com')->save();
    $project->setTitle('Wrapper check')->save();

    $mailer = $this->container->get(ProjectTransitionMailerInterface::class);
    $this->captureMails($mailer);
    $this->assertTrue($mailer->publish($project));
    $this->assertCount(1, $this->capturedMails);

    $html = $this->capturedMails[0]['html'];
    $this->assertStringContainsString('<!doctype html>', strtolower($html));
    $this->assertStringContainsString('mj-column', $html);
    $this->assertStringContainsString('alt="youvo', $html);
    $this->assertMatchesRegularExpression('#src="https?://[^"]+/assets/logo_email\.png"#', $html);
    $this->assertStringContainsString('lang="de"', $html);
    $this->assertStringContainsString('Wrapper check', $html);
    // The policy text also appears after the header, inside the wrapper body.
    $header = strpos($html, 'alt="youvo');
    $this->assertNotFalse(strpos($html, 'Wrapper check', $header));
    // The hidden preview text skips the greeting and names the project.
    $this->assertMatchesRegularExpression('#<div style="display:none[^"]*">([^<]*)</div>#', $html);
    preg_match('#<div style="display:none[^"]*">([^<]*)</div>#', $html, $matches);
    $this->assertStringContainsString('Wrapper check', $matches[1]);
    $this->assertStringStartsNotWith('Hallo', $matches[1]);
  }

  /**
   * The recorded body is the policy body without the wrapper.
   *
   * The sent mail carries the full wrapped document.
   */
  public function testRecordedBodyHasNoWrapper(): void {
    $this->container->get('theme_installer')->install(['gin', 'yv']);
    $this->config('system.theme')->set('default', 'yv')->save();

    $project = $this->createMailableProject();
    $project->setTitle('Wrapper check')->save();

    $mailer = $this->container->get(ProjectTransitionMailerInterface::class);
    $this->captureMails($mailer);
    $this->assertTrue($mailer->publish($project));

    $sent = strtolower($this->capturedMails[0]['html']);
    $this->assertStringContainsString('<!doctype html>', $sent);
    $this->assertStringContainsString('mj-column', $sent);

    $entries = $this->loadEntries($project->id());
    $this->assertCount(1, $entries);
    $body = (string) reset($entries)->get('body')->value;
    $this->assertStringContainsString('Wrapper check', $body);
    $this->assertStringNotContainsString('<!doctype', strtolower($body));
    $this->assertStringNotContainsString('<html', strtolower($body));
    $this->assertStringNotContainsString('<style', strtolower($body));
    $this->assertStringNotContainsString('mj-column', $body);
  }

  /**
   * The plain-text branch outputs only the body.
   */
  public function testPlainBranch(): void {
    $this->container->get('theme_installer')->install(['gin', 'yv']);
    $source = file_get_contents($this->templatePath());
    $twig = $this->container->get('twig');
    $template = $twig->createTemplate($source);
    $renderer = $this->container->get('renderer');
    $output = $renderer->executeInRenderContext(new RenderContext(), fn () => $template->render([
      'is_html' => FALSE,
      'body' => 'Plain body',
      'subject' => 'Subject',
    ]));
    $this->assertSame('Plain body', trim($output));
    $html = $renderer->executeInRenderContext(new RenderContext(), fn () => $template->render([
      'is_html' => TRUE,
      'body' => Markup::create('<p>Html body</p>'),
      'subject' => 'Subject',
    ]));
    $this->assertStringContainsString('<p>Html body</p>', $html);
  }

}
