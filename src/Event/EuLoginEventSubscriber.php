<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication\Event;

use Drupal\cas\Event\CasAfterValidateEvent;
use Drupal\cas\Event\CasPreRegisterEvent;
use Drupal\cas\Service\CasHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for CAS module events.
 *
 * The class subscribes to the events provided by the CAS module and makes
 * the required modifications to work with EuLogin.
 */
class EuLoginEventSubscriber implements EventSubscriberInterface {

  /**
   * Returns an array of event names this subscriber wants to listen to.
   *
   * @return array
   *   The event names to listen to.
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[CasHelper::EVENT_PRE_REGISTER] = 'generateEmail';
    $events[CasHelper::EVENT_AFTER_VALIDATE] = 'processAttributes';
    return $events;
  }

  /**
   * Generates the user email based on the information taken from EuLogin.
   *
   * @param \Drupal\cas\Event\CasPreRegisterEvent $event
   *   The triggered event.
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
  }

  /**
   * Parses the EuLogin attributes from the validation response.
   *
   * @param \Drupal\cas\Event\CasAfterValidateEvent $event
   *   The triggered event.
   */
  public function processAttributes(CasAfterValidateEvent $event) {
    $data = $event->getResponseData();
    $property_bag = $event->getCasPropertyBag();
    $dom = new \DOMDocument();
    $dom->preserveWhiteSpace = FALSE;
    $dom->encoding = "utf-8";

    // Suppress errors from this function, as we intend to allow other
    // event subscribers to work on the data.
    if (@$dom->loadXML($data) === FALSE) {
      return;
    }

    $success_elements = $dom->getElementsByTagName("authenticationSuccess");
    if ($success_elements->length === 0) {
      return;
    }

    // There should only be one success element, grab it and extract username.
    $success_element = $success_elements->item(0);
    // Parse the attributes coming from Eu Login
    // and add them to the default ones.
    $eulogin_attributes = $this->parseAttributes($success_element);
    foreach ($eulogin_attributes as $key => $value) {
      $property_bag->setAttribute($key, $value);
    }
  }

  /**
   * Parse the attributes list from the EuLogin Server into an array.
   *
   * @param \DOMElement $node
   *   An XML element containing attributes.
   *
   * @return array
   *   An associative array of attributes.
   */
  private function parseAttributes(\DOMElement $node) {
    $attributes = [];
    // @var \DOMElement $child
    foreach ($node->childNodes as $child) {
      $name = $child->localName;
      if ($child->hasAttribute('number')) {
        $value = $this->parseAttributes($child);
      }
      else {
        $value = $child->nodeValue;
      }
      $attributes[$name] = $value;
    }
    return $attributes;
  }

}
