<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication\Service;

use Drupal\cas\Service\CasHelper;
use Drupal\cas\Service\CasValidator;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use GuzzleHttp\Client;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class ECasValidator.
 *
 * @todo: Replace this custom class whenever
 *  https://www.drupal.org/project/cas/issues/2997099 gets fixed
 */
class ECasValidator extends CasValidator {

  /**
   * Stores ECAS settings object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $ecasSettings;

  /**
   * Constructor.
   *
   * @param \GuzzleHttp\Client $http_client
   *   The HTTP Client library.
   * @param \Drupal\cas\Service\CasHelper $cas_helper
   *   The CAS Helper service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The URL generator.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The EventDispatcher service.
   */
  public function __construct(Client $http_client, CasHelper $cas_helper, ConfigFactoryInterface $config_factory, UrlGeneratorInterface $url_generator, EventDispatcherInterface $event_dispatcher) {
    $this->ecasSettings = $config_factory->get('oe_authentication.settings');
    parent::__construct($http_client, $cas_helper, $config_factory, $url_generator, $event_dispatcher);
  }

  /**
   * {@inheritdoc}
   */
  public function getServerValidateUrl($ticket, array $service_params = []) {
    $validate_url = $this->casHelper->getServerBaseUrl();
    $path = '';
    switch ($this->settings->get('server.version')) {
      case "1.0":
        $path = 'validate';
        break;

      case "2.0":
        if ($this->settings->get('proxy.can_be_proxied')) {
          $path = 'proxyValidate';
        }
        else {
          // Custom ECAS validation path.
          $path = 'TicketValidationService';
        }
        break;

      case "3.0":
        if ($this->settings->get('proxy.can_be_proxied')) {
          $path = 'p3/proxyValidate';
        }
        else {
          $path = 'p3/serviceValidate';
        }
        break;
    }
    $validate_url .= $path;

    $params = [];
    $params['service'] = $this->urlGenerator->generate('cas.service', $service_params, UrlGeneratorInterface::ABSOLUTE_URL);
    $params['ticket'] = $ticket;
    // We add the necessary ECAS parameters.
    $params['assuranceLevel'] = $this->ecasSettings->get('assurance_level');
    $params['ticketTypes'] = $this->ecasSettings->get('ticket_types');
    if ($this->settings->get('proxy.initialize')) {
      $params['pgtUrl'] = $this->formatProxyCallbackUrl();
    }
    return $validate_url . '?' . UrlHelper::buildQuery($params);
  }

  /**
   * {@inheritdoc}
   */
  private function formatProxyCallbackUrl() {
    return str_replace('http://', 'https://', $this->urlGenerator->generateFromRoute('cas.proxyCallback', [], [
      'absolute' => TRUE,
    ]));
  }

}
