@redis_no_tags
Feature: TokenService with Redis No-Tags Adapter
  In order to prove that the TokenService works with online storage without tag support
  As a developer, a TokenService user
  I need to be able to create and consume tokens using Redis without tag support

  Background:
    Given I am using Redis without tag support for caching

  Scenario: It can create token with Redis no-tags adapter
    Given I have type "type" and payload "payload"
    When I create token using type and payload
    Then I should have token created

  Scenario: It can not consume token with wrong uid in Redis no-tags adapter
    Given I create token with type "type" and payload "payload"
    When I consume token with empty uid
    Then I should have null returned instead of consumed token

  Scenario: It can consume token with Redis no-tags adapter
    Given I create token with type "type" and payload "payload"
    When I consume token with uid from token
    Then I should have instance of TokenInterface returned and removed from cache

  Scenario: It can consume token and keep in cache with Redis no-tags adapter
    Given I create token with type "type" and payload "payload"
    When I consume token with uid from token and keep token "true"
    Then I should have instance of TokenInterface returned and kept in cache

  Scenario: It can not consume token when ttl is set with Redis no-tags adapter
    Given I create token with type "type" and payload "payload" and ttl "1"
    When I consume token with uid from token
    Then I should have null returned instead of consumed token

  Scenario: It can clear all tokens with full cache clear in Redis no-tags adapter
    Given I create multiple tokens of different types
    When I clear all tokens
    Then all tokens should be cleared from cache
    And non-tag-aware clearing should work with full cache clear

  Scenario: Non-tag-aware clearing works with multiple tokens
    Given I create token with type "type1" and payload "payload1"
    And I create token with type "type2" and payload "payload2"
    And I create token with type "type3" and payload "payload3"
    When I clear all tokens
    Then all tokens should be cleared from cache
    And non-tag-aware clearing should work with full cache clear
