<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_authentication\Functional;

use Drupal\oe_authentication\Form\AuthenticationSettingsForm;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the settings form.
 */
class SettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_authentication_test',
  ];

  /**
   * Tests the two-factor authentication condition settings.
   */
  public function testTwoFactorAuthenticationConditionSettings(): void {
    $role_id = $this->createRole(['administer authentication configuration']);
    $this->drupalLogin($this->createUser(values: ['roles' => [$role_id]]));
    $this->drupalGet('/admin/config/system/oe_authentication');

    $assert_session = $this->assertSession();
    $wrapper = $assert_session->elementExists('css', 'div[data-drupal-selector="edit-condition-tabs"]');
    $details = $wrapper->findAll('xpath', $this->cssSelectToXpath(selector: 'details', prefix: '/'));
    // Core provides more plugins such as "current theme", "response status",
    // but they won't show as only condition plugin that require exclusively a
    // user account context.
    $this->assertCount(2, $details);
    [$test_condition_wrapper, $user_role_condition_wrapper] = $details;
    // The first condition plugin is the user test one.
    $this->assertEquals('User test condition', $assert_session->elementExists('css', 'summary', $test_condition_wrapper)->getText());
    $this->assertFalse($assert_session->fieldExists('Example configuration option', $test_condition_wrapper)->isChecked());
    // The second one is the user role core plugin.
    $this->assertEquals('User Role', $assert_session->elementExists('css', 'summary', $user_role_condition_wrapper)->getText());
    $this->assertEquals([
      'anonymous',
      'authenticated',
      $role_id,
    ], array_map(
      static fn ($element) => $element->getAttribute('value'),
      $user_role_condition_wrapper->findAll('css', 'input[name^="2fa_conditions[user_role][roles]"]'),
    ));

    // 2FA setting needs to be enabled or condition configuration won't be
    // saved.
    $assert_session->fieldExists('Force two factor authentication')->check();

    // Test that validation is trigger for plugin forms.
    $test_condition_wrapper->checkField('Do not click this');
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('You cannot select this field.', 'error');
    $this->assertTrue($test_condition_wrapper->findField('Do not click this')->hasClass('error'));
    $test_condition_wrapper->uncheckField('Do not click this');
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('The configuration options have been saved.', 'status');

    // No configuration was provided for the plugins, so they match their
    // default state. This means that the plugins are disabled, and no
    // configuration should be saved.
    $config = \Drupal::config(AuthenticationSettingsForm::CONFIG_NAME);
    $this->assertEquals([], $config->get('2fa_conditions'));

    // Set some configuration for the test plugin.
    $this->drupalGet('/admin/config/system/oe_authentication');
    $test_condition_wrapper->checkField('Example configuration option');
    $test_condition_wrapper->checkField('Negate');
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('The configuration options have been saved.', 'status');

    $this->refreshVariables();
    $config = \Drupal::config(AuthenticationSettingsForm::CONFIG_NAME);
    $this->assertEquals([
      'oe_authentication_user_test' => [
        'example' => TRUE,
        'negate' => TRUE,
        'id' => 'oe_authentication_user_test',
      ],
    ], $config->get('2fa_conditions'));

    // Enable some settings for the other plugin.
    $user_role_condition_wrapper->checkField('Authenticated user');
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('The configuration options have been saved.', 'status');

    $this->refreshVariables();
    $config = \Drupal::config(AuthenticationSettingsForm::CONFIG_NAME);
    $this->assertEquals([
      'oe_authentication_user_test' => [
        'example' => TRUE,
        'negate' => TRUE,
        'id' => 'oe_authentication_user_test',
      ],
      'user_role' => [
        'id' => 'user_role',
        'negate' => FALSE,
        'roles' => [
          'authenticated' => 'authenticated',
        ],
      ],
    ], $config->get('2fa_conditions'));

    // Check that the configuration is loaded back correctly.
    $this->drupalGet('/admin/config/system/oe_authentication');
    $assert_session->checkboxChecked('Example configuration option', $test_condition_wrapper);
    $assert_session->checkboxChecked('Negate', $test_condition_wrapper);
    $assert_session->checkboxChecked('Authenticated user', $user_role_condition_wrapper);
    $assert_session->checkboxNotChecked('Negate', $user_role_condition_wrapper);

    // Disabling the 2FA will clean all the condition settings.
    $assert_session->fieldExists('Force two factor authentication')->uncheck();
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('The configuration options have been saved.', 'status');
    $this->refreshVariables();
    $config = \Drupal::config(AuthenticationSettingsForm::CONFIG_NAME);
    $this->assertEquals([], $config->get('2fa_conditions'));
  }

}
