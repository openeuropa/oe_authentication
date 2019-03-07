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
use Drupal\oe_authentication\CasProcessor;

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
    $properties = CasProcessor::convertCasAttributesToFieldValues($event->getCasPropertyBag()->getAttributes());
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
    $event->setPropertyValues(CasProcessor::convertCasAttributesToFieldValues($attributes));
  }

  /**
   * Parses the EU Login attributes from the validation response.
   *
   * @param \Drupal\cas\Event\CasPostValidateEvent $event
   *   The triggered event.
   */
  public function processAttributes(CasPostValidateEvent $event): void {
    $property_bag = $event->getCasPropertyBag();
    $response = $event->getResponseData();
    if (CasProcessor::isValidResponse($response)) {
      $eulogin_attributes = CasProcessor::processValidationResponseAttributes($response);
      foreach ($eulogin_attributes as $key => $value) {
        $property_bag->setAttribute($key, $value);
      }
    }
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
