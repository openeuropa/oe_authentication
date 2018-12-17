<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_authentication\Behat;

use Drupal\DrupalExtension\Context\DrupalContext as BaseDrupalContext;

/**
 * Defines step definitions specifically for testing the CAS users.
 */
class DrupalContext extends BaseDrupalContext {

  /**
   * Configures the CAS module to use Drupal login.
   *
   * @var string $username
   *   The name of the user to be blocked.
   *
   * @When the user :username is blocked
   *
   * @throws \Exception
   *   Thrown when the user with the given name does not exist.
   */
  public function setConfigDrupalLogin($username): void {
    /** @var \Drupal\user\Entity\User $user */
    $user = user_load_by_name($username);
    if ($user) {
      $user->block();
      $user->save();
    }
  }

}
