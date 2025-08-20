<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

use Praetorian\TokenService\TokenIdentifier;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

echo "=== Testing TokenIdentifier with Dependencies ===\n\n";

// Test 1: Basic functionality
echo "--- Test 1: Basic TokenIdentifier ---\n";
$tokenIdentifier = new TokenIdentifier();
$tokenIdentifier->token = 'test-token-123';
echo "Token set: " . $tokenIdentifier->token . "\n\n";

// Test 2: Serialization
echo "--- Test 2: Serialization ---\n";
$encoders = [new JsonEncoder()];
$normalizers = [new ObjectNormalizer()];
$serializer = new Serializer($normalizers, $encoders);

$jsonContent = $serializer->serialize($tokenIdentifier, 'json');
echo "Serialized JSON: " . $jsonContent . "\n";

$deserializedTokenIdentifier = $serializer->deserialize($jsonContent, TokenIdentifier::class, 'json');
echo "Deserialized token: " . $deserializedTokenIdentifier->token . "\n\n";

// Test 3: Validation
echo "--- Test 3: Validation ---\n";
$validator = Validation::createValidatorBuilder()
    ->enableAttributeMapping()
    ->getValidator();

// Valid token
$validToken = new TokenIdentifier();
$validToken->token = 'valid-token';
$violations = $validator->validate($validToken);
echo "Valid token violations: " . count($violations) . "\n";

// Invalid token (empty)
$invalidToken = new TokenIdentifier();
$invalidToken->token = '';
$violations = $validator->validate($invalidToken);
echo "Invalid token (empty) violations: " . count($violations) . "\n";
if (count($violations) > 0) {
    foreach ($violations as $violation) {
        echo "  - " . $violation->getMessage() . "\n";
    }
}

echo "\n=== All dependencies working correctly! ===\n";
