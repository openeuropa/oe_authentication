@javascript
Feature: Login through OE Authentication
  In order to be able to access the CMS backend
  As user of the system
  I need to login through OE Authentication
  I need to be redirect back to the site

  Scenario: Login/Logout with eCAS mockup server
    When I am on the homepage
    And I click "Log in"
    And I click "European Commission"

    # Redirected to the mock server.
    And I fill in "Username or e-mail address" with "texasranger@chuck_norris.com.eu"
    And I fill in "Password" with "Qwerty098"
    And I press the "Login!" button

    # Redirected back to Drupal.
    Then I should see "You have been logged in."
    And I should see the link "My account"
    And I should see the link "Log out"
    And I should not see the link "Log in"
    # Redirected back to Drupal.

    When I click "My account"
    Then I should see the heading "chucknorris"

    When I click "Log out"
    # Redirected to the Ecas mockup server.
    And I press the "Log me out" button

    # Redirected back to Drupal.
    Then I should be on the homepage
    And I should not see the link "My account"
    And I should not see the link "Log out"
    And I should see the link "Log in"

  Scenario: A blocked user should not be able to log in
    Given the user "chucknorris" is blocked
    When I am on the homepage
    Then I should see the link "Log in"
    And I should not see the link "Log out"

    # When I try to log in again I will be denied access.
    When I click "Log in"
    And I fill in "Username or e-mail address" with "texasranger@chuck_norris.com.eu"
    And I fill in "Password" with "Qwerty098"
    And I press the "Login!" button
    Then I should see "There was a problem logging in, please contact a site administrator."
