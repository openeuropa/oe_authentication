<?php

namespace Drupal\eu_login\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\UserInterface;
use OpenEuropa\pcas\PCas;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Returns responses for EU Login routes.
 */
class EuLoginController extends ControllerBase {

  protected $requestStack;

  protected $pCas;

  /**
   * Constructs the controller object.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(RequestStack $requestStack, PCas $pCas) {
    $this->requestStack = $requestStack;
    $this->pCas = $pCas;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('pcas')
    );
  }

  public function login() {
    /** @var \Drupal\Core\Session\AccountProxyInterface $account */
    $account = \Drupal::currentUser();
    if ($account->isAuthenticated()) {
      // If uid is not set on the session, we just got redirected back from
      // EU Login, and we have to do the actual login of the user.
      if (!\Drupal::service('session')->get('uid')) {
        $user = \Drupal::entityTypeManager()->getStorage('user')->load($account->id());
        $this->userLoginFinalize($user);
        return ['#markup' => t("Authenticated as %name", ['%name' => $user->label()])];
      }
      return $this->redirect('<front>');
    }
    // EU Login session started, no active Drupal user yet.
    if ($pcas_account = $this->pCas->getAuthenticatedUser()) {
      /** @var \Drupal\eu_login\UserProvider $serv */
      $serv = \Drupal::service('eulogin.userprovider');
      $user = $serv->loadAccount($pcas_account);
      if ($account) {
        $this->userLoginFinalize($user);
      }

    }
    // No active session yet, redirect to EU Login for authentication.
    if ($response = $this->pCas->login()) {
      return $response;
    }
    return ['#markup' => 'Que?'];
  }

  protected function userLoginFinalize(UserInterface $account) {
  \Drupal::currentUser()->setAccount($account);
  \Drupal::logger('user')->notice('Session opened for %name.', ['%name' => $account->getUsername()]);
  // Update the user table timestamp noting user has logged in.
  // This is also used to invalidate one-time login links.
  $account->setLastLoginTime(REQUEST_TIME);
  \Drupal::entityTypeManager()
    ->getStorage('user')
    ->updateLastLoginTimestamp($account);

  // Regenerate the session ID to prevent against session fixation attacks.
  // This is called before hook_user_login() in case one of those functions
  // fails or incorrectly does a redirect which would leave the old session
  // in place.
  \Drupal::service('session')->migrate();
  \Drupal::service('session')->set('uid', $account->id());
  \Drupal::moduleHandler()->invokeAll('user_login', [$account]);
}

  protected function available_username($name) {
    $requested_name = $name;
    $user_storage = \Drupal::entityTypeManager()->getStorage('user');
    $suffix = 0;
    do {
      $accounts = $user_storage->loadByProperties(['name' => $name]);
      if ($accounts) {
        $name = $requested_name . '_' . $suffix;
        $suffix++;
      }
    } while (!empty($accounts));
    return $name;
  }

  /**
   * Builds the response.
   */
  public function logout() {
    $query['service'] = \Drupal::url('<front>', [], ['absolute' => TRUE]);
    $logout_url = $this->pCas->logoutUrl($query);
    $response = $this->pCas->getHttpClient()->redirect($logout_url);
    if ($response) {
      if (\Drupal::currentUser()->isAuthenticated()) {
        user_logout();
      }
      return $response;
    }
    if (\Drupal::currentUser()->isAuthenticated()) {
      user_logout();
    }
    return $this->redirect('<front>');
  }

}
