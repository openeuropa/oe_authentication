<?php

declare(strict_types = 1);

namespace Drupal\oe_auth\Authentication\Provider;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\oe_auth\UserProvider;
use OpenEuropa\pcas\PCas;
use Symfony\Component\HttpFoundation\Request;

/**
 * Cookie based authentication provider.
 */
class OeAuth implements AuthenticationProviderInterface {

  /**
   * The pCas variable.
   *
   * @var OpenEuropa\pcas\PCas
   */
  protected $pCas;

  /**
   * The user provider variable.
   *
   * @var Drupal\oe_auth\UserProvider
   */
  protected $userProvider;

  /**
   * OeAuth constructor.
   *
   * @param OpenEuropa\pcas\PCas $pCas
   *   The pCas variable.
   * @param Drupal\oe_auth\UserProvider $userProvider
   *   The user provider variable.
   */
  public function __construct(PCas $pCas, UserProvider $userProvider) {
    $this->pCas = $pCas;
    $this->userProvider = $userProvider;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    return $this->pCas->isAuthenticated();
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request) {
    if (!$this->pCas->isAuthenticated()) {
      return NULL;
    }
    $pCasUser = $this->pCas->getAuthenticatedUser();
    return $this->userProvider->loadAccount($pCasUser);
  }

}
