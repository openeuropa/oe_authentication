<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_authentication_corporate_roles\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\oe_authentication_corporate_roles\Traits\CorporateRolesTestTrait;
use Drupal\oe_authentication_corporate_roles\Entity\CorporateRolesMapping;
use Drupal\user\Entity\Role;
use Drupal\user\UserInterface;

/**
 * Tests the automatic corporate roles.
 */
class AutomaticCorporateRolesTest extends WebDriverTestBase {

  use CorporateRolesTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_authentication_corporate_roles',
    'cas_mock_server',
    'oe_authentication_eulogin_mock',
    'system',
    'user',
    'field',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $forced_login = \Drupal::configFactory()->getEditable('cas.settings')->get('forced_login');
    $forced_login['enabled'] = TRUE;
    \Drupal::configFactory()->getEditable('cas.settings')->set('forced_login', $forced_login)->save();
    \Drupal::service('cas_mock_server.server_manager')->start();

    \Drupal::configFactory()->getEditable('oe_authentication.settings')->set('assurance_level', 'LOW')->save();
  }

  /**
   * Tests that upon first login (register), the user gets the relevant roles.
   */
  public function testAutomaticRolesOnRegister(): void {
    \Drupal::configFactory()->getEditable('user.settings')->set('register', UserInterface::REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL)->save();

    Role::create(['id' => 'test_role', 'label' => 'test role'])->save();

    $mapping = CorporateRolesMapping::create([
      'label' => 'test',
      'id' => 'test',
      'matching_value_type' => CorporateRolesMapping::LDAP_GROUP,
      'value' => 'COMM_ONE',
      'roles' => ['test_role'],
    ]);
    $mapping->save();

    $this->createUserWithAttributes([
      'groups' => 'COMM_ONE',
    ]);
    $this->logUserIn();
    $user = $this->loadTestUser();
    $this->assertTrue($user->isActive());
    $this->assertUsersWithRoles([
      'test' => ['test_role'],
    ]);

    // Without any mapping, the user is not active by default.
    $this->logUserOut();
    $this->deleteTestUser();
    $this->createUserWithAttributes();
    $this->logUserIn(FALSE);
    $user = $this->loadTestUser();
    $this->assertFalse($user->isActive());
  }

  /**
   * Tests that upon login, the user gets the relevant roles.
   */
  public function testAutomaticRolesOnLogin(): void {
    // Create some roles.
    foreach (['role one', 'role two', 'role three', 'role four', 'role five'] as $name) {
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
      'roles' => ['role_two'],
    ]);
    $two->save();

    $three = CorporateRolesMapping::create([
      'label' => 'three',
      'id' => 'c',
      'matching_value_type' => CorporateRolesMapping::DEPARTMENT,
      'value' => 'DIGIT.5',
      'roles' => ['role_three'],
    ]);
    $three->save();

    $four = CorporateRolesMapping::create([
      'label' => 'four',
      'id' => 'd',
      'matching_value_type' => CorporateRolesMapping::DEPARTMENT,
      'value' => 'DIGIT.5.0.003',
      'roles' => ['role_four'],
    ]);
    $four->save();

    $this->createUserWithAttributes();
    $this->logUserIn();
    $user = $this->loadTestUser();
    // We have no roles as nothing matched.
    $this->assertEmpty($user->getRoles(TRUE));

    $this->logUserOut();
    $this->deleteTestUser();
    $this->createUserWithAttributes([
      'departmentNumber' => 'DIGIT.5.0.003',
    ]);
    $this->logUserIn();
    $user = $this->loadTestUser();
    $this->assertTrue($user->isActive());
    $this->assertUsersWithRoles([
      'test' => ['role_three', 'role_four'],
    ]);

    $this->logUserOut();
    $this->deleteTestUser();
    $this->createUserWithAttributes([
      'groups' => 'COMM_ONE',
    ]);
    $this->logUserIn();
    $user = $this->loadTestUser();
    $this->assertTrue($user->isActive());
    $this->assertUsersWithRoles([
      'test' => ['role_one'],
    ]);

    // Log out, delete the mappings and log back in.
    $this->logUserOut();
    $one->delete();
    $this->logUserIn();
    $user = $this->loadTestUser();
    $this->assertEmpty($user->getRoles(TRUE));

    // Delete the user and create another one but add a manual role to it.
    $this->deleteTestUser();
    $this->createUserWithAttributes([
      'groups' => 'DIGIT_TWO',
    ]);
  }

  /**
   * Tests that users can create corporate mappings in the UI.
   */
  public function testCorporateMappingCreation(): void {
    $forced_login = \Drupal::configFactory()->getEditable('cas.settings')->get('forced_login');
    $forced_login['enabled'] = FALSE;
    \Drupal::configFactory()->getEditable('cas.settings')->set('forced_login', $forced_login)->save();

    $user = $this->drupalCreateUser();
    $this->drupalLogin($user);

    $this->drupalGet('/admin/people/corporate_roles_mapping/add');
    $this->assertSession()->pageTextContains('Access denied');
    $role = Role::create(['label' => 'test', 'id' => 'test']);
    $role->grantPermission('manage corporate roles');
    $role->save();
    $user->addRole($role->id());
    $user->save();
    $this->drupalGet('/admin/people/corporate_roles_mapping/add');

    $this->getSession()->getPage()->fillField('Label', 'Test mapping');
    $this->assertSession()->waitForElement('css', '.machine-name-value');
    $this->getSession()->getPage()->selectFieldOption('Matching type', 'Department');
    $this->getSession()->getPage()->fillField('Value', 'COMM.B.3');
    $this->getSession()->getPage()->checkField('test');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Created new Test mapping.');
    $this->assertSession()->pageTextContains('No users were found matching these conditions.');
    $mapping = CorporateRolesMapping::load('test_mapping');
    $this->assertEquals('department', $mapping->get('matching_value_type'));
    $this->assertEquals('COMM.B.3', $mapping->get('value'));
    $this->assertEquals(['test' => 'test'], $mapping->get('roles'));

  }

  /**
   * Creates a test user with specific department and group info.
   *
   * @param array $attributes
   *   The attributes.
   */
  protected function createUserWithAttributes(array $attributes = []): void {
    $user = [
      'username' => 'test',
      'email' => 'test@example.com',
      'password' => 'test',
      'firstName' => 'John',
      'lastName' => 'Rambo',
      'domain' => 'eu.europa.ec',
    ] + $attributes;
    $user_manager = \Drupal::service('cas_mock_server.user_manager');
    $user_manager->addUser($user);
  }

  /**
   * Logs the test user in.
   */
  protected function logUserIn(bool $active = TRUE): void {
    $this->drupalGet('user/login');
    $this->getSession()->getPage()->fillField('Username or e-mail address', 'test@example.com');
    $this->getSession()->getPage()->fillField('Password', 'test');
    $this->getSession()->getPage()->pressButton('Login!');
    if ($active) {
      $this->assertSession()->pageTextContains('You have been logged in');
      return;
    }
    $this->assertSession()->pageTextContains('Thank you for applying for an account. Your account is currently pending approval by the site administrator.');

  }

  /**
   * Deletes the test user.
   */
  protected function deleteTestUser(): void {
    $user_manager = \Drupal::service('cas_mock_server.user_manager');
    $user_manager->deleteUsers();

    $user = $this->loadTestUser();
    $user->delete();
  }

  /**
   * Loads the test user.
   *
   * @return \Drupal\user\UserInterface
   *   The test user.
   */
  protected function loadTestUser(): UserInterface {
    $storage = \Drupal::entityTypeManager()->getStorage('user');
    $storage->resetCache();
    $users = $storage->loadByProperties(['name' => 'test']);
    return reset($users);
  }

  /**
   * Logs the user out.
   *
   * We cannot use the regular base method because of CAS redirect.
   */
  protected function logUserOut(): void {
    $this->drupalGet('/user/logout');
    $this->submitForm([], 'op');
  }

}
