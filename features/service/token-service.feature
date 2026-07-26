Feature: Token service
  In order to let a user complete an action exactly once
  As a developer using the library
  I need to issue tokens into a cache and redeem them from it

  Every scenario below runs unchanged against each cache the suites configure,
  because the service is meant to need nothing beyond PSR-16. The only
  scenarios that differ are the ones about clearing, which is where a tagging
  cache and a plain one genuinely part ways.

  Scenario: Issuing a token returns it with an identifier
    When I create a token of type "reset"
    Then I should get a token
    And the token identifier should be a version 4 uuid

  Scenario: A token can be issued under an identifier the caller chose
    When I create a token of type "reset" identified by "reset.42"
    Then I should get a token
    And the token identifier should be "reset.42"

  Scenario: A token issued under a chosen identifier is redeemed by it
    Given I have created a token of type "reset" identified by "reset.42" with payload "user-7"
    When I redeem the identifier "reset.42"
    Then I should get the token back
    And the redeemed token should carry the payload "user-7"
    And the redeemed token identifier should be "reset.42"

  Scenario: A chosen identifier is still spent exactly once
    Given I have created a token of type "reset" identified by "reset.42" with payload "user-7"
    When I redeem the identifier "reset.42"
    Then I should get the token back
    When I redeem the identifier "reset.42"
    Then I should get nothing back

  Scenario: Re-issuing under the same identifier replaces what was there
    Given I have created a token of type "reset" identified by "reset.42" with payload "first"
    And I have created a token of type "reset" identified by "reset.42" with payload "second"
    When I redeem the identifier "reset.42"
    Then I should get the token back
    And the redeemed token should carry the payload "second"
    When I redeem the identifier "reset.42"
    Then I should get nothing back

  Scenario: Two callers choosing different identifiers do not collide
    Given I have created a token of type "reset" identified by "reset.42" with payload "user-42"
    And I have created a token of type "reset" identified by "reset.43" with payload "user-43"
    When I redeem the identifier "reset.42"
    Then the redeemed token identifier should be "reset.42"
    And the redeemed token should carry the payload "user-42"
    When I redeem the identifier "reset.43"
    Then the redeemed token identifier should be "reset.43"
    And the redeemed token should carry the payload "user-43"

  Scenario Outline: An identifier the cache could not store is refused up front
    When I create a token of type "reset" identified by the invalid identifier "<uid>"
    Then the creation should be refused

    Examples:
      | uid       | why                        |
      |           | empty                      |
      | reset:42  | colon is reserved          |
      | reset/42  | forward slash is reserved  |
      | reset@42  | at sign is reserved        |
      | reset{42  | brace is reserved          |

  Scenario: A token comes back with the type and payload it was issued with
    Given I have created a token of type "reset" with payload "user-7"
    When I redeem the token
    Then I should get the token back
    And the redeemed token should be of type "reset"
    And the redeemed token should carry the payload "user-7"

  Scenario: A token issued without a payload comes back without one
    Given I have created a token of type "verify"
    When I redeem the token
    Then I should get the token back
    And the redeemed token should carry no payload

  Scenario: A structured payload survives the trip through the cache
    Given I have created a token of type "session" carrying a user identifier of 7
    When I redeem the token
    Then I should get the token back
    And the redeemed token should carry a user identifier of 7

  Scenario: A token can only be redeemed once
    Given I have created a token of type "reset" with payload "user-7"
    When I redeem the token
    Then I should get the token back
    And the token should no longer be redeemable

  Scenario: A token can be inspected without being spent
    Given I have created a token of type "reset" with payload "user-7"
    When I redeem the token keeping it in the cache
    Then I should get the token back
    And the token should still be redeemable

  Scenario: An identifier nobody issued redeems to nothing
    Given I have created a token of type "reset"
    When I redeem the identifier "no-such-token"
    Then I should get nothing back

  Scenario: Redeeming against an empty cache gives nothing
    When I redeem the identifier "no-such-token"
    Then I should get nothing back

  Scenario: A token past its lifetime is gone
    Given I have created a token of type "reset" lasting 1 second
    When I wait for the token to expire
    And I redeem the token
    Then I should get nothing back

  Scenario: A token within its lifetime is still there
    Given I have created a token of type "reset" lasting 30 seconds
    When I redeem the token
    Then I should get the token back

  Scenario: Every issued token gets its own identifier
    Given I have created 5 tokens of type "reset"
    Then every token identifier should be different

  Scenario: An unusable type is refused before anything is cached
    When I create a token of the invalid type "NOT VALID"
    Then the creation should be refused

  Scenario: Clearing drops every token at once
    Given I have created 3 tokens of type "reset"
    When I clear all tokens
    Then no token should be redeemable

  Scenario: Clearing an empty cache is harmless
    When I clear all tokens
    Then I should get nothing back

  @tagging
  Scenario: A tagging cache clears tokens without touching anything else
    Given the cache also holds an unrelated entry "unrelated_key"
    And I have created 3 tokens of type "reset"
    When I clear all tokens
    Then no token should be redeemable
    And the unrelated entry "unrelated_key" should still be there

  @untagged
  Scenario: A plain cache has no way to clear tokens alone
    Given the cache also holds an unrelated entry "unrelated_key"
    And I have created 3 tokens of type "reset"
    When I clear all tokens
    Then no token should be redeemable
    And the unrelated entry "unrelated_key" should be gone
