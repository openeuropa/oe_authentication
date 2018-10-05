@api
Feature: Login through Drupal
  If configured properly
  I can access the CMS backend through Drupal

  Scenario: If configured properly I can access the CMS backend through Drupal
    Given the site is configured to use Drupal login
    And I am logged in as a user with the "authenticated" role
    Then I should see the link "Log out"
    When I click "Log out"
    Then I should not see the link "Log out"
