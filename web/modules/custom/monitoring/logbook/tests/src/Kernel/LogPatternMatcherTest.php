<?php

namespace Drupal\Tests\logbook\Kernel;

/**
 * Tests which log patterns the logbook lists.
 *
 * @group logbook
 */
class LogPatternMatcherTest extends LogbookKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createPattern('project_apply', ['observable' => TRUE]);
    $this->createPattern('project_publish', ['observable' => TRUE, 'public' => TRUE, 'promote' => TRUE]);
    $this->createPattern('project_mediate', ['observable' => TRUE, 'public' => TRUE]);
    $this->createPattern('project_notify', ['observable' => FALSE]);
    $this->createPattern('organization_create', ['observable' => TRUE, 'hidden' => TRUE]);
    $this->createPattern('feedback_complete', ['observable' => TRUE, 'status' => FALSE]);
    $this->createPattern('feedback_other', ['observable' => TRUE]);
  }

  /**
   * Only enabled and observable patterns are in scope.
   */
  public function testScope(): void {
    $ids = $this->container->get('logbook.pattern_matcher')->scopeIds();
    sort($ids);
    $this->assertSame(['feedback_other', 'organization_create', 'project_apply', 'project_mediate', 'project_publish'], $ids);
  }

  /**
   * Hidden patterns are not visible by default.
   */
  public function testVisibleIdsExcludeHidden(): void {
    $ids = $this->container->get('logbook.pattern_matcher')->visibleIds();
    $this->assertNotContains('organization_create', $ids);
    $this->assertContains('project_apply', $ids);
  }

  /**
   * Patterns are grouped by the explicit map, the rest is other.
   */
  public function testGroups(): void {
    $matcher = $this->container->get('logbook.pattern_matcher');
    $this->assertSame(['project_publish'], $matcher->groupIds('lifecycle'));
    $creatives = $matcher->groupIds('creatives');
    sort($creatives);
    $this->assertSame(['project_apply', 'project_mediate', 'project_notify'], $creatives);
    $this->assertSame(['organization_create'], $matcher->groupIds('organizations'));
    $other = $matcher->groupIds('other');
    sort($other);
    $this->assertSame(['feedback_complete', 'feedback_other'], $other);
    $this->assertSame([], $matcher->groupIds('bogus'));
  }

  /**
   * Visibility: internal is not public, public includes promoted.
   */
  public function testVisibility(): void {
    $matcher = $this->container->get('logbook.pattern_matcher');
    $public = $matcher->visibilityIds('public');
    sort($public);
    $this->assertSame(['project_mediate', 'project_publish'], $public);
    $this->assertSame(['project_publish'], $matcher->visibilityIds('promoted'));
    $this->assertContains('project_apply', $matcher->visibilityIds('internal'));
    $this->assertNotContains('project_publish', $matcher->visibilityIds('internal'));
    $this->assertSame([], $matcher->visibilityIds('bogus'));
  }

  /**
   * The option lists are keyed by the values the filters accept.
   */
  public function testOptions(): void {
    $matcher = $this->container->get('logbook.pattern_matcher');
    $this->assertSame(['lifecycle', 'creatives', 'organizations', 'other'], array_keys($matcher->groupOptions()));
    $this->assertSame(['internal', 'public', 'promoted'], array_keys($matcher->visibilityOptions()));
  }

}
