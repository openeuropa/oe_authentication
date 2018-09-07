<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Replace the core register route.
    if ($route = $collection->get('user.register')) {
      $defaults = $route->getDefaults();
      unset($defaults['_form']);
      $defaults['_controller'] = '\Drupal\oe_authentication\Controller\AuthenticationController::register';
      $route->setDefaults($defaults);
    }

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
  }

}
