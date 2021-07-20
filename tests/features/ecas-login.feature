@api @casMockServer
Feature: Login through OE Authentication
  In order to be able to access the CMS backend
  As user of the system
  I need to login through OE Authentication
  I need to be redirect back to the site

  Background:
    Given CAS users:
      | Username    | E-mail                            | Password           | First name | Last name | Department    | Organisation |
      | chucknorris | texasranger@chucknorris.com.eu    | Qwerty098          | Chuck      | Norris    | DIGIT.A.3.001 | eu.europa.ec |
      | jb007       | 007@mi6.eu                        | shaken_not_stirred | James      | Bond      | DIGIT.A.3.001 | external     |
      | lissa       | Lisbeth.SALANDER@ext.ec.europa.eu | dragon_tattoo      | Lisbeth    | Salander  |               |              |

  @cleanup:user
  Scenario: Login/Logout with eCAS mockup server of internal users
    Given the site is configured to make users active on creation
    When I am on the homepage
    And I click "Log in"
    # Redirected to the mock server.
    And I fill in "Username or e-mail address" with "texasranger@chucknorris.com.eu"
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

    # Profile contains extra fields.
    When I click "Edit"
    Then the "First Name" field should contain "Chuck"
    And the "Last Name" field should contain "NORRIS"
    And the "Department" field should contain "DIGIT.A.3.001"
    And the "Organisation" field should contain "eu.europa.ec"

    When I click "Log out"
    And I should see "You are about to be logged out of EU Login."
    And I should see the link "No, stay logged in!"
    # Redirected to the Ecas mockup server.
    And I press the "Log me out" button
    # Redirected back to Drupal.
    Then I should be on the homepage
    And I should see "You have logged out from EU Login."
    And I should not see the link "My account"
    And I should not see the link "Log out"
    And I should see the link "Log in"

  @cleanup:user @BackupAuthConfigs @AllowExternalLogin
  Scenario: Login/Logout with eCAS mockup server of external users
    Given the site is configured to make users active on creation
    When I am on the homepage
    And I click "Log in"
    # Redirected to the mock server.
    And I fill in "Username or e-mail address" with "007@mi6.eu"
    And I fill in "Password" with "shaken_not_stirred"
    And I press the "Login!" button
    # Redirected back to Drupal.
    Then I should see "You have been logged in."
    And I should see the link "My account"
    And I should see the link "Log out"
    And I should not see the link "Log in"

    # Redirected back to Drupal.
    When I click "My account"
    Then I should see the heading "jb007"

    # Profile contains extra fields.
    When I click "Edit"
    Then the "First Name" field should contain "James"
    And the "Last Name" field should contain "BOND"
    And the "Department" field should contain "DIGIT.A.3.001"
    And the "Organisation" field should contain "external"

    When I click "Log out"
    # Redirected to the Ecas mockup server.
    And I press the "Log me out" button
    # Redirected back to Drupal.
    Then I should be on the homepage
    And I should not see the link "My account"
    And I should not see the link "Log out"
    And I should see the link "Log in"

  @cleanup:user
  Scenario: A user's information should update every login
    # Login with an EULogin user.
    Given the site is configured to make users active on creation
    When I am on the homepage
    And I click "Log in"
    And I fill in "Username or e-mail address" with "texasranger@chucknorris.com.eu"
    And I fill in "Password" with "Qwerty098"
    And I press the "Login!" button
    And I click "My account"

    And I click "Edit"
    Then the "First Name" field should contain "Chuck"
    And the "Last Name" field should contain "NORRIS"
    And the "Department" field should contain "DIGIT.A.3.001"
    And the "Organisation" field should contain "eu.europa.ec"

    # Edit the details.
    When I fill in "First Name" with "New name"
    And I press "Save"
    Then I should see "The changes have been saved."

    When I click "Edit"
    Then the "First Name" field should contain "New name"
    And the "Last Name" field should contain "NORRIS"
    And the "Department" field should contain "DIGIT.A.3.001"
    And the "Organisation" field should contain "eu.europa.ec"

    # Logout.
    When I click "Log out"
    And I press the "Log me out" button
    Then I should be on the homepage

    # Login again and check the changed details.
    When I click "Log in"
    And I fill in "Username or e-mail address" with "texasranger@chucknorris.com.eu"
    And I fill in "Password" with "Qwerty098"
    And I press the "Login!" button
    And I click "My account"

    And I click "Edit"
    Then the "First Name" field should contain "Chuck"
    And the "Last Name" field should contain "NORRIS"
    And the "Department" field should contain "DIGIT.A.3.001"
    And the "Organisation" field should contain "eu.europa.ec"

    #Logout to continue scenarios.
    When I click "Log out"
    And I press the "Log me out" button
    Then I should be on the homepage

  @cleanup:user
  Scenario: A site that requires administration validation on users should block them by default
    # When I try to log in again I will be denied access.
    Given the site is configured to make users blocked on creation
    When I am on the homepage
    And I click "Log in"
    And I fill in "Username or e-mail address" with "Lisbeth.SALANDER@ext.ec.europa.eu"
    And I fill in "Password" with "dragon_tattoo"
    And I press the "Login!" button
    Then I should be on the homepage
    And I should see "Your account is blocked or has not been activated. Please contact a site administrator."
    And I should see "Thank you for applying for an account. Your account is currently pending approval by the site administrator."
    And I should see the link "Log in"

  @cleanup:user
  Scenario: Login user with an already registered email with auto-register enabled
    Given users:
      | name    | mail        |
      | james   | 007@mi6.eu  |
    And I am an anonymous user
    When I am on the homepage
    And I click "Log in"
    # Redirected to the mock server.
    And I fill in "Username or e-mail address" with "007@mi6.eu"
    And I fill in "Password" with "shaken_not_stirred"
    And I press the "Login!" button
    # Redirected back to Drupal.
    Then I should see the error message "A user with this email address already exists. Please contact the site administrator."
    And I should see the link "Log in"
