<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_authentication\Kernel;

use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Test login block rendering.
 */
class ProxyCallbackTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'oe_authentication',
    'system',
    'user',
    'cas',
    'externalauth',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'user']);
    $this->installEntitySchema('user');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('cas', ['cas_pgt_storage']);
  }

  /**
   * Test login block rendering.
   */
  public function testProxyCallback(): void {
    /** @var \Symfony\Component\HttpKernel\HttpKernelInterface $kernel */
    $kernel = $this->container->get('http_kernel');
    $proxy_callback_url = Url::fromRoute('cas.proxyCallback')->toString();

    // Check the method.
    $test_request = Request::create($proxy_callback_url, 'GET');
    $response = $kernel->handle($test_request);
    $this->assertEquals(400, $response->getStatusCode());
    $this->assertEquals('The Request should use the POST method', $response->getContent());

    // Check the parameters.
    $test_request = Request::create($proxy_callback_url, 'POST');
    $response = $kernel->handle($test_request);
    $this->assertEquals(400, $response->getStatusCode());
    $this->assertEquals('Missing necessary parameters', $response->getContent());

    // Check a good request.
    $test_request = Request::create($proxy_callback_url, 'POST');
    $test_request->request->set('pgtId', 'foo');
    $test_request->request->set('pgtIou', 'bar');
    $response = $kernel->handle($test_request);
    $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
    $xml = '<?xml version="1.0" encoding="UTF-8"?><proxySuccess xmlns="https://localhost"/>';
    $this->assertEquals($xml, $response->getContent());

    // Check a duplicate.
    $test_request = Request::create($proxy_callback_url, 'POST');
    $test_request->request->set('pgtId', 'foo');
    $test_request->request->set('pgtIou', 'bar');
    $response = $kernel->handle($test_request);
    $this->assertEquals(400, $response->getStatusCode(), $response->getContent());
    $this->assertEquals('Parameters already in use', $response->getContent());
  }

}
