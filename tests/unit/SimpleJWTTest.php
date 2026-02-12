<?php

use TestHelpers\AbstractTest;
use Utils\Tools\SimpleJWT;


/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 11/04/16
 * Time: 19.52
 *
 */
class SimpleJWTTest extends AbstractTest
{


    private string $secretKey;

    public function setUp(): void
    {
        $this->secretKey = 'test_secret_key';
    }

    public function testSignWithInjectedPrivateClaimsKeepsAllExceptExp(): void
    {
        $claims = [
            'iss' => 'issuer-123',
            'sub' => 'subject-abc',
            'aud' => 'audience-xyz',
            'exp' => 1111111111,      // will be overridden by sign()
            'nbf' => 1111111111,
            'jti' => 'token-id-1',
        ];

        $jwt = new SimpleJWT(
            $claims,
            'simple.jwt.claims',
            $this->secretKey,
            3600
        );

        $signed = $jwt->sign();

        // Build the expected payload: same as constructor claims,
        // but with the actual exp computed by sign()
        $expected = $claims;
        $this->assertArrayHasKey('exp', $signed['payload']);
        $expected['exp'] = $signed['payload']['exp'];

        $this->assertSame(
            $expected,
            array_intersect_key($signed['payload'], $expected),
            'Signed payload must equal the constructor payload for injected private claims, except for exp which is recomputed.'
        );
    }

    public function testSignWithInjectedPrivateClaimsKeepsAllButNbf(): void
    {
        $claims = [
            'iss' => 'issuer-123',
            'sub' => 'subject-abc',
            'aud' => 'audience-xyz',
            'nbf' => 9999999999999999999999,      // will throw an exception in sign() because it is invalid
            'jti' => 'token-id-1',
        ];

        $jwt = new SimpleJWT(
            $claims,
            'simple.jwt.claims',
            $this->secretKey,
            3600
        );

        $this->expectException(UnexpectedValueException::class);
        $jwt->sign();
    }

    public function testIsValid_withValidToken(): void
    {
        $jwt = new SimpleJWT(
            ['customClaim' => 'testValue'],
            'simple.jwt.claims',
            $this->secretKey,
            3600
        );

        $signedToken = $jwt->sign();
        $isValid = SimpleJWT::isValid($signedToken, $this->secretKey);

        $this->assertTrue($isValid, "The valid token should be recognized as valid.");
    }

    public function testIsValid_withTamperedSignature(): void
    {
        $jwt = new SimpleJWT(
            ['customClaim' => 'testValue'],
            'simple.jwt.claims',
            $this->secretKey,
            3600
        );

        $signedToken = $jwt->sign();
        $signedToken['signature'] = 'tampered_signature';

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Invalid Token Signature");

        SimpleJWT::isValid($signedToken, $this->secretKey);
    }

    public function testIsValid_withExpiredToken(): void
    {
        $jwt = new SimpleJWT(
            ['customClaim' => 'testValue'],
            'simple.jwt.claims',
            $this->secretKey,
            -3600 // Negative TTL to create an expired token
        );

        $signedToken = $jwt->sign();

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("Token Expired");

        SimpleJWT::isValid($signedToken, $this->secretKey);
    }

    public function testIsValid_withNotYetValidToken(): void
    {
        $jwt = new SimpleJWT(
            ['customClaim' => 'testValue', 'nbf' => time() + 3600],
            'simple.jwt.claims',
            $this->secretKey,
            3600
        );

        $signedToken = $jwt->sign();

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("Token not valid yet");

        SimpleJWT::isValid($signedToken, $this->secretKey);
    }

    public function testIsValid_withValidNbf(): void
    {
        $jwt = new SimpleJWT(
            ['customClaim' => 'testValue', 'nbf' => time() - 3600],
            'simple.jwt.claims',
            $this->secretKey,
            3600
        );

        $signedToken = $jwt->sign();
        $this->assertTrue(SimpleJWT::isValid($signedToken, $this->secretKey));
        $this->assertTrue(SimpleJWT::isValid((string)$jwt, $this->secretKey));
    }

    public function testIsValid_withStringToken(): void
    {
        $jwt = new SimpleJWT(
            ['customClaim' => 'testValue'],
            'simple.jwt.claims',
            $this->secretKey,
            3600
        );

        $tokenString = $jwt->encode();
        $isValid = SimpleJWT::isValid($tokenString, $this->secretKey);

        $this->assertTrue($isValid, "The valid token (as a string) should be recognized as valid.");
    }

    public function testIsValid_withTamperedStringToken(): void
    {
        $jwt = new SimpleJWT(
            ['customClaim' => 'testValue'],
            'simple.jwt.claims',
            $this->secretKey,
            3600
        );

        $tokenString = $jwt->encode();

        $tamperPart = '1234-foo-bar-x';
        $tamperedTokenString = substr($tokenString, 0, -strlen($tamperPart)) . $tamperPart;

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Invalid Token Signature");

        SimpleJWT::isValid($tamperedTokenString, $this->secretKey);
    }

    public function testIsValid_withMissingClaims(): void
    {
        $jwt = new SimpleJWT(
            [],
            'simple.jwt.claims',
            $this->secretKey,
            3600
        );

        $signedToken = $jwt->sign();

        $isValid = SimpleJWT::isValid($signedToken, $this->secretKey);

        $this->assertTrue($isValid, "Tokens missing non-critical claims should still be valid.");
    }

    public function testEncryption()
    {
        $invited_by_uid = 166;
        $email = "domenico@translated.net";
        $request_info = ["team_id" => 1];

        $x = new SimpleJWT([
            'invited_by_uid' => $invited_by_uid,
            'email' => $email,
            'request_info' => $request_info,
        ]);

        $result = $x->sign();
        $this->assertArrayHasKey('signature', $result);
        $this->assertArrayHasKey('payload', $result);
        $this->assertArrayHasKey('exp', $result['payload']);
        $this->assertArrayHasKey('iat', $result['payload']);
        $this->assertTrue($result['payload'][$result['payload']['iss']]['invited_by_uid'] == $invited_by_uid);
        $this->assertTrue($result['payload'][$result['payload']['iss']]['email'] == $email);
        $this->assertTrue($result['payload'][$result['payload']['iss']]['request_info'] == $request_info);
    }

    public function testValidateTokenEncryption()
    {
        $invited_by_uid = 166;
        $email = "domenico@translated.net";
        $request_info = ["team_id" => 1];

        $x = new SimpleJWT([
            'invited_by_uid' => $invited_by_uid,
            'email' => $email,
            'request_info' => $request_info,
        ]);

        $result = $x->sign();
        $this->assertTrue($x->isValid($result));
    }

    public function testValidateTokenEncryptionByJWT()
    {
        $invited_by_uid = 166;
        $email = "domenico@translated.net";
        $request_info = ["team_id" => 1];

        $x = new SimpleJWT([
            'invited_by_uid' => $invited_by_uid,
            'email' => $email,
            'request_info' => $request_info,
        ]);

        $this->assertTrue($x->isValid($x->encode()));
    }


    public function testInvalidToken_tamper_field()
    {
        $invited_by_uid = 166;
        $email = "domenico@translated.net";
        $request_info = ["team_id" => 1];

        $x = new SimpleJWT([
            'invited_by_uid' => $invited_by_uid,
            'email' => $email,
            'request_info' => $request_info,
        ]);

        $result = $x->sign();

        //change a value param
        $result['payload']['context']['invited_by_uid'] = 123;

        //assert exception
        $this->expectException(DomainException::class);
        $x->isValid($result);
    }

    public function testInvalidToken_tamper_hash()
    {
        $invited_by_uid = 166;
        $email = "domenico@translated.net";
        $request_info = ["team_id" => 1];

        $x = new SimpleJWT([
            'invited_by_uid' => $invited_by_uid,
            'email' => $email,
            'request_info' => $request_info,
        ]);

        $result = $x->sign();

        //change a value param
        $result['signature'] = "376715df7403f293a019fab9d048e2a904216108fc85190dc824d35375f94bc9";

        //assert exception
        $this->expectException(DomainException::class);
        $x->isValid($result);
    }

    public function testArrayAccess()
    {
        $invited_by_uid = 166;
        $email = "domenico@translated.net";
        $request_info = ["team_id" => 1];

        $x = new SimpleJWT([
            'invited_by_uid' => $invited_by_uid,
            'email' => $email,
            'request_info' => $request_info,
        ]);

        $newEmail = "alex655321@a-clockwork-orange.net";

        //TEST ArrayAccess
        $x['email'] = $newEmail;
        $x['testAccess'] = "a new key/value pair";

        $result = $x->sign();
        $this->assertTrue($result['payload'][$result['payload']['iss']]['invited_by_uid'] == $invited_by_uid);
        $this->assertTrue($result['payload'][$result['payload']['iss']]['request_info'] == $request_info);


        //TEST ArrayAccess
        $this->assertTrue($result['payload'][$result['payload']['iss']]['email'] == $newEmail);
        $this->assertFalse($result['payload'][$result['payload']['iss']]['email'] == $email);
        $this->assertArrayHasKey('testAccess', $result['payload'][$result['payload']['iss']]);
        $this->assertEquals("a new key/value pair", $result['payload'][$result['payload']['iss']]['testAccess']);


        $this->assertTrue($x->isValid($result));
    }

    public function testAssignmentRuntime()
    {
        $invited_by_uid = 166;
        $email = "domenico@translated.net";
        $request_info = ["team_id" => 1];

        $x = new SimpleJWT();

        //TEST ArrayAccess
        $x['email'] = $email;
        $x['invited_by_uid'] = $invited_by_uid;
        $x['request_info'] = $request_info;
        $x['testAccess'] = "a new key/value pair";

        $result = $x->sign();
        $this->assertTrue($result['payload'][$result['payload']['iss']]['invited_by_uid'] == $invited_by_uid);
        $this->assertTrue($result['payload'][$result['payload']['iss']]['request_info'] == $request_info);


        //TEST ArrayAccess
        $this->assertEquals($result['payload'][$result['payload']['iss']]['email'], $email);
        $this->assertArrayHasKey('testAccess', $result['payload'][$result['payload']['iss']]);
        $this->assertEquals("a new key/value pair", $result['payload'][$result['payload']['iss']]['testAccess']);

        $this->assertTrue($x->isValid($x->encode()));
        $this->assertTrue($x->isValid($result));
    }

    public function testTimeToLive()
    {
        $invited_by_uid = 166;
        $email = "domenico@translated.net";
        $request_info = ["team_id" => 1];

        $x = new SimpleJWT([
            'invited_by_uid' => $invited_by_uid,
            'email' => $email,
            'request_info' => $request_info,
        ]);

        $x->setTimeToLive(1);

        $result = $x->encode();
        $this->assertTrue($x->isValid($result));

        //wait 2 seconds for token expire
        sleep(2);

        //assert exception
        $this->expectException(UnexpectedValueException::class);
        $x->isValid($result);
    }


    public function testSetTimeToLiveWithNegativeValueThrowsException(): void
    {
        $jwt = new SimpleJWT([], 'simple.jwt.claims', $this->secretKey, 3600);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Time To Live must be a positive integer');

        $jwt->setTimeToLive(-10);
    }

    public function testSetSecretKeyIsFluentAndUsedForSigning(): void
    {
        $jwt = new SimpleJWT(['foo' => 'bar']);

        $returned = $jwt->setSecretKey($this->secretKey);
        $this->assertSame($jwt, $returned, 'setSecretKey should be fluent');

        $signedToken = $jwt->sign();
        $this->assertTrue(SimpleJWT::isValid($signedToken, $this->secretKey));
    }

    public function testGetExpireDateUsesNowPlusTimeToLive(): void
    {
        $jwt = new SimpleJWT([], 'simple.jwt.claims', $this->secretKey, 100);

        $ref = new ReflectionClass($jwt);
        $nowProp = $ref->getProperty('now');
        $ttlProp = $ref->getProperty('timeToLive');

        $nowProp->setValue($jwt, 1000);
        $ttlProp->setValue($jwt, 200);

        $this->assertSame(1200, $jwt->getExpireDate());
    }

    public function testGetPayloadWithCustomNamespaceAndDefault(): void
    {
        $jwtCustom = new SimpleJWT(['foo' => 'bar'], 'my.custom.namespace', $this->secretKey, 3600);
        $payloadCustom = $jwtCustom->getPayload();
        $this->assertArrayHasKey('foo', $payloadCustom);
        $this->assertSame('bar', $payloadCustom['foo']);

        $jwtDefault = new SimpleJWT();
        $this->assertSame([], $jwtDefault->getPayload());
    }

    public function testArrayAccessExistsAndGetForPrivateAndCustomClaims(): void
    {
        $jwt = new SimpleJWT([], 'simple.jwt.claims', $this->secretKey, 3600);

        // private claim
        // test set and get magic methods
        $jwt['iss'] = 'my-issuer';
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertTrue(isset($jwt['iss']));
        $this->assertSame('my-issuer', $jwt['iss']);

        // custom claim
        // test set and get magic methods
        $jwt['custom_key'] = 'custom_value';
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertTrue(isset($jwt['custom_key']));
        $this->assertSame('custom_value', $jwt['custom_key']);

        // non-existing
        $this->assertFalse(isset($jwt['not_exists']));
        $this->assertNull($jwt['not_exists']);
    }

    public function testArrayAccessUnsetCustomClaim(): void
    {
        $jwt = new SimpleJWT([], 'simple.jwt.claims', $this->secretKey, 3600);

        $jwt['foo'] = 'bar';
        // test set and get magic methods

        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertTrue(isset($jwt['foo']));

        unset($jwt['foo']);
        // test set and get magic methods
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertFalse(isset($jwt['foo']));
        $this->assertNull($jwt['foo']);

        $signed = $jwt->sign();
        $namespace = $signed['payload']['iss'];
        $this->assertArrayNotHasKey('foo', $signed['payload'][$namespace] ?? []);
    }

    public function testArrayAccessUnsetPrivateClaimAlsoClearsNamespacedValue(): void
    {
        // Create a JWT with one custom claim ("x") under the "simple.jwt.claims" namespace.
        $jwt = new SimpleJWT(['x' => 1234], 'simple.jwt.claims', $this->secretKey, 3600);

        // Set the standard private claim "iss" via array access (top‑level payload field).
        $jwt['iss'] = 'my-issuer';

        // Ensure "iss" is now present and readable through ArrayAccess.
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertTrue(isset($jwt['iss']));
        $this->assertSame('my-issuer', $jwt['iss']);

        // Unset the private claim "iss".
        // This should remove:
        //  - the top-level payload["iss"] entry
        //  - any namespaced payload[namespace] entry
        unset($jwt['iss']);

        // After unsetting, "iss" must not be reported as set and should read back as null.
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertFalse(isset($jwt['iss']));
        $this->assertNull($jwt['iss']);

        // Generate a signed token to inspect the final payload structure.
        $signed = $jwt->sign();

        // The signer re‑populates payload["iss"] with the default namespace value.
        $this->assertArrayHasKey('iss', $signed['payload']);
        $this->assertEquals('my-issuer', $signed['payload']['iss']);

        // Use "iss" as the namespace key to look up custom claims.
        $namespace = $signed['payload']['iss'];

        // If a namespace payload section exists, it must *not* contain an "iss" field anymore.
        if (isset($signed['payload'][$namespace])) {
            $this->assertArrayNotHasKey('iss', $signed['payload'][$namespace]);
        }
    }

    public function testJsonSerializeReturnsSameAsEncode(): void
    {
        $jwt = new SimpleJWT(['foo' => 'bar'], 'simple.jwt.claims', $this->secretKey, 3600);

        $this->assertSame($jwt->encode(), $jwt->jsonSerialize());
    }

    public function testToStringReturnsCompactJwt(): void
    {
        $jwt = new SimpleJWT(['foo' => 'bar'], 'simple.jwt.claims', $this->secretKey, 3600);

        $this->assertSame($jwt->encode(), (string)$jwt);
    }

    public function testGetInstanceFromStringWithoutValidation(): void
    {
        $jwt = new SimpleJWT(['foo' => 'bar'], 'my.custom.namespace', $this->secretKey, 3600);

        $tokenString = $jwt->encode();
        $instance = SimpleJWT::getNotValidatedInstanceFromString($tokenString);

        $this->assertEquals($jwt->getPayload(), $instance->getPayload());
    }

    /**
     * @throws ReflectionException
     */
    public function testGetInstanceFromStringWithValidationClearsSignature(): void
    {
        $jwt = new SimpleJWT(['foo' => 'bar'], 'my.custom.namespace', $this->secretKey, 3600);

        $tokenString = $jwt->encode();
        $instance = SimpleJWT::getValidatedInstanceFromString($tokenString, $this->secretKey);

        $ref = new ReflectionClass($instance);
        $storageProp = $ref->getProperty('storage');
        $storage = $storageProp->getValue($instance);

        $this->assertArrayHasKey('signature', $storage);
        $this->assertNull($storage['signature']);
    }

    public function testIsValidWhenExpAndNbfAreMissing(): void
    {
        $jwt = new SimpleJWT(['foo' => 'bar'], 'simple.jwt.claims', $this->secretKey, 3600);
        $original = $jwt->sign();

        // Remove exp and nbf to cover the nullable-branch logic
        $storage = $original;
        unset($storage['payload']['exp'], $storage['payload']['nbf']);

        // Recompute signature for the modified payload
        $expectedHash = hash_hmac(
            'sha256',
            $this->base64UrlEncode(json_encode($storage['header'])) .
            '.' .
            $this->base64UrlEncode(json_encode($storage['payload'])),
            $this->secretKey,
            true
        );
        $storage['signature'] = $expectedHash;

        $this->assertTrue(SimpleJWT::isValid($storage, $this->secretKey));
    }

    /**
     * Helper for tests to mirror SimpleJWT::base64url_encode behavior.
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

}