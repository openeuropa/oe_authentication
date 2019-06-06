@api @javascript
Feature: As an on a site that requires registration admin approval, when
  configuring the EU Login, I want to be able to decide whether users registered
  via EU Login are active or blocked.

  @cleanup:user @DrupalLogin @BackupAuthConfigs
  Scenario: A site that requires administration validation on users should block
    them by default

    Given the site is configured to make users blocked on creation
    And I am an anonymous user
    When I am on the homepage
    And I click "Log in"
    And I click "EU Login"
    And I click "European Commission"
    And I fill in "Username or e-mail address" with "Lisbeth.SALANDER@ext.ec.europa.eu"
    And I fill in "Password" with "dragon_tattoo"
    And I press the "Login!" button
    Then I should be on the homepage
    And I should see "Your account is blocked or has not been activated. Please contact a site administrator."
    And I should see "Thank you for applying for an account. Your account is currently pending approval by the site administrator."
    And I should see the link "Log in"
    # Logout from EU Login.
    And I click "Log in"
    And I click "EU Login"
    And I click "Logout"

    Given I am logged in as a user with the "administer authentication configuration" permission
    When I am on "the Authentication configuration page"
    And I uncheck "Block newly created users if the site requires admin approval"
    Then I press the "Save configuration" button

    Given I am an anonymous user
    When I am on the homepage
    And I click "Log in"
    And I click "EU Login"
    And I click "European Commission"
    And I fill in "Username or e-mail address" with "texasranger@chucknorris.com.eu"
    And I fill in "Password" with "Qwerty098"
    And I press the "Login!" button
    Then I should be on the homepage
    And I should see the link "Log out"
