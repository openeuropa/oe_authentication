<?php

declare(strict_types=1);

namespace Drupal\oe_authentication\Event;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\cas\Event\CasPostValidateEvent;
use Drupal\cas\Event\CasPreRedirectEvent;
use Drupal\cas\Event\CasPreRegisterEvent;
use Drupal\cas\Event\CasPreValidateEvent;
use Drupal\oe_authentication\CasProcessor;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

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
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  public function __construct(ConfigFactoryInterface $configFactory, ?RequestStack $requestStack = NULL) {
    $this->configFactory = $configFactory;
    if ($requestStack === NULL) {
      // phpcs:ignore Drupal.Semantics.FunctionTriggerError.TriggerErrorTextLayoutRelaxed
      @trigger_error('Calling ' . __METHOD__ . '() without the $requestStack argument is deprecated in oe_authentication:1.x and will be required in oe_authentication:2.x.', E_USER_DEPRECATED);
      $requestStack = \Drupal::requestStack();
    }
    $this->requestStack = $requestStack;
  }

  /**
   * Returns an array of event names this subscriber wants to listen to.
   *
   * @return array
   *   The event names to listen to.
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    $events[CasPreRegisterEvent::class] = [
      ['checkUserMailExists', 1000],
      ['processUserProperties'],
    ];
    $events[CasPreRedirectEvent::class] = 'forceTwoFactorAuthentication';
    $events[CasPostValidateEvent::class] = 'processCasAttributes';
    $events[CasPreValidateEvent::class] = 'alterValidationPath';

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
   * Ensures that 2-factor authentication is forced if it is configured.
   *
   * @param \Drupal\cas\Event\CasPreRedirectEvent $event
   *   The triggered event.
   */
  public function forceTwoFactorAuthentication(CasPreRedirectEvent $event): void {
    $config_forced_2fa = $this->configFactory->get('oe_authentication.settings')->get('force_2fa');
    $request_forced_2fa = $this->requestStack->getCurrentRequest()->query->has('force_2fa');

    if ($config_forced_2fa || $request_forced_2fa) {
      $data = $event->getCasRedirectData();
      $data->setParameter('authenticationLevel', 'MEDIUM');

      // Add a parameter to the service URL to add 2FA during ticket validation.
      if ($request_forced_2fa) {
        $data->setServiceParameter('force_2fa', 1);
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
      'groups' => '*',
    ];

    $service_url = UrlHelper::parse($event->getParameters()['service']);
    // Add 2FA validation is 2FA is always required via config, or if it has
    // been required for this request.
    // @see ::forceTwoFactorAuthentication()
    if ($config->get('force_2fa') || isset($service_url['query']['force_2fa'])) {
      $params['authenticationLevel'] = 'MEDIUM';
    }
    $event->addParameters($params);
  }

}
