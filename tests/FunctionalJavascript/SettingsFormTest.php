<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_authentication\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Element\TraversableElement;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\oe_authentication\Form\AuthenticationSettingsForm;

/**
 * Tests the settings form.
 */
class SettingsFormTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'condition_test',
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
    $use_2fa_fieldset = $assert_session->elementExists('named', ['fieldset', 'Require two-factor authentication']);
    $this->assertCount(3, $use_2fa_fieldset->findAll('css', 'input'));
    $never_radio = $assert_session->fieldExists('Never', $use_2fa_fieldset);
    $this->assertTrue($never_radio->isChecked());
    $always_radio = $assert_session->fieldExists('Always', $use_2fa_fieldset);
    $this->assertFalse($always_radio->isChecked());
    $conditional_radio = $assert_session->fieldExists('Based on conditions', $use_2fa_fieldset);
    $this->assertFalse($conditional_radio->isChecked());

    $conditions_wrapper = $this->assertDetailsElementExists('Two-factor authentication conditions');
    $this->assertFalse($conditions_wrapper->isVisible());
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('The configuration options have been saved.', 'status');

    // The "never" option sets the "force_2fa" to FALSE.
    $config = $this->loadConfig();
    $this->assertEquals([], $config->get('2fa_conditions'));
    $this->assertFalse($config->get('force_2fa'));

    // Re-assert that the conditions are still hidden.
    $this->drupalGet('/admin/config/system/oe_authentication');
    $this->assertFalse($conditions_wrapper->isVisible());
    // Set the 2FA to "always".
    $always_radio->click();
    $this->assertFalse($conditions_wrapper->isVisible());
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('The configuration options have been saved.', 'status');

    // The "always" option sets the "force_2fa" to TRUE.
    $config = $this->loadConfig();
    $this->assertEquals([], $config->get('2fa_conditions'));
    $this->assertTrue($config->get('force_2fa'));

    $this->drupalGet('/admin/config/system/oe_authentication');
    $this->assertFalse($conditions_wrapper->isVisible());
    $conditional_radio->click();
    $conditions_wrapper_selector = 'details[data-drupal-selector="edit-2fa-conditions"]';
    $conditions_wrapper = $assert_session->waitForElementVisible('css', $conditions_wrapper_selector);
    $this->assertNotNull($conditions_wrapper);

    // Test that only condition plugins that require a user context are shown.
    // The conditions are disabled by default.
    $enabled_conditions_fieldset = $assert_session->elementExists('named', ['fieldset', 'Enabled conditions'], $conditions_wrapper);
    $this->assertCount(2, $enabled_conditions_fieldset->findAll('css', 'input'));
    $assert_session->checkboxNotChecked('User test condition', $enabled_conditions_fieldset);
    $assert_session->checkboxNotChecked('User Role', $enabled_conditions_fieldset);

    // Each plugin comes with its own configuration form.
    $condition_settings_tabs_wrapper = $assert_session->elementExists(
      'xpath',
      $this->cssSelectToXpath('div.js-form-type-vertical-tabs') . '[./label[string(.)="Condition configuration"]]',
    );
    $this->assertCount(2, $condition_settings_tabs_wrapper->findAll('css', 'details'));

    // The first condition plugin is the user test one.
    $test_condition_wrapper = $this->assertDetailsElementExists('User test condition', $condition_settings_tabs_wrapper);
    $this->assertFalse($assert_session->fieldExists('Example configuration option', $test_condition_wrapper)->isChecked());
    // The second one is the user role core plugin.
    $user_role_condition_wrapper = $this->assertDetailsElementExists('User Role', $condition_settings_tabs_wrapper);
    $this->assertEquals([
      'anonymous',
      'authenticated',
      $role_id,
    ], array_map(
      function (NodeElement $element): string {
        $this->assertFalse($element->isChecked());
        return $element->getAttribute('value');
      },
      $user_role_condition_wrapper->findAll('css', 'input[name^="2fa_conditions[settings][user_role][roles]"]'),
    ));

    // When the 2FA mode is set to conditional, at least one condition is
    // required to be selected.
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('At least one condition should be enabled when two-factor authentication is set to conditional.', 'error');
    $this->assertTrue($enabled_conditions_fieldset->findField('User test condition')->hasClass('error'));
    $this->assertTrue($enabled_conditions_fieldset->findField('User Role')->hasClass('error'));

    // Test that configuration is required when enabling a condition.
    $enabled_conditions_fieldset->checkField('User test condition');
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('All condition plugins have been disabled, as no configuration was provided. Two-factor authentication has been set to "Never".', 'warning');
    $assert_session->statusMessageContains('The configuration options have been saved.', 'status');

    $config = $this->loadConfig();
    $this->assertEquals([], $config->get('2fa_conditions'));
    $this->assertFalse($config->get('force_2fa'));

    $this->drupalGet('/admin/config/system/oe_authentication');
    $conditional_radio->click();
    $this->assertNotNull($assert_session->waitForElementVisible('css', $conditions_wrapper_selector));

    // Set some configuration for a plugin.
    $test_condition_wrapper->checkField('Example configuration option');
    $test_condition_wrapper->checkField('Negate');

    // Test that validation is triggered for plugin forms.
    $enabled_conditions_fieldset->checkField('User test condition');
    $test_condition_wrapper->checkField('Do not click this');
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('You cannot select this field.', 'error');
    $assert_session->statusMessageNotExists('warning');
    $assert_session->statusMessageNotExists('status');
    $this->assertTrue($test_condition_wrapper->findField('Do not click this')->hasClass('error'));

    // No configuration has been saved.
    $config = $this->loadConfig();
    $this->assertEquals([], $config->get('2fa_conditions'));
    $this->assertFalse($config->get('force_2fa'));

    $test_condition_wrapper->uncheckField('Do not click this');
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('The configuration options have been saved.', 'status');
    $assert_session->statusMessageNotExists('warning');

    // The condition configuration has been saved.
    $config = $this->loadConfig();
    $this->assertFalse($config->get('force_2fa'));
    $this->assertEquals([
      'oe_authentication_user_test' => [
        'example' => TRUE,
        'negate' => TRUE,
        'id' => 'oe_authentication_user_test',
      ],
    ], $config->get('2fa_conditions'));

    // Test that only the conditions with a non-default configuration are
    // persisted.
    $enabled_conditions_fieldset->checkField('User Role');
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('The configuration options have been saved.', 'status');
    $assert_session->statusMessageContains('The following condition plugins have been disabled, as no configuration was provided: User Role', 'warning');
    $assert_session->checkboxNotChecked('User Role', $enabled_conditions_fieldset);

    $config = $this->loadConfig();
    $this->assertFalse($config->get('force_2fa'));
    $this->assertEquals([
      'oe_authentication_user_test' => [
        'example' => TRUE,
        'negate' => TRUE,
        'id' => 'oe_authentication_user_test',
      ],
    ], $config->get('2fa_conditions'));

    // Enable some settings for the user role plugin.
    $enabled_conditions_fieldset->checkField('User Role');
    $condition_settings_tabs_wrapper->clickLink('User Role');
    $user_role_condition_wrapper->checkField('Authenticated user');
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('The configuration options have been saved.', 'status');
    $assert_session->statusMessageNotExists('warning');

    $config = $this->loadConfig();
    $this->assertFalse($config->get('force_2fa'));
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

    // Configuration for a condition is purged when it's disabled.
    $this->drupalGet('/admin/config/system/oe_authentication');
    $enabled_conditions_fieldset->uncheckField('User test condition');
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('The configuration options have been saved.', 'status');
    $assert_session->statusMessageNotExists('warning');

    $config = $this->loadConfig();
    $this->assertFalse($config->get('force_2fa'));
    $this->assertEquals([
      'user_role' => [
        'id' => 'user_role',
        'negate' => FALSE,
        'roles' => [
          'authenticated' => 'authenticated',
        ],
      ],
    ], $config->get('2fa_conditions'));

    // Test that "Always" and "Never" completely remove existing condition
    // configuration.
    $always_radio->click();
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('The configuration options have been saved.', 'status');
    $assert_session->statusMessageNotExists('warning');

    $config = $this->loadConfig();
    $this->assertTrue($config->get('force_2fa'));
    $this->assertEquals([], $config->get('2fa_conditions'));

    // Re-enable a condition programmatically.
    \Drupal::configFactory()
      ->getEditable('oe_authentication.settings')
      ->set('2fa_conditions', [
        'user_role' => [
          'id' => 'user_role',
          'negate' => FALSE,
          'roles' => [
            'authenticated' => 'authenticated',
          ],
        ],
      ])
      ->set('force_2fa', FALSE)
      ->save();

    $this->drupalGet('/admin/config/system/oe_authentication');
    $this->assertTrue($conditional_radio->isChecked());
    $assert_session->checkboxChecked('User Role', $enabled_conditions_fieldset);
    $assert_session->checkboxChecked('Authenticated user', $user_role_condition_wrapper);

    $never_radio->click();
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('The configuration options have been saved.', 'status');
    $assert_session->statusMessageNotExists('warning');

    $config = $this->loadConfig();
    $this->assertFalse($config->get('force_2fa'));
    $this->assertEquals([], $config->get('2fa_conditions'));

    // Since we rely on a test module from core, and we also test that the
    // following plugins don't show in the UI, we won't detect if the plugins
    // have been removed. Add a safety check to look for their existence.
    // This doesn't prevent core from changing completely their context
    // definitions, but that's more unlikely.
    /** @var \Drupal\Component\Plugin\Discovery\DiscoveryInterface $condition_manager */
    $condition_manager = \Drupal::service('plugin.manager.condition');
    $this->assertTrue($condition_manager->hasDefinition('condition_test_optional_context'));
    $this->assertTrue($condition_manager->hasDefinition('condition_test_dual_user'));
  }

  /**
   * Returns the latest version of the oe_authentication config.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The loaded config.
   */
  protected function loadConfig(): ImmutableConfig {
    $this->refreshVariables();

    return \Drupal::config(AuthenticationSettingsForm::CONFIG_NAME);
  }

  /**
   * Returns a details HTML element given its summary.
   *
   * @param string $summary
   *   The summary element text content.
   * @param \Behat\Mink\Element\TraversableElement|null $container
   *   The container where to find the element. The whole page if NULL.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The node element.
   */
  protected function assertDetailsElementExists(string $summary, ?TraversableElement $container = NULL): NodeElement {
    $assert_session = $this->assertSession();
    $xpath = $assert_session->buildXPathQuery('//details[./summary[string(.)=:summary]]', [
      ':summary' => $summary,
    ]);

    return $assert_session->elementExists('xpath', $xpath, $container);
  }

}
