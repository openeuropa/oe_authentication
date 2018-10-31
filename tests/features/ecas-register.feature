@javascript
Feature: Register through OE Authentication
  In order to be able to have new users
  As an anonymous user of the system
  I need to be able to go to the registration URL

  Scenario: Register
    Given I am an anonymous user
    And I visit "/user/register"

    # Redirected to the Ecas mockup server.
    # @TODO: create scenarios according bussiness cases.
    Then I should see the heading "Unauthorised Access"
