<?php

declare(strict_types=1);

namespace Drupal\oe_authentication_user_fields\EventSubscriber;

use Drupal\cas\Event\CasPostLoginEvent;
use Drupal\cas\Event\CasPreRegisterEvent;
use Drupal\oe_authentication_user_fields\EuLoginAttributesHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Copies the EU Login attributes to user fields.
 */
class EuLoginAttributesToUserFieldsSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      CasPostLoginEvent::class => 'updateUserData',
      CasPreRegisterEvent::class => 'processUserProperties',
    ];
  }

  /**
   * Updates the user data based on the information taken from EU Login.
   *
   * @param \Drupal\cas\Event\CasPostLoginEvent $event
   *   The triggered event.
   */
  public function updateUserData(CasPostLoginEvent $event): void {
    $properties = EuLoginAttributesHelper::convertEuLoginAttributesToFieldValues($event->getCasPropertyBag()->getAttributes());
    $account = $event->getAccount();
    foreach ($properties as $name => $value) {
      if (is_array($value)) {
        $value = array_values($value);
      }
      $account->set($name, $value);
    }
    $account->save();
  }

  /**
   * Adds user properties based on the information taken from EU Login.
   *
   * @param \Drupal\cas\Event\CasPreRegisterEvent $event
   *   The triggered event.
   */
  public function processUserProperties(CasPreRegisterEvent $event): void {
    $attributes = $event->getCasPropertyBag()->getAttributes();
    $event->setPropertyValues(EuLoginAttributesHelper::convertEuLoginAttributesToFieldValues($attributes));
  }

}
