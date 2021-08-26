<?php

declare(strict_types = 1);

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
      'field_oe_firstname' => 'First name',
      'field_oe_lastname' => 'Last name',
      'field_oe_department' => 'Department',
      'field_oe_organisation' => 'Organisation',
    ]);

    $this->drush('sql:sanitize');
    $expected = 'The following operations will be performed:' . PHP_EOL . PHP_EOL;
    $expected .= '* Sanitise user fields.' . PHP_EOL;
    $expected .= '* Truncate sessions table.' . PHP_EOL;
    $expected .= '* Sanitize text fields associated with users.' . PHP_EOL;
    $expected .= '* Sanitize user passwords.' . PHP_EOL;
    $expected .= '* Sanitize user emails.';
    $this->assertOutputEquals($expected);

    $user = \Drupal::entityTypeManager()->getStorage('user')->load($user->id());
    $this->assertEquals('First Name ' . $user->id(), $user->get('field_oe_firstname')->value);
    $this->assertEquals('Last Name ' . $user->id(), $user->get('field_oe_lastname')->value);
    $this->assertEquals('Department ' . $user->id(), $user->get('field_oe_department')->value);
    $this->assertEquals('Organisation ' . $user->id(), $user->get('field_oe_organisation')->value);
  }

}
