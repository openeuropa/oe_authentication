<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_authentication\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\cas\Traits\CasTestTrait;
use Drupal\user\Entity\Role;

/**
 * Tests two-factor authentication.
 */
class TwoFactorAuthenticationTest extends BrowserTestBase {

  use CasTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'cas_mock_server',
    'oe_authentication_eulogin_mock',
    'oe_authentication_test',
  ];

  /**
   * Tests the 2FA conditions.
   */
  public function testTwoFactorAuthenticationConditions(): void {
    // Set 2FA to be required, but without any conditions.
    $config = \Drupal::configFactory()->getEditable('oe_authentication.settings');
    $config->set('force_2fa', FALSE)->save();
    // Place the login block.
    $this->placeBlock('system_menu_block:account');

    // Create roles to use in the user role condition later.
    $role_one = Role::create([
      'id' => $this->randomMachineName(),
      'label' => $this->randomMachineName(),
    ]);
    $role_one->save();
    $role_two = Role::create([
      'id' => $this->randomMachineName(),
      'label' => $this->randomMachineName(),
    ]);
    $role_two->save();

    // Create a CAS user that doesn't use 2FA when authenticating.
    $basic_user = $this->createUser(name: 'basic_user');
    $this->createCasUser('basic_user', 'basic_user@example.com', 'pwd1', [
      'authenticationLevel' => 'BASIC',
    ], $basic_user);

    // Add other two users that use MEDIUM and HIGH 2FA methods.
    $medium_user = $this->createUser(name: 'medium_user');
    $this->createCasUser('medium_user', 'medium_user@example.com', 'pwd2', [
      'authenticationLevel' => 'MEDIUM',
    ], $medium_user);
    $high_user = $this->createUser(name: 'high_user');
    $this->createCasUser('high_user', 'high_user@example.com', 'pwd3', [
      'authenticationLevel' => 'HIGH',
    ], $high_user);

    // Create two more users with basic authentication, each one with a
    // different role.
    $role_one_user = $this->createUser(name: 'role_one_user', values: [
      'roles' => [$role_one->id()],
    ]);
    // This user has no authenticationLevel specified. It should be treated as
    // BASIC level.
    $this->createCasUser('role_one_user', 'role_one_user@example.com', 'pwd4', [], $role_one_user);
    $role_two_user = $this->createUser(name: 'role_two_user', values: [
      'roles' => [$role_two->id()],
    ]);
    $this->createCasUser('role_two_user', 'role_two_user@example.com', 'pwd5', [
      'authenticationLevel' => 'BASIC',
    ], $role_two_user);

    // Create also a user with MEDIUM level and a role matching the condition
    // below.
    $medium_with_role_user = $this->createUser(name: 'medium_with_role_user', values: [
      'roles' => [$role_one->id()],
    ]);
    $this->createCasUser('medium_with_role_user', 'medium_with_role_user@example.com', 'pwd6', [
      'authenticationLevel' => 'MEDIUM',
    ], $medium_with_role_user);

    // Since no conditions are specified, all users can log in.
    $this->casLogin('basic_user@example.com', 'pwd1');
    $assert_session = $this->assertSession();
    $this->assertUserLoggedIn();
    $this->drupalLogout();
    $this->casLogin('medium_user@example.com', 'pwd2');
    $this->assertUserLoggedIn();
    $this->drupalLogout();
    $this->casLogin('high_user@example.com', 'pwd3');
    $this->assertUserLoggedIn();
    $this->drupalLogout();
    $this->casLogin('role_one_user@example.com', 'pwd4');
    $this->assertUserLoggedIn();
    $this->drupalLogout();

    // Enable the conditions to allow to log in given a specific role.
    $config->set('2fa_conditions', [
      'user_role' => [
        'id' => 'user_role',
        'negate' => FALSE,
        'roles' => [
          $role_one->id() => $role_one->id(),
        ],
      ],
    ])->save();

    // The user account that matches the condition above is required to log in
    // with a 2FA method.
    $this->casLogin('role_one_user@example.com', 'pwd4');
    $assert_session->statusMessageContains('You are required to log in using a two-factor authentication method.', 'error');
    // Users that used a 2FA authentication method can always log in.
    $this->casLogin('medium_user@example.com', 'pwd2');
    $this->assertUserLoggedIn();
    $this->drupalLogout();
    $this->casLogin('high_user@example.com', 'pwd3');
    $this->assertUserLoggedIn();
    $this->drupalLogout();
    // Test that conditions are ignored as long as the user is using a 2FA.
    $this->casLogin('medium_with_role_user@example.com', 'pwd6');
    $this->assertUserLoggedIn();
    $this->drupalLogout();
    // Users that use a non-2FA authentication method and do not match the
    // conditions, are free to log in.
    $this->casLogin('basic_user@example.com', 'pwd1');
    $this->assertUserLoggedIn();
    $this->drupalLogout();
    $this->casLogin('role_two_user@example.com', 'pwd5');
    $this->assertUserLoggedIn();
    $this->drupalLogout();

    // Enable a second condition.
    $config->set('2fa_conditions', [
      'oe_authentication_user_test' => [
        'example' => TRUE,
        'negate' => FALSE,
        'id' => 'oe_authentication_user_test',
      ],
      'user_role' => [
        'id' => 'user_role',
        'negate' => FALSE,
        'roles' => [
          $role_one->id() => $role_one->id(),
        ],
      ],
    ])->save();

    // Set a user to be required to use 2FA.
    \Drupal::state()->set('oe_authentication_user_test.account_name', 'role_two_user');
    // Test that users that match at least one condition are required to use
    // 2FA.
    $this->casLogin('role_one_user@example.com', 'pwd4');
    $assert_session->statusMessageContains('You are required to log in using a two-factor authentication method.', 'error');
    $this->casLogin('role_two_user@example.com', 'pwd5');
    $assert_session->statusMessageContains('You are required to log in using a two-factor authentication method.', 'error');
    // Users that use a non-2FA authentication method and do not match the
    // conditions, are free to log in.
    $this->casLogin('basic_user@example.com', 'pwd1');
    $this->assertUserLoggedIn();
    $this->drupalLogout();
    // Users that used a 2FA authentication method can always log in.
    $this->casLogin('medium_user@example.com', 'pwd2');
    $this->assertUserLoggedIn();
    $this->drupalLogout();
    $this->casLogin('high_user@example.com', 'pwd3');
    $this->assertUserLoggedIn();
    $this->drupalLogout();
    $this->casLogin('medium_with_role_user@example.com', 'pwd6');
    $this->assertUserLoggedIn();
    $this->drupalLogout();

    // Check that plugins are executed correctly, taking into account also
    // the negate option.
    $config->set('2fa_conditions', [
      'oe_authentication_user_test' => [
        'example' => TRUE,
        'negate' => TRUE,
        'id' => 'oe_authentication_user_test',
      ],
      'user_role' => [
        'id' => 'user_role',
        'negate' => FALSE,
        'roles' => [
          $role_one->id() => $role_one->id(),
        ],
      ],
    ])->save();

    $this->casLogin('role_one_user@example.com', 'pwd4');
    $assert_session->statusMessageContains('You are required to log in using a two-factor authentication method.', 'error');
    $this->casLogin('role_two_user@example.com', 'pwd5');
    $this->assertUserLoggedIn();
    $this->drupalLogout();
    $this->casLogin('basic_user@example.com', 'pwd1');
    $assert_session->statusMessageContains('You are required to log in using a two-factor authentication method.', 'error');
  }

  /**
   * Tests that any exception thrown during condition evaluation is caught.
   */
  public function testConditionException(): void {
    $config = \Drupal::configFactory()->getEditable('oe_authentication.settings');
    $config
      ->set('force_2fa', FALSE)
      ->set('2fa_conditions', [
        'oe_authentication_user_test' => [
          'example' => TRUE,
          'negate' => FALSE,
          'id' => 'oe_authentication_user_test',
        ],
      ])
      ->save();

    \Drupal::state()->set('oe_authentication_user_test.crash_me', TRUE);
    $basic_user = $this->createUser(name: 'basic_user');
    $this->createCasUser('basic_user', 'basic_user@example.com', 'pwd1', [
      'authenticationLevel' => 'BASIC',
    ], $basic_user);
    $this->casLogin('basic_user@example.com', 'pwd1');
    $this->assertSession()->statusMessageContains('There was a problem validating your login, please contact a site administrator.', 'error');
  }

  /**
   * {@inheritdoc}
   */
  protected function casLogin(string $email, string $password, array $query = []): void {
    $this->drupalGet(Url::fromRoute('cas.login', [], ['query' => $query]));
    // The submit text is different from the default.
    $this->submitForm(['email' => $email, 'password' => $password], 'Login!');
  }

  /**
   * {@inheritdoc}
   */
  protected function drupalLogout(): void {
    // Compared to the parent method, we don't run assertions on the user form
    // being loaded in the page. We instead check for a login link.
    $destination = Url::fromRoute('user.page')->toString();
    $this->drupalGet(Url::fromRoute('user.logout.confirm', options: ['query' => ['destination' => $destination]]));
    $assert_session = $this->assertSession();
    $assert_session->buttonExists('Log out')->press();
    $this->assertUserNotLoggedIn();
    $this->drupalResetSession();
  }

}
