<?php

namespace unit\LQA;

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\LQA\QA\ErrObject;

class ErrObjectTest extends AbstractTest
{
    #[Test]
    public function getWithAllFields(): void
    {
        $errors = [
            'outcome' => 1000,
            'debug' => 'Tag mismatch.',
            'tip' => 'Press alt + t to add tags.'
        ];

        $errObj = ErrObject::get($errors);

        $this->assertInstanceOf(ErrObject::class, $errObj);
        $this->assertEquals(1000, $errObj->outcome);
        $this->assertEquals('Tag mismatch.', $errObj->debug);
        $this->assertEquals('Press alt + t to add tags.', $errObj->tip);
    }

    #[Test]
    public function getWithoutTip(): void
    {
        $errors = [
            'outcome' => 5,
            'debug' => 'Heading whitespaces mismatch'
        ];

        $errObj = ErrObject::get($errors);

        $this->assertEquals(5, $errObj->outcome);
        $this->assertEquals('Heading whitespaces mismatch', $errObj->debug);
        $this->assertEquals('', $errObj->tip);
    }

    #[Test]
    public function getOrigDebug(): void
    {
        $errors = [
            'outcome' => 1000,
            'debug' => 'Original debug message',
            'tip' => 'Some tip'
        ];

        $errObj = ErrObject::get($errors);

        // Modify debug
        $errObj->debug = 'Modified debug ( 3 )';

        // Original debug should remain unchanged
        $this->assertEquals('Original debug message', $errObj->getOrigDebug());
        $this->assertEquals('Modified debug ( 3 )', $errObj->debug);
    }

    #[Test]
    public function getTip(): void
    {
        $errors = [
            'outcome' => 3000,
            'debug' => 'Characters limit exceeded',
            'tip' => 'Maximum characters limit exceeded.'
        ];

        $errObj = ErrObject::get($errors);

        $this->assertEquals('Maximum characters limit exceeded.', $errObj->getTip());
    }

    #[Test]
    public function testToString(): void
    {
        $errors = [
            'outcome' => 1000,
            'debug' => 'Tag mismatch.'
        ];

        $errObj = ErrObject::get($errors);

        $this->assertEquals('1000', (string)$errObj);
    }

    #[Test]
    public function toStringWithZeroOutcome(): void
    {
        $errors = [
            'outcome' => 0,
            'debug' => 'No error'
        ];

        $errObj = ErrObject::get($errors);

        $this->assertEquals('0', (string)$errObj);
    }

    #[Test]
    public function jsonEncode(): void
    {
        $errors = [
            'outcome' => 1000,
            'debug' => 'Tag mismatch.',
            'tip' => 'Fix the tags'
        ];

        $errObj = ErrObject::get($errors);
        $json = json_encode($errObj);
        $decoded = json_decode($json, true);

        $this->assertEquals(1000, $decoded['outcome']);
        $this->assertEquals('Tag mismatch.', $decoded['debug']);
        $this->assertEquals('Fix the tags', $decoded['tip']);
    }

    #[Test]
    public function publicPropertiesAreModifiable(): void
    {
        $errObj = ErrObject::get([
            'outcome' => 1000,
            'debug' => 'Original'
        ]);

        $errObj->outcome = 2000;
        $errObj->debug = 'Modified';
        $errObj->tip = 'New tip';

        $this->assertEquals(2000, $errObj->outcome);
        $this->assertEquals('Modified', $errObj->debug);
        $this->assertEquals('New tip', $errObj->tip);
    }

    #[Test]
    public function nullOutcome(): void
    {
        $errors = [
            'outcome' => null,
            'debug' => 'Some debug'
        ];

        $errObj = ErrObject::get($errors);

        $this->assertNull($errObj->outcome);
        $this->assertEquals('', (string)$errObj);
    }
}

