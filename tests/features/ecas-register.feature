@api @casMockServer
Feature: Register through OE Authentication
  In order to be able to have new users
  As an anonymous user of the system
  I need to be able to go to the registration URL

  Scenario: Register
    Given I am an anonymous user
    When I visit "the user registration page"
    # Redirected to the Ecas mockup server.
    Then I should see "Create an account"
    And I should see "This page is part of the EU login mock."

  @cleanup:user
  Scenario: Login user with auto-register enabled
    Given CAS users:
      | Username    | E-mail                            | Password           | First name | Last name | Department    | Organisation |
      | jb007       | 007@mi6.eu                        | shaken_not_stirred | James      | Bond      | DIGIT.A.3.001 | external     |
    And I am an anonymous user
    When I am on the homepage
    And I click "Log in"
    # Redirected to the mock server.
    And I fill in "Username or e-mail address" with "007@mi6.eu"
    And I fill in "Password" with "shaken_not_stirred"
    And I press the "Login!" button
    # Redirected back to Drupal.
    Then I should see the success message "Your account is currently pending approval by the site administrator."
    And I should see the link "Log in"

  @cleanup:user
  Scenario: Login user with an already registered email with auto-register enabled
    Given users:
      | name    | mail        |
      | james   | 007@mi6.eu  |
    And CAS users:
      | Username    | E-mail         | Password           | First name | Last name | Department    | Organisation |
      | jb007       | 007@mi6.eu     | shaken_not_stirred | James      | Bond      | DIGIT.A.3.001 | external     |
    And I am an anonymous user
    When I am on the homepage
    And I click "Log in"
    # Redirected to the mock server.
    And I fill in "Username or e-mail address" with "007@mi6.eu"
    And I fill in "Password" with "shaken_not_stirred"
    And I press the "Login!" button
    # Redirected back to Drupal.
    Then I should see the error message "A user with this email address already exists. Please contact the site administrator."
    And I should see the link "Log in"
