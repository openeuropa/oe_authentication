<?php
namespace Drupal\eu_login;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;
use OpenEuropa\pcas\Security\Core\User\PCasUserInterface;

class UserProvider {

  public function __construct(EntityTypeManagerInterface $entityTypeManger) {

  }

  /**
   * Returns the user object for the given Pcas user.
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

  protected function attachRoles(UserInterface $account, PCasUserInterface $PCasUser) {
    // @todo Fetch the user roles from the authorisation service.
  }

  protected function doLoadAccount(PCasUserInterface $pCasUser) {
    $user_storage = \Drupal::entityTypeManager()->getStorage('user');
    $mail = $pCasUser->get('cas:email');
    if (empty($mail)) {
      throw new \Exception('Email address not provided by EU Login.');
    }
    $accounts = $user_storage->loadByProperties(['mail' => $mail]);
    if (empty($accounts)) {
      // Account does not exist, creation of new accounts is handled in
      // @see \Drupal\eu_login\Controller\EuLoginController::login
      return FALSE;
    }
    return array_pop($accounts);
  }

  protected function createAccount(PCasUserInterface $pCasUser) {
    $name = $this->availableUsername($pCasUser->getUsername());
    $mail = $pCasUser->get('cas:email');

    /** @var \Drupal\user\Entity\User $account */
    $account = \Drupal::entityTypeManager()->getStorage('user')->create([
      'mail' => $mail,
      'name' => $name,
    ]);
    $account->activate()->save();
    return $account;
  }

  /**
   * Generate an available username, as this must be unique in Drupal.
   *
   * @param $name
   *
   * @return string
   */
  protected function availableUsername($name) {
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

  protected function canCreateNewAccounts() {
    // @todo Implement this stub with a setting?
    return TRUE;
  }
}
