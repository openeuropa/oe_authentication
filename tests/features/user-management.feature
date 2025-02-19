@api
@DrupalLogin
Feature: Manage users
  Because users are managed externally
  I should not be able to delete users on the site
  Unless I am superuser user
  Or module configuration to restrict the user delete options is not enabled

  Scenario: Users should not be able to delete other users
    Given I am logged in as a user with the "administer users" permissions
    When I visit my user page
    And I click "Edit"
    And I click "Cancel account"
    Then I should see "Disable the account and keep its content."
    And I should see "Disable the account and unpublish its content."
    And I should not see "Delete the account"

  Scenario: As the superuser I can access the delete options
    Given users:
      | name     | mail         | roles        |
      | foo      | foo@bar.com  |              |
    And I am logged in as the superuser user
    When I visit the foo user page
    And I click "Edit"
    And I click "Cancel account"
    Then I should see "Disable the account and keep its content."
    And I should see "Disable the account and unpublish its content."
    And I should see "Delete the account and its content."
    And I should see "Delete the account and make its content belong to the Anonymous user."

  Scenario: Users should be able to delete other users when the configuration to restrict the user delete options is not enabled.
    Given the site is configured to not restrict user delete options
    And I am logged in as a user with the "administer users" permissions
    When I visit my user page
    And I click "Edit"
    And I click "Cancel account"
    Then I should see "Disable the account and keep its content."
    And I should see "Disable the account and unpublish its content."
    And I should see "Delete the account and its content."
    And I should see "Delete the account and make its content belong to the Anonymous user."
