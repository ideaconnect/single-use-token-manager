@array
Feature: TokenService with ArrayAdapter
  In order to prove that the TokenService works with offline storage
  As a developer, a TokenService user
  I need to be able to create and consume tokens using ArrayAdapter

  Background:
    Given I am using ArrayAdapter for caching

  Scenario: It can create token with ArrayAdapter
    Given I have type "type" and payload "payload"
    When I create token using type and payload
    Then I should have token created

  Scenario: It can not consume token with wrong uid in ArrayAdapter
    Given I create token with type "type" and payload "payload"
    When I consume token with empty uid
    Then I should have null returned instead of consumed token

  Scenario: It can consume token with ArrayAdapter
    Given I create token with type "type" and payload "payload"
    When I consume token with uid from token
    Then I should have instance of TokenInterface returned and removed from cache

  Scenario: It can consume token and keep in cache with ArrayAdapter
    Given I create token with type "type" and payload "payload"
    When I consume token with uid from token and keep token "true"
    Then I should have instance of TokenInterface returned and kept in cache

  Scenario: It can not consume token when ttl is set with ArrayAdapter
    Given I create token with type "type" and payload "payload" and ttl "1"
    When I consume token with uid from token
    Then I should have null returned instead of consumed token

  Scenario: It can clear all tokens with ArrayAdapter
    Given I create token with type "type1" and payload "payload1"
    When I clear all tokens
    Then all tokens should be cleared from cache
