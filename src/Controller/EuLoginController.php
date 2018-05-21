<?php

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

  /** @var \Symfony\Component\HttpFoundation\RequestStack $requestStack */
  protected $requestStack;

  /** @var \OpenEuropa\pcas\PCas $pCas */
  protected $pCas;

  /** @var \Drupal\Core\Session\AccountProxyInterface $currentUser */
  protected $currentUser;

  /**
   * Constructs the controller object.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
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
   * @return \Psr\Http\Message\ResponseInterface
   *   The HTTP redirect object.
   */
  protected function getLogoutRedirect() {
    $query['service'] = \Drupal::url('<front>', [], ['absolute' => TRUE]);
    $logout_url = $this->pCas->logoutUrl($query);
    $http_client = $this->pCas->getHttpClient();
    return $http_client->redirect($logout_url);
  }

}
