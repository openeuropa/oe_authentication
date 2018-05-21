<?php

namespace Drupal\eu_login\Routing;

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
    if ($route = $collection->get('user.login')) {
      $defaults = $route->getDefaults();
      unset($defaults['_form']);
      $defaults['_controller'] = '\Drupal\eu_login\Controller\EuLoginController::login';
      $route->setDefaults($defaults);
      $req = $route->getRequirements();
      $route->setRequirements($req);
    }

    if ($route = $collection->get('user.logout')) {
      $route->setDefault('_controller', '\Drupal\eu_login\Controller\EuLoginController::logout');
    }

    if ($route = $collection->get('user.login.http')) {
      $collection->remove('user.login.http');
    }
  }

}
