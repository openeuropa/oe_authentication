<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_authentication\Behat;

use Drupal\Tests\cas_mock_server\Context\CasMockServerContext as OriginalCasMockServerContext;

/**
 * Temporary override to fix Drupal 10 compatibility.
 *
 * Adds accessCheck(FALSE) to the entity query.
 */
class CasMockServerContext extends OriginalCasMockServerContext {

  /**
   * Clean up any CAS users created in the scenario.
   *
   * @AfterScenario @casMockServer
   */
  public function cleanCasUsers(): void {
    // Early bailout if there are no users to clean up.
    if (empty($this->users)) {
      return;
    }

    // Delete the users for the mock user storage.
    $user_manager = $this->getCasMockServerUserManager();
    $user_manager->deleteUsers(array_keys($this->users));

    // Delete users that might have been created in Drupal after logging in
    // through CAS.
    $user_storage = \Drupal::entityTypeManager()->getStorage('user');
    $query = $user_storage->getQuery();
    $or_condition = $query->orConditionGroup()
      ->condition('name', array_keys($this->users), 'IN')
      // Some users, created in Drupal, might have a different name than the CAS
      // user name as some event subscribers are able to alter them. Do an
      // additional check by email.
      ->condition('mail', array_filter(array_values($this->users)), 'IN');

    $user_ids = $user_storage->getQuery()->condition($or_condition)->accessCheck(FALSE)->execute();
    $users = $user_storage->loadMultiple($user_ids);
    if (!empty($users)) {
      foreach ($users as $user) {
        user_cancel([], $user->id(), 'user_cancel_delete');
      }
      $this->getDriver()->processBatch();
    }

    $this->users = [];
  }

}
