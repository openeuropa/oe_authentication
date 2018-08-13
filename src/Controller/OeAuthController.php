<?php

declare(strict_types = 1);

namespace Drupal\oe_auth\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use OpenEuropa\pcas\PCas;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Returns responses for OE Auth routes.
 */
class OeAuthController extends ControllerBase {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The pCas variable.
   *
   * @var \OpenEuropa\pcas\PCas
   */
  protected $pCas;

  /**
   * Constructs the controller object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \OpenEuropa\pcas\PCas $pcas
   *   The pCas variable.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(RequestStack $requestStack, PCas $pcas, AccountProxyInterface $current_user) {
    $this->requestStack = $requestStack;
    $this->pCas = $pcas;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('oe_auth.pcas.factory')->getPCas(),
      $container->get('current_user')
    );
  }

  /**
   * Logs a user in of the system.
   *
   * @throws \Exception
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   */
  public function login() {
    // There is no access to this route for authenticated users,
    // Therefore we can directly redirect the user to the OE Auth path.
    if ($response = $this->pCas->login()) {
      return $response;
    }

    throw new AccessDeniedHttpException();
  }

  /**
   * Logs a user out of the system.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   */
  public function logout() {
    if ($this->currentUser->isAuthenticated()) {
      $this->doDrupalLogout();
    }

    $query = [
      'service' => \Drupal::url('<front>', [], ['absolute' => TRUE]),
    ];

    if ($response = $this->getLogoutRedirect($query)) {
      return $response;
    }

    throw new AccessDeniedHttpException();
  }

  /**
   * Logs a user out from Drupal.
   */
  protected function doDrupalLogout() {
    user_logout();
  }

  /**
   * Get the redirect object to the OE Auth logout URL.
   *
   * @param string[] $query
   *   The query strings if any.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The HTTP redirect object.
   */
  protected function getLogoutRedirect(array $query = []) {
    return $this->pCas->getHttpClient()->redirect(
      $this->pCas->logoutUrl($query)
    );
  }

}
