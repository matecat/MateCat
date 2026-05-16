<?php

use Model\Outsource\TranslatedConfirmationStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class ConfirmationStructTypedPropertiesTest extends AbstractTest
{
    #[Test]
    public function struct_can_be_constructed_with_all_properties(): void
    {
        $struct = new TranslatedConfirmationStruct([
            'id' => 42,
            'id_job' => 100,
            'password' => 'abc123',
            'vendor_name' => 'Translated',
            'id_vendor' => 1,
            'create_date' => '2024-01-01 00:00:00',
            'delivery_date' => '2024-01-02 00:00:00',
            'currency' => 'USD',
            'price' => 99.50,
            'quote_pid' => 'qp-123',
        ]);

        $this->assertSame(42, $struct->id);
        $this->assertSame(100, $struct->id_job);
        $this->assertSame('abc123', $struct->password);
        $this->assertSame('Translated', $struct->vendor_name);
        $this->assertSame(1, $struct->id_vendor);
        $this->assertSame('2024-01-01 00:00:00', $struct->create_date);
        $this->assertSame('2024-01-02 00:00:00', $struct->delivery_date);
        $this->assertSame('USD', $struct->currency);
        $this->assertSame(99.50, $struct->price);
        $this->assertSame('qp-123', $struct->quote_pid);
    }

    #[Test]
    public function struct_nullable_properties_accept_null(): void
    {
        $struct = new TranslatedConfirmationStruct([
            'id' => null,
            'id_job' => 100,
            'password' => 'abc123',
            'quote_pid' => null,
        ]);

        $this->assertNull($struct->id);
        $this->assertNull($struct->quote_pid);
    }

    #[Test]
    public function struct_defaults_are_correct(): void
    {
        $struct = new TranslatedConfirmationStruct();

        // vendor_name default comes from parent ConfirmationStruct::VENDOR_NAME = ''
        // (self:: resolves at definition site, not child)
        $this->assertSame('', $struct->vendor_name);
        $this->assertSame(-1, $struct->id_vendor);
        $this->assertSame('EUR', $struct->currency);
    }
}
