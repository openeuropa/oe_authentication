<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_authentication_corporate_roles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\oe_authentication_corporate_roles\Traits\CorporateRolesTestTrait;
use Drupal\oe_authentication_corporate_roles\Entity\CorporateRolesMapping;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Tests the corporate roles.
 */
class CorporateRolesTest extends KernelTestBase {

  use CorporateRolesTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_authentication',
    'oe_authentication_corporate_roles',
    'oe_authentication_user_fields',
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
      'field_oe_department' => 'DIGIT.B.3.001',
      'field_oe_organisation' => 'eu.europa.ec',
    ]);
    $user->save();

    $user = User::load($user->id());
    $this->assertEquals(['test_role_one'], array_column($user->get('oe_manual_roles')->getValue(), 'target_id'));

    // Remove the role.
    $user->removeRole('test_role_one');
    $user->save();
    $user = User::load($user->id());
    $this->assertEmpty($user->get('oe_manual_roles')->getValue());

    // Create a mapping with the role. This will automatically be added
    // to the user.
    CorporateRolesMapping::create([
      'id' => 'test',
      'label' => 'test',
      'matching_value_type' => CorporateRolesMapping::DEPARTMENT,
      'value' => 'DIGIT.B.3.001',
      'roles' => ['test_role_one'],
    ])->save();
    $user = User::load($user->id());
    $this->assertEmpty($user->get('oe_manual_roles')->getValue());
    $this->assertUsersWithRoles([
      'test_user' => ['test_role_one'],
    ]);

    // Save the user again, but this time don't change the roles. Assert that
    // because we didn't make a change to the user roles, the existing roles
    // didn't get added to the list of manual roles.
    $user->save();
    $user = User::load($user->id());
    $this->assertEmpty($user->get('oe_manual_roles')->getValue());

    // Now add another role and assert it gets added to the manual list. But
    // only this extra one, the existing one was added automatically, so it
    // should not count as a manual one.
    $user->addRole('test_role_two');
    $user->save();
    $user = User::load($user->id());
    // Both roles are added to the manual list, denoting that the user is aware
    // of adding the roles.
    $this->assertEquals(['test_role_two'], array_column($user->get('oe_manual_roles')->getValue(), 'target_id'));
  }

  /**
   * Tests that when we CRUD corporate mappings, users get updated.
   */
  public function testCorporateMappingUserUpdates(): void {
    // Create some roles.
    foreach (['role one', 'role two', 'role three'] as $name) {
      Role::create(['id' => str_replace(' ', '_', $name), 'label' => $name])->save();
    }

    // Create some test users.
    $values = [
      [
        'name' => 'user one',
        'field_oe_ldap_groups' => ['COMM_ONE', 'COMM_TWO'],
        'field_oe_organisation' => 'eu.europa.ec',
      ],
      [
        'name' => 'user two',
        'field_oe_ldap_groups' => ['COMM_THREE'],
        'field_oe_organisation' => 'eu.europa.ec',
      ],
      [
        'name' => 'user three',
        'field_oe_department' => 'COMM.B.3.003',
        'field_oe_organisation' => 'eu.europa.ec',
      ],
      [
        'name' => 'user four',
        'field_oe_department' => 'DIGIT.C.3.001',
        'field_oe_organisation' => 'eu.europa.ec',
      ],
      [
        'name' => 'user five',
        'field_oe_department' => 'DIGIT.C.3.001',
        'field_oe_ldap_groups' => ['DIGIT_THREE'],
        'field_oe_organisation' => 'eu.europa.ec',
      ],
      [
        'name' => 'user six',
        'field_oe_department' => 'DIGIT.C.3.001',
        'field_oe_ldap_groups' => ['DIGIT_ONE'],
        'field_oe_organisation' => 'external',
      ],
    ];

    foreach ($values as $value) {
      User::create($value)->save();
    }

    // Create and update a corporate mapping and assert the relevant users get
    // updated.
    $mapping = CorporateRolesMapping::create([
      'label' => 'test',
      'id' => 'test',
      'matching_value_type' => CorporateRolesMapping::LDAP_GROUP,
      'value' => 'COMM_ONE',
      'roles' => ['role_one'],
    ]);
    $mapping->save();

    $expected = [
      'user one' => ['role_one'],
    ];
    $this->assertUsersWithRoles($expected);

    // Edit the mapping and change a role and a condition.
    $mapping->set('roles', ['role_two']);
    $mapping->set('matching_value_type', CorporateRolesMapping::DEPARTMENT);
    $mapping->set('value', 'COMM.B.3');
    $mapping->save();

    // Now the user which mapped before, no longer maps. Instead, another one
    // does.
    $expected = [
      'user three' => ['role_two'],
    ];
    $this->assertUsersWithRoles($expected);

    // Update to map to the entire DIGIT.
    $mapping->set('value', 'DIGIT');
    $mapping->save();
    $expected = [
      'user four' => ['role_two'],
      'user five' => ['role_two'],
    ];
    $this->assertUsersWithRoles($expected);
    $this->assertCorporateMappingReferences([
      'user four' => ['test'],
      'user five' => ['test'],
    ]);

    // Create another mapping that will overlap.
    $another_mapping = CorporateRolesMapping::create([
      'label' => 'test2',
      'id' => 'test2',
      'matching_value_type' => CorporateRolesMapping::DEPARTMENT,
      'value' => 'DIGIT.C.3.001',
      'roles' => ['role_one'],
    ]);
    $another_mapping->save();

    $expected = [
      'user four' => ['role_two', 'role_one'],
      'user five' => ['role_two', 'role_one'],
    ];
    $this->assertUsersWithRoles($expected);
    $this->assertCorporateMappingReferences([
      'user four' => ['test', 'test2'],
      'user five' => ['test', 'test2'],
    ]);

    // Update this second mapping to change the conditions.
    $another_mapping->set('value', 'COMM.B');
    $another_mapping->save();

    $expected = [
      'user three' => ['role_one'],
      'user four' => ['role_two'],
      'user five' => ['role_two'],
    ];
    $this->assertUsersWithRoles($expected);
    $this->assertCorporateMappingReferences([
      'user three' => ['test2'],
      'user four' => ['test'],
      'user five' => ['test'],
    ]);

    // Delete this second mapping and assert we have the roles cleared.
    $another_mapping->delete();
    $expected = [
      'user four' => ['role_two'],
      'user five' => ['role_two'],
    ];
    $this->assertUsersWithRoles($expected);
    $this->assertCorporateMappingReferences([
      'user four' => ['test'],
      'user five' => ['test'],
    ]);
  }

  /**
   * Tests the various cases of looking up mappings for users.
   */
  public function testMappingsLookup(): void {
    // Create some roles.
    foreach (['role one', 'role two', 'role three'] as $name) {
      Role::create(['id' => str_replace(' ', '_', $name), 'label' => $name])->save();
    }

    // Create some mappings.
    $one = CorporateRolesMapping::create([
      'label' => 'one',
      'id' => 'a',
      'matching_value_type' => CorporateRolesMapping::LDAP_GROUP,
      'value' => 'COMM_ONE',
      'roles' => ['role_one'],
    ]);
    $one->save();

    $two = CorporateRolesMapping::create([
      'label' => 'two',
      'id' => 'b',
      'matching_value_type' => CorporateRolesMapping::LDAP_GROUP,
      'value' => 'DIGIT_TWO',
      'roles' => ['role_one'],
    ]);
    $two->save();

    $three = CorporateRolesMapping::create([
      'label' => 'three',
      'id' => 'c',
      'matching_value_type' => CorporateRolesMapping::DEPARTMENT,
      'value' => 'DIGIT.5',
      'roles' => ['role_one'],
    ]);
    $three->save();

    $four = CorporateRolesMapping::create([
      'label' => 'four',
      'id' => 'd',
      'matching_value_type' => CorporateRolesMapping::DEPARTMENT,
      'value' => 'DIGIT.5.0.003',
      'roles' => ['role_one'],
    ]);
    $four->save();

    // Create a user for which to look for mappings.
    $user = User::create([
      'name' => 'test user',
      'field_oe_department' => 'DIGIT.5.0.003',
      'field_oe_ldap_groups' => ['DIGIT_TWO'],
      'field_oe_organisation' => 'external',
    ]);
    $user->save();

    /** @var \Drupal\oe_authentication_corporate_roles\CorporateRolesMappingLookup $lookup_service */
    $lookup_service = \Drupal::service('oe_authentication_corporate_roles.mapping_lookup');
    // The user is external, so no mappings are found.
    $this->assertEmpty($lookup_service->getMappingsForUser($user));

    $user->set('field_oe_organisation', 'eu.europa.ec');
    $user->set('field_oe_ldap_groups', []);
    $user->save();
    $mappings = $lookup_service->getMappingsForUser($user);
    $this->assertCount(2, $mappings);
    $this->assertEquals(['c', 'd'], array_keys($mappings));

    // Add also a group so we get another mapping.
    $user->set('field_oe_ldap_groups', ['DIGIT_TWO']);
    $user->save();
    $mappings = $lookup_service->getMappingsForUser($user);
    $this->assertCount(3, $mappings);
    $this->assertEquals(['b', 'c', 'd'], array_keys($mappings));

    // Remove the department and change the group.
    $user->set('field_oe_ldap_groups', ['COMM_ONE']);
    $user->set('field_oe_department', NULL);
    $user->save();
    $mappings = $lookup_service->getMappingsForUser($user);
    $this->assertCount(1, $mappings);
    $this->assertEquals(['a'], array_keys($mappings));

    // Use multiple LDAP groups.
    $user->set('field_oe_ldap_groups', ['COMM_NINE', 'COMM_ONE']);
    $user->set('field_oe_department', NULL);
    $user->save();
    $mappings = $lookup_service->getMappingsForUser($user);
    $this->assertCount(1, $mappings);
    $this->assertEquals(['a'], array_keys($mappings));
  }

  /**
   * Asserts the corporate mapping references on the users.
   *
   * @param array $expected
   *   The expected references.
   */
  protected function assertCorporateMappingReferences(array $expected): void {
    $ids = \Drupal::entityTypeManager()->getStorage('user')->getQuery()
      ->accessCheck(FALSE)
      ->sort('uid', 'ASC')
      ->execute();

    $users = User::loadMultiple($ids);
    $actual = [];
    foreach ($users as $user) {
      if ($user->get('oe_corporate_roles_mappings')->isEmpty()) {
        continue;
      }
      $actual[$user->label()] = array_column($user->get('oe_corporate_roles_mappings')->getValue(), 'target_id');
    }

    $this->assertEquals($expected, $actual);
  }

  /**
   * Returns the user by name.
   *
   * @param string $name
   *   The name.
   *
   * @return \Drupal\user\UserInterface
   *   The user.
   */
  protected function getUserByName(string $name): UserInterface {
    $users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['name' => $name]);
    return reset($users);
  }

}
