<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication_eulogin_mock\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters the CAS mock server routes to comply to EU login ones.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * RouteSubscriber constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('cas_mock_server.validate')) {
      $config = $this->configFactory->get('oe_authentication.settings');
      $route->setPath('/cas-mock-server/' . trim($config->get('validation_path'), '/'));
    }
    if ($route = $collection->get('oe_authentication_eulogin_mock.register')) {
      // Adapt route path to current configuration.
      $config = $this->configFactory->get('oe_authentication.settings');
      $route->setPath('/cas-mock-server/' . trim($config->get('register_path'), '/'));
    }
  }

}
