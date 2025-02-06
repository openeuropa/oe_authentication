<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_authentication_corporate_roles\Traits;

/**
 * Helpers for testing the automatic corporate roles.
 */
trait CorporateRolesTestTrait {

  /**
   * Asserts the users that have any roles.
   *
   * @param array $expected
   *   The expected roles.
   */
  protected function assertUsersWithRoles(array $expected): void {
    $storage = \Drupal::entityTypeManager()->getStorage('user');
    $storage->resetCache();
    $ids = \Drupal::entityTypeManager()->getStorage('user')->getQuery()
      ->accessCheck(FALSE)
      ->sort('uid', 'ASC')
      ->execute();

    $users = $storage->loadMultiple($ids);
    $actual = [];
    foreach ($users as $user) {
      $roles = $user->getRoles(TRUE);
      if (!$roles) {
        continue;
      }
      $actual[$user->label()] = $user->getRoles(TRUE);
    }

    $this->assertEquals($expected, $actual);
  }

}
