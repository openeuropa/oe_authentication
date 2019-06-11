<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication\Event;

use Drupal\cas\Event\CasPostLoginEvent;
use Drupal\cas\Event\CasPostValidateEvent;
use Drupal\cas\Event\CasPreRegisterEvent;
use Drupal\cas\Event\CasPreValidateEvent;
use Drupal\cas\Service\CasHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;
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

  use StringTranslationTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Stores a Messenger object.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructors the EuLoginEventSubscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(ConfigFactoryInterface $configFactory, MessengerInterface $messenger) {
    $this->configFactory = $configFactory;
    $this->messenger = $messenger;
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
    $events[CasHelper::EVENT_PRE_REGISTER] = 'processUserProperties';
    $events[CasHelper::EVENT_POST_VALIDATE] = 'processCasAttributes';
    $events[CasHelper::EVENT_PRE_VALIDATE] = 'alterValidationPath';
    return $events;
  }

  /**
   * Updates the user data based on the information taken from EU Login.
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
   * Adds user properties based on the information taken from EU Login.
   *
   * @param \Drupal\cas\Event\CasPreRegisterEvent $event
   *   The triggered event.
   */
  public function processUserProperties(CasPreRegisterEvent $event): void {

    $attributes = $event->getCasPropertyBag()->getAttributes();
    $event->setPropertyValues(CasProcessor::convertCasAttributesToFieldValues($attributes));

    // If the site is configured to require administrator approval on user
    // registration and OE Authentication is configured to register new users as
    // disabled users, change the status of the account to blocked.
    $user_settings = $this->configFactory->get('user.settings');
    if ($user_settings->get('register') === UserInterface::REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL) {
      $oe_authentication_settings = $this->configFactory->get('oe_authentication.settings');
      if ($oe_authentication_settings->get('block_on_site_admin_approval')) {
        $event->setPropertyValue('status', 0);
        $this->messenger->addStatus($this->t('Thank you for applying for an account. Your account is currently pending approval by the site administrator.'));
      }
    }
  }

  /**
   * Parses the EU Login attributes from the validation response.
   *
   * @param \Drupal\cas\Event\CasPostValidateEvent $event
   *   The triggered event.
   */
  public function processCasAttributes(CasPostValidateEvent $event): void {
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
   * Alters the default CAS validation path to point to the EULogin one.
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
