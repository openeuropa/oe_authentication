<?php

declare(strict_types = 1);

namespace Drupal\eu_login\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use OpenEuropa\pcas\PCas;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Returns responses for EU Login routes.
 */
class EuLoginController extends ControllerBase {

  /**
   * The request stack.
   *
   * @var Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The pCas variable.
   *
   * @var OpenEuropa\pcas\PCas
   */
  protected $pCas;

  /**
   * The current user.
   *
   * @var Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs the controller object.
   *
   * @param Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param OpenEuropa\pcas\PCas $pCas
   *   The pCas variable.
   * @param Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(RequestStack $requestStack, PCas $pCas, AccountProxyInterface $current_user) {
    $this->requestStack = $requestStack;
    $this->pCas = $pCas;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('pcas'),
      $container->get('current_user')
    );
  }

  /**
   * Logs a user in of the system.
   */
  public function login() {
    // There is no access to this route for authenticated users,
    // Therefore we can directly redirect the user to the EU Login path.
    if ($response = $this->pCas->login()) {
      return $response;
    }
    return new AccessDeniedHttpException();
  }

  /**
   * Logs a user out of the system.
   */
  public function logout() {
    $response = $this->getLogoutRedirect();

    if ($this->currentUser->isAuthenticated()) {
      $this->doDrupalLogout();
    }
    if ($response) {
      return $response;
    }
    return new AccessDeniedHttpException();
  }

  /**
   * Logs a user out from Drupal.
   */
  protected function doDrupalLogout() {
    user_logout();
  }

  /**
   * Get the redirect object to the EU Login logout URL.
   *
   * @return Psr\Http\Message\ResponseInterface
   *   The HTTP redirect object.
   */
  protected function getLogoutRedirect() {
    $query['service'] = \Drupal::url('<front>', [], ['absolute' => TRUE]);
    $logout_url = $this->pCas->logoutUrl($query);
    $http_client = $this->pCas->getHttpClient();
    return $http_client->redirect($logout_url);
  }

}
