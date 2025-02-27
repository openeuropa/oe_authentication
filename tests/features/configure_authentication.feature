@api
Feature: Authentication
  As the site manager
  I need to be able to configure the settings

  Background:
    Given I am logged in as a user with the "administer authentication configuration" permission

  @DrupalLogin @BackupAuthConfigs
  Scenario: Configure Authentication settings
    When I am on "the Authentication configuration page"
    Then I should see "Authentication settings"
    # Check for the default config is there.
    And the "Application authentication protocol" field should contain "eulogin"
    And the "Application register path" field should contain "eim/external/register.cgi"
    And the "Application validation path" field should contain "TicketValidationService"
    And the "Application assurance levels" field should contain "TOP"
    And the "Application available ticket types" field should contain "SERVICE,PROXY"
    # Note: 2FA fields are tested in \Drupal\Tests\oe_authentication\Functional\SettingsFormTest.
    And the "Message for login rejected: two-factor authentication required" field should contain "You are required to log in using a two-factor authentication method."

    # Change the configuration values.
    When I fill in "Application authentication protocol" with "something"
    And I fill in "Application register path" with "test/something"
    And I fill in "Application validation path" with "validation/path"
    And I fill in "Application assurance levels" with "assurance"
    And I fill in "Application available ticket types" with "ticket.test"
    And I fill in "Message for login rejected: two-factor authentication required" with "Example message"
    And I press "Save configuration"
    Then I should see the message "The configuration options have been saved."
    And the "Application authentication protocol" field should contain "something"
    And the "Application register path" field should contain "test/something"
    And the "Application validation path" field should contain "validation/path"
    And the "Application assurance levels" field should contain "assurance"
    And the "Application available ticket types" field should contain "ticket.test"
    And the "Message for login rejected: two-factor authentication required" field should contain "Example message"
