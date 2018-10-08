@api
Feature: Logout through OE Authentication
  In order to be able to logout
  As user of the system
  I need to logout through OE Authentication
  I must be redirect back to the site

  Scenario: Logout
    Given I am on the homepage
    Then I click "Log in"

    # We are redirected to the mock OE Authentication server at this point.
    Then I fill in "User" with "Kevin"
    Then I fill in "Nickname" with "Kevin"
    Then I fill in "Password" with "Kevin"
    And I press the "Submit" button

    Then I click "Log out"
    When I am on the homepage
    Then I should not see the link "My account"
    And I should see the link "Log in"

