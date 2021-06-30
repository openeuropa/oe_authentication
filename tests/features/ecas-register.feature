@api @casMockServer
Feature: Register through OE Authentication
  In order to be able to have new users
  As an anonymous user of the system
  I need to be able to go to the registration URL

  Background:
    Given CAS users:
      | Username    | E-mail                            | Password           | First name | Last name | Department    | Organisation |
      | jb007       | 007@mi6.eu                        | shaken_not_stirred | James      | Bond      | DIGIT.A.3.001 | external     |

  Scenario: Register
    Given I am an anonymous user
    When I visit "cas-mock-server/eim/external/register.cgi"
    # Redirected to the Ecas mockup server.
    Then I should see "Create an account"
    And I should see "This page is part of the EU login mock."

  @cleanup:user
  Scenario: Register new user with AutoRegister enabled
    Given the site is configured to register users if not exists
    When I am on the homepage
    And I click "Log in"
    # Redirected to the mock server.
    And I fill in "Username or e-mail address" with "007@mi6.eu"
    And I fill in "Password" with "shaken_not_stirred"
    And I press the "Login!" button
    # Redirected back to Drupal.
    Then I should see "Your account is currently pending approval by the site administrator."
    And I should see the link "Log in"

  @cleanup:user
  Scenario: Register with AutoRegister enabled a user with already existent e-mail
    Given the site is configured to register users if not exists
    Given a user with the same email already exists locally
    When I am on the homepage
    And I click "Log in"
    # Redirected to the mock server.
    And I fill in "Username or e-mail address" with "007@mi6.eu"
    And I fill in "Password" with "shaken_not_stirred"
    And I press the "Login!" button
    # Redirected back to Drupal.
    Then I should see "A user with this mail already exists. Please contact with your site administrator."
    And I should see the link "Log in"
