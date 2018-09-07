<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication\Controller;

use Drupal\cas\Service\CasHelper;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Returns responses for OE Authentication routes.
 */
class AuthenticationController extends ControllerBase {

  /**
   * CAS Helper object.
   *
   * @var \Drupal\cas\Service\CasHelper
   */
  protected $casHelper;

  /**
   * Constructs the controller object.
   *
   * @param \Drupal\cas\Service\CasHelper $cas_helper
   *   The CAS Helper service.
   */
  public function __construct(CasHelper $cas_helper) {
    $this->casHelper = $cas_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cas.helper')
    );
  }

  /**
   * Register a user with Cas.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   */
  public function register() {
    if ($this->currentUser()->isAuthenticated()) {
      throw new AccessDeniedHttpException();
    }

    $url = $this->getRegisterUrl();

    if ($response = new TrustedRedirectResponse($url)) {
      return $response;
    }

    throw new AccessDeniedHttpException();
  }

  /**
   * Get the register URL.
   *
   * @return string
   *   The register URL.
   */
  public function getRegisterUrl() {

    $config = \Drupal::configFactory()->get('oe_authentication.settings');

    $url = $this->casHelper->getServerBaseUrl()
      . $config->get('register_path')
      . '?service=' . \Drupal::url('<front>', [], ['absolute' => TRUE]);

    return $url;
  }

}
