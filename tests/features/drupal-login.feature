@api
Feature: Login through Drupal
  If configured properly
  I can access the CMS backend through Drupal

  @DrupalLogin
  Scenario: If configured properly I can access the CMS backend through Drupal
    Given I am logged in as a user with the "authenticated" role
    When I am on homepage
    Then I should see the link "Log out"

    When I click "Log out"
    Then I should be on homepage
    And I should not see the link "Log out"
    And I should see the link "Log in"
