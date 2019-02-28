<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication\Event;

use Drupal\cas\Event\CasPostLoginEvent;
use Drupal\cas\Event\CasPostValidateEvent;
use Drupal\cas\Event\CasPreRegisterEvent;
use Drupal\cas\Event\CasPreValidateEvent;
use Drupal\cas\Service\CasHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for CAS module events.
 *
 * The class subscribes to the events provided by the CAS module and makes
 * the required modifications to work with EU Login.
 */
class EuLoginEventSubscriber implements EventSubscriberInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructors the EuLoginEventSubscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * Returns an array of event names this subscriber wants to listen to.
   *
   * @return array
   *   The event names to listen to.
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    $events[CasHelper::EVENT_POST_LOGIN] = 'updateUserData';
    $events[CasHelper::EVENT_PRE_REGISTER] = 'generateUserData';
    $events[CasHelper::EVENT_POST_VALIDATE] = 'processAttributes';
    $events[CasHelper::EVENT_PRE_VALIDATE] = 'alterValidationPath';
    return $events;
  }

  /**
   * Generates the user data based on the information taken from EU Login.
   *
   * @param \Drupal\cas\Event\CasPostLoginEvent $event
   *   The triggered event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   In case of failures an exception is thrown.
   */
  public function updateUserData(CasPostLoginEvent $event): void {
    $properties = $this->generateUserDataArray($event->getCasPropertyBag()->getAttributes());
    $account = $event->getAccount();
    foreach ($properties as $name => $value) {
      $account->set($name, $value);
    }
    $account->save();
  }

  /**
   * Generates the user data based on the information taken from EU Login.
   *
   * @param \Drupal\cas\Event\CasPreRegisterEvent $event
   *   The triggered event.
   */
  public function generateUserData(CasPreRegisterEvent $event): void {
    $attributes = $event->getCasPropertyBag()->getAttributes();
    $event->setPropertyValues($this->generateUserDataArray($attributes));
  }

  /**
   * Generates an array of user fields and values based on the CAS attributes.
   *
   * @param array $attributes
   *   An array of attributes retrieved from CAS.
   *
   * @return array
   *   An array of field names and values.
   */
  private function generateUserDataArray(array $attributes) {
    $properties = [];
    if (!empty($attributes['email'])) {
      $properties['mail'] = $attributes['email'];
    }
    if (!empty($attributes['firstName'])) {
      $properties['field_oe_firstname'] = $attributes['firstName'];
    }
    if (!empty($attributes['lastName'])) {
      $properties['field_oe_lastname'] = $attributes['lastName'];
    }
    if (!empty($attributes['departmentNumber'])) {
      $properties['field_oe_department'] = $attributes['departmentNumber'];
    }
    if (!empty($attributes['domain'])) {
      $properties['field_oe_organisation'] = $attributes['domain'];
    }
    return $properties;
  }

  /**
   * Parses the EU Login attributes from the validation response.
   *
   * @param \Drupal\cas\Event\CasPostValidateEvent $event
   *   The triggered event.
   */
  public function processAttributes(CasPostValidateEvent $event): void {
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
   * Parse the attributes list from the EU Login Server into an array.
   *
   * @param \DOMElement $node
   *   An XML element containing attributes.
   *
   * @return array
   *   An associative array of attributes.
   */
  private function parseAttributes(\DOMElement $node): array {
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

  /**
   * Parses the EU Login attributes from the validation response.
   *
   * @param \Drupal\cas\Event\CasPreValidateEvent $event
   *   The triggered event.
   */
  public function alterValidationPath(CasPreValidateEvent $event): void {
    $config = $this->configFactory->get('oe_authentication.settings');
    $event->setValidationPath($config->get('validation_path'));
    $params = [
      'assuranceLevel' => $config->get('assurance_level'),
      'ticketTypes' => $config->get('ticket_types'),
      'userDetails' => 'true',
    ];
    $event->addParameters($params);
  }

}
