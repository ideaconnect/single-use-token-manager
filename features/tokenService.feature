Feature: TokenService
  In order to prove that the TokenService is working properly
  As a developer, a TokenService user
  I need to be able to create and consume token

  Scenario: It can create token
    Given I have type "type" and payload "payload"
    When I create token using type and payload
    Then I should have token created

  Scenario: It can not consume token
    Given I create token with type "type" and payload "payload"
    When I consume token with empty uid
    Then I should have null returned instead of consumed token

  Scenario: It can consume token
    Given I create token with type "type" and payload "payload"
    When I consume token with uid from token
    Then I should have instance of TokenInterface returned and removed from cache

  Scenario: It can consume token and keep in cache
    Given I create token with type "type" and payload "payload"
    When I consume token with uid from token and keep token "true"
    Then I should have instance of TokenInterface returned and kept in cache

  Scenario: It can not consume token when ttl is set
    Given I create token with type "type" and payload "payload" and ttl "1"
    When I consume token with uid from token
    Then I should have null returned instead of consumed token