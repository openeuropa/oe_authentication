@api
Feature: Manage users
  Because users are managed externally
  I should not be able to delete users on the site

  @DrupalLogin
  Scenario: Users should not be able to delete other users
    Given users:
      | name |
      | jens |

    # Privileged user that can administer other users.
    Given I am logged in as a user with the "administer users" permissions

    # Check the user account.
    When I visit my user page
    And I click "Edit"
    And I press "Cancel account"
    Then I should see "Disable the account and keep its content."
    And I should see "Disable the account and unpublish its content."
    And I should see "Delete the account and make its content belong to the Anonymous user."
    And I should see "Delete the account and its content."

    # Check the administrative page.
    Given I visit "/admin/people"
    When I check "jens"
    And I select "Cancel the selected user account(s)" from "Action"
    And I press "Apply to selected items"
    Then I should see "Disable the account and keep its content."
    And I should see "Disable the account and unpublish its content."
    And I should see "Delete the account and make its content belong to the Anonymous user."
    And I should see "Delete the account and its content."

    # Regular user that is able to cancel its account and choose the method.
    Given I am logged in as a user with the "cancel account,select account cancellation method" permissions
    When I visit my user page
    And I click "Edit"
    And I press "Cancel account"
    Then I should see "Disable the account and keep its content."
    And I should see "Disable the account and unpublish its content."
    But I should not see "Delete the account and make its content belong to the Anonymous user."
    And I should not see "Delete the account and its content."
