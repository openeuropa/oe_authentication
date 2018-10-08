@api
Feature: Login through OE Authentication
  In order to be able to access the CMS backend
  As user of the system
  I need to login through OE Authentication
  I need to be redirect back to the site

  Scenario: Login
    Given I am on the homepage
    Then I click "Log in"
    # We are redirected to the mock OE Authentication server at this point.
    Then I fill in "User" with "Dr. Lektroluv"
    Then I fill in "Nickname" with "The Man with the Green Mask"
    Then I fill in "Password" with "Em0lotion"
    And I press the "Submit" button
    # Redirected back to Drupal.
    Then I click "My account"
    And I should see text matching "Dr. Lektroluv"

