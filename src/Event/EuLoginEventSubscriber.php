<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication\Event;

use Drupal\cas\Event\CasPostValidateEvent;
use Drupal\cas\Event\CasPreRegisterEvent;
use Drupal\cas\Event\CasPreValidateEvent;
use Drupal\cas\Service\CasHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
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
   */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('messenger')
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
    $events[CasHelper::EVENT_PRE_REGISTER] = [
      ['checkUserMailExists', 1000],
      ['processUserProperties'],
    ];
    $events[CasHelper::EVENT_POST_VALIDATE] = 'processCasAttributes';
    $events[CasHelper::EVENT_PRE_VALIDATE] = 'alterValidationPath';
    return $events;
  }

  /**
   * Checks user email exists previously.
   *
   * @param \Drupal\cas\Event\CasPreRegisterEvent $event
   *   The triggered event.
   */
  public function checkUserMailExists(CasPreRegisterEvent $event): void {
    $cas_settings = $this->configFactory->get('cas.settings');
    if ($cas_settings->get('user_accounts.auto_register')) {
      $email = $event->getCasPropertyBag()->getAttribute('email');

      if (user_load_by_mail($email)) {
        $event->cancelAutomaticRegistration($this->t('A user with this email address already exists. Please contact the site administrator.'));
        $event->stopPropagation();
      }
    }
  }

  /**
   * Adds user properties based on the information taken from EU Login.
   *
   * @param \Drupal\cas\Event\CasPreRegisterEvent $event
   *   The triggered event.
   */
  public function processUserProperties(CasPreRegisterEvent $event): void {
    // If the site is configured to need administrator approval,
    // change the status of the account to blocked.
    $user_settings = $this->configFactory->get('user.settings');
    if ($user_settings->get('register') === UserInterface::REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL) {
      $event->setPropertyValue('status', 0);
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
      'groups' => '*',
    ];
    $event->addParameters($params);
  }

}
