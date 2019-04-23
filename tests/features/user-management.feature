@api
Feature: Manage users
  Because users are managed externally
  I should not be able to delete users on the site

  @DrupalLogin
  Scenario: Users should not be able to delete other users
    Given I am logged in as a user with the "administer users" permissions
    When I visit my user page
    And I click "Edit"
    And I press "Cancel account"
    Then I should see "Disable the account and keep its content."
    And I should not see "Delete the account and its content."
