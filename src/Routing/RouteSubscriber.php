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
  protected function alterRoutes(RouteCollection $collection) {
    // Remove these routes as to generate fatal errors wherever
    // functionality is missing.
    // @see user.routing.yml for original definition.
    $routes_to_remove = [
      'user.admin_create',
      'user.multiple_cancel_confirm',
      'user.pass',
      'user.pass.http',
      'user.login.http',
      'user.logout.http',
      'user.cancel_confirm',
    ];
    foreach ($routes_to_remove as $route_to_remove) {
      if ($route = $collection->get($route_to_remove)) {
        $route->setRequirement('_access', 'FALSE');
      }
    }

    // Replace the core register route controller.
    $route = $collection->get('user.register');
    if ($route instanceof Route) {
      $defaults = $route->getDefaults();
      unset($defaults['_form']);
      $defaults['_controller'] = '\Drupal\oe_authentication\Controller\RegisterController::register';
      $route->setDefaults($defaults);
    }
  }

}
