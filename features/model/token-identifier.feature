Feature: Token identifier
  In order to redeem a token over an API
  As a developer using the library
  I need the incoming identifier to be validated and serialised for me

  Scenario: A plausible identifier is accepted
    Given an identifier holding "1efb1c4e-0f7a-6c1a-9b2f-0242ac120002"
    When I validate it
    Then it should be accepted

  Scenario: An empty identifier is rejected
    Given an empty identifier
    When I validate it
    Then it should be rejected
    And the complaint should be about the token property

  Scenario: An identifier made only of whitespace is rejected
    Given an identifier of whitespace only
    When I validate it
    Then it should be rejected
    And the complaint should be about the token property

  Scenario: An identifier that was never filled in is rejected
    Given an identifier that was never filled in
    When I validate it
    Then it should be rejected

  Scenario: An identifier goes out as json under the name token
    Given an identifier holding "1efb1c4e-0f7a-6c1a-9b2f-0242ac120002"
    When I serialise it
    Then the json should be '{"token":"1efb1c4e-0f7a-6c1a-9b2f-0242ac120002"}'

  Scenario: An identifier is read back from a json request body
    When I deserialise the request body '{"token":"1efb1c4e-0f7a-6c1a-9b2f-0242ac120002"}'
    Then the identifier should hold "1efb1c4e-0f7a-6c1a-9b2f-0242ac120002"

  Scenario: An identifier read from a request body is validated like any other
    When I deserialise the request body '{"token":""}'
    And I validate it
    Then it should be rejected

  Scenario: An identifier survives the round trip through json
    Given an identifier holding "round-trip"
    When I serialise it
    And I deserialise the request body '{"token":"round-trip"}'
    Then the identifier should hold "round-trip"
