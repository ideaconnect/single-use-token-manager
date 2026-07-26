Feature: Token
  In order to hand a user something they can redeem exactly once
  As a developer using the library
  I need a token that carries a unique identifier, a type and a payload

  Scenario: A token carries the type and payload it was built with
    Given a token type of "reset"
    And a payload of "user-7"
    When I construct the token
    Then I should get a token
    And the token type should be "reset"
    And the token payload should be "user-7"

  Scenario: A token may carry no payload at all
    Given a token type of "verify"
    And no payload
    When I construct the token
    Then I should get a token
    And the token should carry no payload

  Scenario: A token identifies itself with an unguessable uuid
    Given a token type of "reset"
    When I construct the token
    Then the token identifier should be a version 4 uuid

  Scenario: Two tokens of the same type are still told apart
    Given a token type of "reset"
    And a payload of "user-7"
    When I construct the token
    And I construct a second token of the same type
    Then the two tokens should have different identifiers

  Scenario Outline: A usable type is accepted
    Given a token type of "<type>"
    When I construct the token
    Then I should get a token
    And the token type should be "<type>"

    Examples:
      | type             |
      | a                |
      | 1                |
      | reset            |
      | reset2fa         |
      | abcdefghij123456 |

  Scenario Outline: An unusable type stops the token from existing
    Given a token type of "<type>"
    When I construct the token
    Then I should not get a token
    And the construction should be refused
    And the refusal should name the rejected type

    Examples:
      | type              | why                        |
      |                   | empty                      |
      | abcdefghij1234567 | one character too long     |
      | Reset             | uppercase is not allowed   |
      | pass_reset        | underscore is not allowed  |
      | pass-reset        | dash is not allowed        |
      | pass reset        | space is not allowed       |
      | zażółć            | multibyte is not allowed   |
