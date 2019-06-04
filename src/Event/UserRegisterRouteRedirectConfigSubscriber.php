<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication\Event;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Routing\RouteBuilderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Acts when oe_authentication.settings:redirect_user_register_route changes.
 */
class UserRegisterRouteRedirectConfigSubscriber implements EventSubscriberInterface {

  /**
   * The route builder service.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routeBuilder;

  /**
   * Constructs a new event subscriber instance.
   *
   * @param \Drupal\Core\Routing\RouteBuilderInterface $route_builder
   *   The route builder service.
   */
  public function __construct(RouteBuilderInterface $route_builder) {
    $this->routeBuilder = $route_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::SAVE => 'onConfigSave',
    ];
  }

  /**
   * Acts when oe_authentication.settings:redirect_user_register_route changes.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The config CRUD event.
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    if ($event->getConfig()->getName() === 'oe_authentication.settings') {
      if ($event->isChanged('redirect_user_register_route')) {
        $this->routeBuilder->rebuild();
      }
    }
  }

}
