<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_authentication_user_fields\Functional;

use Drupal\Tests\BrowserTestBase;
use Drush\TestTraits\DrushTestTrait;

/**
 * Tests the user fields drush sanitization.
 */
class UserSanitizeCommandTest extends BrowserTestBase {

  use DrushTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_authentication_user_fields',
  ];

  /**
   * Tests the eu login users drush sanitization.
   */
  public function testEuLoginUsersDataSanitization() {
    $user = $this->createUser([], NULL, FALSE, [
      'field_oe_firstname' => 'Laurissa',
      'field_oe_lastname' => 'Garrett',
      'field_oe_department' => 'Needless',
      'field_oe_organisation' => 'Beam',
      'field_oe_ldap_groups' => [
        'Test 1',
        'Test 2',
      ],
    ]);

    $user2 = $this->createUser([], NULL, FALSE, [
      'field_oe_firstname' => 'Beverly',
      'field_oe_lastname' => 'Thorley',
      'field_oe_department' => 'Green',
      'field_oe_organisation' => 'Lantern',
      'field_oe_ldap_groups' => [
        'User 1 group',
        'User 2 group',
      ],
    ]);

    // We need to write in session table to trigger the table creation.
    \Drupal::service('session_handler.storage')->write('some-id', 'serialized-session-data');

    $version = $this->drushMajorVersion();
    $expected = 'The following operations will be performed:' . PHP_EOL;
    $expected .= '* Truncate sessions table.' . PHP_EOL;
    $expected .= '* Sanitize text fields associated with users.' . PHP_EOL;
    $expected .= '* Sanitize user passwords.' . PHP_EOL;
    $expected .= '* Sanitize user emails.' . PHP_EOL;
    $expected .= '* Preserve user emails and passwords for the specified roles.' . PHP_EOL;
    $expected .= '* Sanitise user fields.';

    if ($version > 12) {
      $expected = 'The following operations will be performed:' . PHP_EOL;
      $expected .= '* Sanitize user passwords.' . PHP_EOL;
      $expected .= '* Sanitize user emails.' . PHP_EOL;
      $expected .= '* Preserve user emails and passwords for the specified roles.' . PHP_EOL;
      $expected .= '* Sanitize text fields associated with users.' . PHP_EOL;
      $expected .= '* Truncate sessions table.' . PHP_EOL;
      $expected .= '* Sanitise user fields.';
    }

    $this->drush('sql:sanitize');
    $this->assertOutputEquals($expected);

    $user = \Drupal::entityTypeManager()->getStorage('user')->load($user->id());
    $this->assertEquals('First Name ' . $user->id(), $user->get('field_oe_firstname')->value);
    $this->assertEquals('Last Name ' . $user->id(), $user->get('field_oe_lastname')->value);
    $this->assertEquals('Department ' . $user->id(), $user->get('field_oe_department')->value);
    $this->assertEquals('Organisation ' . $user->id(), $user->get('field_oe_organisation')->value);
    $this->assertEquals([
      'LDAP group',
      'LDAP group',
    ], array_column($user->get('field_oe_ldap_groups')->getValue(), 'value'));

    $user2 = \Drupal::entityTypeManager()->getStorage('user')->load($user2->id());
    $this->assertEquals('First Name ' . $user2->id(), $user2->get('field_oe_firstname')->value);
    $this->assertEquals('Last Name ' . $user2->id(), $user2->get('field_oe_lastname')->value);
    $this->assertEquals('Department ' . $user2->id(), $user2->get('field_oe_department')->value);
    $this->assertEquals('Organisation ' . $user2->id(), $user2->get('field_oe_organisation')->value);
    $this->assertEquals([
      'LDAP group',
      'LDAP group',
    ], array_column($user2->get('field_oe_ldap_groups')->getValue(), 'value'));
  }

}
