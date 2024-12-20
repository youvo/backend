<?php

namespace Drupal\Tests\projects\Kernel\Resource;

use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for the project submit resource.
 *
 * @coversDefaultClass \Drupal\projects\Plugin\rest\resource\ProjectSubmitResource
 * @group projects
 */
class ProjectSubmitResourceTest extends ProjectResourceTestBase {

  /**
   * Tests the project submit resource.
   *
   * @covers ::create
   * @covers ::routes
   * @covers ::access
   * @covers ::projectAccessCondition
   * @covers ::post
   */
  public function testProjectSubmit(): void {

    $project = $this->createProject();
    $organization = $project->getOwner();

    $request = Request::create('/api/projects/' . $project->uuid() . '/submit', 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $request->headers->set('PHP_AUTH_USER', $organization->getAccountName());
    $request->headers->set('PHP_AUTH_PW', 'password');

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project submitted."', $response->getContent());
  }

}
