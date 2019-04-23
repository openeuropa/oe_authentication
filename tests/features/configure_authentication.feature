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

    # Change the configuration values.
    When I fill in "Application authentication protocol" with "something"
    And I fill in "Application register path" with "test/something"
    And I fill in "Application validation path" with "validation/path"
    And I fill in "Application assurance levels" with "assurance"
    And I fill in "Application available ticket types" with "ticket.test"
    And I press "Save configuration"
    Then I should see the message "The configuration options have been saved."
    And the "Application authentication protocol" field should contain "something"
    And the "Application register path" field should contain "test/something"
    And the "Application validation path" field should contain "validation/path"
    And the "Application assurance levels" field should contain "assurance"
    And the "Application available ticket types" field should contain "ticket.test"
