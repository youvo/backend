<?php

namespace Drupal\Tests\logbook\Unit;

use Drupal\Core\Routing\RouteMatch;
use Drupal\logbook\Hook\LogbookPageHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;

/**
 * Tests the page hooks of the logbook.
 */
#[CoversClass(LogbookPageHooks::class)]
#[Group('logbook')]
class LogbookPageHooksTest extends UnitTestCase {

  /**
   * The page title of the logbook is read out, but not shown.
   */
  public function testPageTitleIsVisuallyHidden(): void {
    $variables = ['title_attributes' => []];
    (new LogbookPageHooks(new RouteMatch('view.logbook.page_1', new Route('/logbook'))))->preprocessPageTitle($variables);
    $this->assertContains('visually-hidden', $variables['title_attributes']['class'] ?? []);
    $variables = ['title_attributes' => []];
    (new LogbookPageHooks(new RouteMatch('system.admin', new Route('/admin'))))->preprocessPageTitle($variables);
    $this->assertNotContains('visually-hidden', $variables['title_attributes']['class'] ?? []);
  }

}
