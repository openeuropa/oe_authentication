<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;
use OpenEuropa\pcas\Security\Core\User\PCasUserInterface;

/**
 * Provides user.
 */
class UserProvider {

  /**
   * User Storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * Provides the user.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->userStorage = $entityTypeManager->getStorage('user');
  }

  /**
   * Returns the user object for the given Pcas user.
   *
   * @param \OpenEuropa\pcas\Security\Core\User\PCasUserInterface $pCasUser
   *   User for pCas.
   *
   * @return \Drupal\user\UserInterface|null
   *   The user object.
   *
   * @throws \Exception
   */
  public function loadAccount(PCasUserInterface $pCasUser) : ?UserInterface {
    $account = $this->doLoadAccount($pCasUser);
    if (!$account && $this->canCreateNewAccounts()) {
      $account = $this->createAccount($pCasUser);
    }
    // Account not found, and not eligible for account creation.
    if (!$account) {
      return NULL;
    }
    $this->attachRoles($account, $pCasUser);

    return $account;
  }

  /**
   * Attach a roles to the Pcas user.
   *
   * @param \Drupal\user\UserInterface $account
   *   Account in drupal.
   * @param \OpenEuropa\pcas\Security\Core\User\PCasUserInterface $pCasUser
   *   User for pCas.
   */
  protected function attachRoles(UserInterface $account, PCasUserInterface $pCasUser) {
    // @todo Fetch the user roles from the authorisation service.
  }

  /**
   * Load the Pcas user.
   *
   * @param \OpenEuropa\pcas\Security\Core\User\PCasUserInterface $pCasUser
   *   User for pCas.
   *
   * @return \Drupal\user\Entity\User|false
   *   The user object if any, false otherwise.
   *
   * @throws \Exception
   */
  protected function doLoadAccount(PCasUserInterface $pCasUser) {
    $username = $pCasUser->get('cas:user');
    if ($username === NULL) {
      throw new \Exception('No username found on the PCas user.');
    }

    $accounts = $this->userStorage->loadByProperties(['name' => $username]);
    if (empty($accounts)) {
      // Account does not exist, creation of new accounts is handled in.
      // @see \Drupal\oe_authentication\Controller\AuthenticationController::login.
      return FALSE;
    }

    return array_pop($accounts);
  }

  /**
   * Create a local user account.
   *
   * @param \OpenEuropa\pcas\Security\Core\User\PCasUserInterface $pCasUser
   *   The PCas user object.
   *
   * @return \Drupal\user\Entity\User
   *   The new created Drupal user object.
   */
  protected function createAccount(PCasUserInterface $pCasUser) {
    $name = $this->uniqueUsername($pCasUser->getUsername());
    // @todo Fix the retrieval of the email as not all CAS replies have "cas:email".
    $mail = $pCasUser->get('cas:email');

    /** @var \Drupal\user\Entity\User $account */
    $account = $this->userStorage->create([
      'mail' => $mail,
      'name' => $name,
    ]);
    $account->activate()->save();

    return $account;
  }

  /**
   * Generate available username, as this must be unique in Drupal.
   *
   * @todo This might lead to race condition.
   * Generate username in authorization service?
   *
   * @param string $name
   *   The proposed username.
   *
   * @return string
   *   The available username.
   */
  protected function uniqueUsername($name) {
    $requested_name = $name;
    $suffix = 0;
    do {
      $accounts = $this->userStorage->loadByProperties(['name' => $name]);
      if ($accounts) {
        $name = $requested_name . '_' . $suffix;
        $suffix++;
      }
    } while (!empty($accounts));

    return $name;
  }

  /**
   * Stub function: Determine if lazy account creation is allowed.
   *
   * @return bool
   *   True or False to can create a new Account.
   */
  protected function canCreateNewAccounts() {
    // @todo Implement this stub with a setting?
    return TRUE;
  }

}
