<?php

declare(strict_types=1);

namespace Drupal\oe_authentication_eulogin_mock\EventSubscriber;

use Drupal\cas\Event\CasPreRedirectEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Cas pre redirect event subscriber.
 */
class CasPreRedirectSubscriber implements EventSubscriberInterface {

  /**
   * Cas pre redirect event handler.
   *
   * By default, "force login" redirect is not cacheable which introduces
   * a problem with invalidating redirects for varnish. Here we will make
   * redirect cacheable.
   *
   * @param \Drupal\cas\Event\CasPreRedirectEvent $event
   *   Cas pre redirect event.
   */
  public function onCasPreRedirect(CasPreRedirectEvent $event) {
    $event->getCasRedirectData()->setIsCacheable(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      CasPreRedirectEvent::class => ['onCasPreRedirect'],
    ];
  }

}
