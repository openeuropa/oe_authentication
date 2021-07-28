<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication_eulogin_mock\EventSubscriber;

use Drupal\cas_mock_server\Event\CasMockServerEvents;
use Drupal\cas_mock_server\Event\CasMockServerResponseAlterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber to CAS mock server events.
 *
 * Event subscriber initially used in Joinup project.
 *
 * @see https://github.com/ec-europa/joinup-dev/blob/develop/web/modules/custom/joinup_cas_mock_server/src/EventSubscriber/JoinupCasMockServerSubscriber.php
 */
class CasMockServerSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      CasMockServerEvents::RESPONSE_ALTER => 'alterResponse',
    ];
  }

  /**
   * Alters the CAS mock server XML DOM to provide an EU Login style response.
   *
   * The EU Login server CAS response doesn't store the attributes under the
   * <cas:attributes/> node. Instead the attributes are stored one level up,
   * populating the <cas:authenticationSuccess/> node.
   *
   * @param \Drupal\cas_mock_server\Event\CasMockServerResponseAlterEvent $event
   *   The event object.
   */
  public function alterResponse(CasMockServerResponseAlterEvent $event): void {
    $dom = $event->getDom();

    $authentication_success = $dom->getElementsByTagName('cas:authenticationSuccess')->item(0);

    // Remove <cas:attributes/>.
    $attributes = $dom->getElementsByTagName('cas:attributes')->item(0);
    $authentication_success->removeChild($attributes);

    // Add the attributes one level up.
    foreach ($event->getUserData() as $key => $value) {
      $attribute = $dom->createElement("cas:$key");
      // If the response has a "groups" key, each group needs to be in an
      // individual element so it is processed as an array down the line.
      if ($key === 'groups') {
        $groups = array_map('trim', explode(',', $value));
        $attribute->setAttribute('number', (string) count($groups));
        foreach ($groups as $group) {
          $group_node = $dom->createElement('cas:group');
          $group_node->textContent = $group;
          $attribute->appendChild($group_node);
        }
      }
      else {
        $attribute->textContent = $value;
      }
      $authentication_success->appendChild($attribute);
    }
  }

}
