<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication\Controller;

use Drupal\cas\Service\CasHelper;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\RedirectResponse;

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
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(CasHelper $cas_helper = NULL, AccountProxyInterface $current_user = NULL) {
    $this->casHelper = $cas_helper;
    $this->currentUser = $current_user;
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

    $query = [
      'service' => \Drupal::url('<front>', [], ['absolute' => TRUE]),
    ];

    $config = \Drupal::configFactory();

    $auth_config = $config->get('oe_authentication.settings');

    $url = $auth_config->get('register_path');

    if ($response = new RedirectResponse($url)) {
      return $response;
    }

    throw new AccessDeniedHttpException();
  }

}
