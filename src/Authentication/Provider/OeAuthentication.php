<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication\Authentication\Provider;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\oe_authentication\PCasFactory;
use Drupal\oe_authentication\UserProvider;
use Symfony\Component\HttpFoundation\Request;

/**
 * Cookie based authentication provider.
 */
class OeAuthentication implements AuthenticationProviderInterface {

  /**
   * The pCas variable.
   *
   * @var \OpenEuropa\pcas\PCas
   */
  protected $pCas;

  /**
   * The user provider variable.
   *
   * @var \Drupal\oe_authentication\UserProvider
   */
  protected $userProvider;

  /**
   * OeAuthentication constructor.
   *
   * @param \OpenEuropa\pcas\PCasFactory $pCasFactory
   *   The pCas variable.
   * @param \Drupal\oe_authentication\UserProvider $userProvider
   *   The user provider variable.
   */
  public function __construct(PCasFactory $pCasFactory, UserProvider $userProvider) {
    $this->pCas = $pCasFactory->getPCas();
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
