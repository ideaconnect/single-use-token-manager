Feature: Token
  In order to prove that the Token is working properly
  As a developer, a Token user
  I need to be able to get uid, type, payload

  Scenario: It can set uid with empty payload
    Given I have type "type" with empty payload
    When I construct token with "type"
    Then I should have uid returned
    And I should have empty payload

  Scenario: It should not create token
    Given I have empty type with payload "payload"
    When I construct token with empty type
    Then I should not get token

  Scenario: It should return type and payload
    Given I have type "type" with payload "payload"
    When I construct token with type "type" and payload "payload"
    Then I should have value under type and payload
