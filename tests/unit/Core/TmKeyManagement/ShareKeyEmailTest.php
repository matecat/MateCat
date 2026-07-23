<?php

namespace Matecat\Core\TmKeyManagement;

use Matecat\TestHelpers\AbstractTest;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Utils\TmKeyManagement\ShareKeyEmail;
use Utils\TmKeyManagement\TmKeyStruct;

class ShareKeyEmailTest extends AbstractTest
{
    private function makeSender(): UserStruct
    {
        $user = new UserStruct();
        $user->uid = 1;
        $user->email = 'sender@example.com';
        $user->first_name = 'Ann';
        $user->last_name = 'Sender';

        return $user;
    }

    private function makeKeyStruct(?string $name): MemoryKeyStruct
    {
        $tmKey = new TmKeyStruct();
        $tmKey->key = 'somekeyvalue';
        $tmKey->name = $name;

        $keyStruct = new MemoryKeyStruct();
        $keyStruct->uid = 1;
        $keyStruct->tm_key = $tmKey;

        return $keyStruct;
    }

    #[Test]
    public function getTemplateVariablesReturnsExpectedKeys(): void
    {
        $email = new ShareKeyEmail($this->makeSender(), ['recipient@example.com', 'Recipient'], $this->makeKeyStruct('My Glossary'));
        $method = new ReflectionMethod(ShareKeyEmail::class, '_getTemplateVariables');
        $vars = $method->invoke($email);

        $this->assertSame('My Glossary', $vars['tm_key_name']);
        $this->assertSame('somekeyvalue', $vars['tm_key_value']);
        $this->assertSame('recipient@example.com', $vars['addressMail']);
    }

    /**
     * Regression: message_content.html used to echo tm_key_name (and the other
     * template variables) with no HTML escaping, so a key name containing raw
     * markup would be injected verbatim into the outgoing email. Every variable
     * must now be escaped by the template.
     */
    #[Test]
    public function buildMessageContentEscapesKeyNameContainingMarkup(): void
    {
        $email = new ShareKeyEmail($this->makeSender(), ['recipient@example.com', 'Recipient'], $this->makeKeyStruct('<script>alert(1)</script>'));
        $method = new ReflectionMethod(ShareKeyEmail::class, '_buildMessageContent');
        $html = $method->invoke($email);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
