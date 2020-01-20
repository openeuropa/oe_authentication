<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    // Restrict Drupal login to Drupal only users.
    $internal_routes = [
      'user.pass',
      'user.pass.http',
      'user.login.http',
      'user.logout.http',
    ];
    foreach ($internal_routes as $internal_route) {
      if (($route = $collection->get($internal_route)) === NULL) {
        continue;
      }
      $route->setRequirement('_cas_user_access', 'TRUE');

    }

    // Replace the core register route controller.
    $route = $collection->get('user.register');
    if ($route instanceof Route) {
      $defaults = $route->getDefaults();
      unset($defaults['_form']);
      $defaults['_controller'] = '\Drupal\oe_authentication\Controller\RegisterController::register';
      $route->setDefaults($defaults);
    }

    // Replace the cas callback route controller.
    if ($route = $collection->get('cas.proxyCallback')) {
      $route->setDefaults([
        '_controller' => '\Drupal\oe_authentication\Controller\ProxyCallbackController::callback',
      ]);
    }

    // Replace default cas login route with eulogin one.
    if ($route = $collection->get('cas.login')) {
      $route->setPath('/eulogin');
    }

    // Replace route title for the Bulk Add CAS Users route.
    if ($route = $collection->get('cas.bulk_add_cas_users')) {
      $route->setDefault('_title', 'Bulk Add EU Login Users');
    }
  }

}
