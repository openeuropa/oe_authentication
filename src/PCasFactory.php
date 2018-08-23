<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication;

use Drupal\Core\Config\ConfigFactory;
use OpenEuropa\pcas\PCasFactory as DefaultFactory;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Custom PCasFactory based on Drupal configuration.
 */
class PCasFactory extends DefaultFactory {

  /**
   * PCasFactory constructor.
   *
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   Session Interface.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   Drupal configuration factory.
   * @param array $protocol
   *   Array of pcas protocols.
   *
   * @throws \Exception
   */
  public function __construct(SessionInterface $session, ConfigFactory $configFactory, array $protocol = []) {
    $config = $configFactory->get('oe_authentication.settings');
    parent::__construct($session, (string) $config->get('base_url'), $protocol);
  }

}
