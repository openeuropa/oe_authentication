<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_authentication_corporate_roles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests the corporate roles.
 */
class CorporateRolesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_authentication',
    'oe_authentication_corporate_roles',
    'system',
    'user',
    'field',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'user']);
    $this->installEntitySchema('user');
    $this->installSchema('system', ['sequences']);
    $this->installConfig(['oe_authentication_corporate_roles']);
  }

  /**
   * Tests manual roles.
   */
  public function testManualRoleAssignment(): void {
    $role_one = Role::create(['id' => 'test_role_one', 'label' => 'Test role on']);
    $role_one->save();

    $role_two = Role::create(['id' => 'test_role_two', 'label' => 'Test role two']);
    $role_two->save();

    $user = User::create([
      'name' => 'test_user',
      'roles' => [$role_one->id()],
    ]);
    $user->save();

    $user = User::load($user->id());
    $this->assertEquals(['test_role_one'], array_column($user->get('oe_manual_roles')->getValue(), 'target_id'));

    // Remove the role.
    $user->removeRole('test_role_one');
    $user->save();
    $user = User::load($user->id());
    $this->assertEmpty($user->get('oe_manual_roles')->getValue());

    // Assign back the role but mimic it was done automatically.
    $user->addRole('test_role_one');
    $user->automatic_corporate_roles = TRUE;
    $user->save();
    $user = User::load($user->id());
    $this->assertEmpty($user->get('oe_manual_roles')->getValue());

    // Save the user again, but this time don't change the roles. Assert that
    // because we didn't make a change to the user roles, the existing roles
    // didn't get added to the list of manual roles.
    $user->save();
    $user = User::load($user->id());
    $this->assertEmpty($user->get('oe_manual_roles')->getValue());

    // Now add another role and assert it gets added to the manual list.
    $user->addRole('test_role_two');
    $user->save();
    $user = User::load($user->id());
    // Both roles are added to the manual list, denoting that the user is aware
    // of adding the roles.
    $this->assertEquals(['test_role_one', 'test_role_two'], array_column($user->get('oe_manual_roles')->getValue(), 'target_id'));
  }

}
