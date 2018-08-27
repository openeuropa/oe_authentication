<?php

declare(strict_types = 1);


namespace Drupal\Tests\oe_authentication\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\oe_authentication\PCasFactory;

/**
 * Class InstallationTest.
 */
class PCasFactoryTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'oe_authentication',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig([
      'oe_authentication',
    ]);
  }

  /**
   * Test default configuration options for the PCasFactory class.
   */
  public function testDefaultConfiguration(): void {
    $pcasfactory = new PCasFactory($this->container->get('session'), $this->container->get('config.factory'));
    $pcas = $pcasfactory->getPCas();
    $properties = $pcas->getProperties();
    $this->assertEquals('http://authentication:8001', $properties['base_url']);
    $login_protocol = [
      'path' => '/login',
      'query' => [],
      'allowed_parameters' => ['service', 'renew', 'gateway'],
    ];
    $this->assertEquals($login_protocol, $properties['protocol']['login']);
  }

  /**
   * Test custom configuration options for the PCasFactory class.
   */
  public function testCustomConfiguration(): void {
    $this->config("oe_authentication.settings")
      ->set('base_url', 'https://ecas.ec.europa.eu/cas')
      ->save(TRUE);
    $protocols = [
      'login' => [
        'path' => '/login',
        'query' => [],
        'allowed_parameters' => ['service', 'renew', 'gateway'],
      ],
    ];
    $pcasfactory = new PCasFactory($this->container->get('session'), $this->container->get('config.factory'), $protocols);
    $pcas = $pcasfactory->getPCas();
    $properties = $pcas->getProperties();
    $this->assertEquals('https://ecas.ec.europa.eu/cas', $properties['base_url']);
    $this->assertEquals($protocols, $properties['protocol']);
  }

}
