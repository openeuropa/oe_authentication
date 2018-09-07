Feature: Register through OE Authentication
  In order to be able to have new users
  As an anonymous user of the system
  I need to be able to go to the registration URL

  Scenario: Register
    Given I am an anonymous user
    And I visit "/user/register"
    # We are redirected to the mock OE Authentication server at this point.
    Then print last response
    Then I should see "This is an empty registration page."

