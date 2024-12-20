<?php

declare(strict_types=1);

namespace Drupal\Tests\youvo\Traits;

use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\AssertContentTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Provides trait for tests with requests.
 */
trait RequestTrait {

  use AssertContentTrait;

  /**
   * Passes a request to the HTTP kernel and returns a response.
   *
   * @throws \Exception
   */
  protected function doRequest(Request $request): Response {

    $http_kernel = $this->container->get('http_kernel');
    self::assertInstanceOf(HttpKernelInterface::class, $http_kernel);

    $response = $http_kernel->handle($request);

    $content = $response->getContent();
    self::assertNotFalse($content);
    $this->setRawContent($content);

    return $response;
  }

  /**
   * Authenticates a request with basic auth.
   */
  protected function authenticateRequest(Request $request, AccountInterface $account): void {
    $request->headers->set('PHP_AUTH_USER', $account->getEmail());
    $request->headers->set('PHP_AUTH_PW', 'password');
  }

}
