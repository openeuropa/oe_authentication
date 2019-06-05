@api @ecas-login
Feature: Register through OE Authentication
  In order to be able to have new users
  As an anonymous user of the system
  I need to be able to go to the registration URL

  @DrupalLogin
  Scenario: Register
    Given I am an anonymous user
    When I visit "the user registration page"
    # Redirected to the Ecas mockup server.
    And I click "External"
    Then I should see "Create an account"

    Given I am logged in as a user with the "administer authentication configuration" permission
    When I am on "the Authentication configuration page"
    And I uncheck "Redirect user registration route to EU Login"
    Then I press "Save configuration"

    Given I am an anonymous user
    When I visit "the user registration page"
    Then I should see "Create new account"

    Given I am logged in as a user with the "administer authentication configuration" permission
    When I am on "the Authentication configuration page"
    And I check "Redirect user registration route to EU Login"
    Then I press "Save configuration"
