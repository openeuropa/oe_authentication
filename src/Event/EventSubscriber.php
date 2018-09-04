<?php

declare(strict_types = 1);


namespace Drupal\oe_authentication\Event;

use Drupal\cas\Event\CasPreRegisterEvent;
use Drupal\cas\Exception\CasLoginException;
use Drupal\cas\Service\CasHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class EventSubscriber.
 *
 * @package Drupal\oe_authentication\Event
 */
class EventSubscriber implements EventSubscriberInterface {

  /**
   * Returns an array of event names this subscriber wants to listen to.
   *
   * @return array
   *   The event names to listen to
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[CasHelper::EVENT_PRE_REGISTER] = 'generateEmail';
    return $events;
  }

  /**
   * Generates the user email based on the information taken from ECAS.
   *
   * @param \Drupal\cas\Event\CasPreRegisterEvent $event
   *   The triggered event.
   *
   * @throws \Drupal\cas\Exception\CasLoginException
   */
  public function generateEmail(CasPreRegisterEvent $event) {
    $attributes = $event->getCasPropertyBag()->getAttributes();
    if (!empty($attributes['mail'])) {
      $event->setPropertyValue('mail', $attributes['mail']);
    }

    if (!empty($attributes['authenticationFactors'])) {
      $authFactors = $attributes['authenticationFactors'];
      if (isset($authFactors['moniker'])) {
        $event->setPropertyValue('mail', $authFactors['moniker']);
      }
    }
    if (empty($event->getPropertyValues()['mail'])) {
      throw new CasLoginException('Empty data found for CAS email attribute.');
    }
  }

}
