@api
Feature: Login through OE Auth
  In order to be able to access the CMS backend
  As user of the system
  I need to login through OE Auth

  Scenario: Login
    Given I am on the homepage
    Then I click "Log in"
    # We are redirected to the mock OE Auth server at this point.
    Then I fill in "User" with "Dr. Lektroluv"
    Then I fill in "Nickname" with "The Man with the Green Mask"
    Then I fill in "Password" with "Em0lotion"
    And I press the "Submit" button
    # Redirected back to Drupal.
    Then I click "My account"
    And I should see text matching "Dr. Lektroluv"

    Then I click "Log out"
    When I am on the homepage
    Then I should not see the link "My account"
    And I should see the link "Log in"

