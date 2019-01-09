@api
Feature: Manage users
  In order to manage the users of the site
  As an administrator with the appropriate rights
  I need to be able to edit and delete users

  Scenario: A user with the appropriate rights can delete users.
    Given the site is configured to use Drupal login
    And I am logged in as a user with the "administer users, delete user" permissions
    When I visit my user page
    And I click "Edit"
    And I press "Cancel account"
    Then I should see "Delete the account and its content."

  Scenario: A user without the appropriate rights can't delete users.
    Given the site is configured to use Drupal login
    And I am logged in as a user with the "administer users" permissions
    When I visit my user page
    And I click "Edit"
    And I press "Cancel account"
    Then I should not see "Delete the account and its content."