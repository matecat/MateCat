<?php

declare(strict_types=1);

namespace unit\Structs;

use Model\Teams\MembershipStruct;
use Model\Users\MetadataStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class MembershipStructMetadataTest extends AbstractTest
{
    #[Test]
    public function setUserMetadataAcceptsListOfMetadataStruct(): void
    {
        $meta1 = new MetadataStruct();
        $meta1->id = '1';
        $meta1->uid = '100';
        $meta1->key = 'gplus_picture';
        $meta1->value = 'https://example.com/photo.jpg';

        $meta2 = new MetadataStruct();
        $meta2->id = '2';
        $meta2->uid = '100';
        $meta2->key = 'theme';
        $meta2->value = 'dark';

        $struct = new MembershipStruct();
        $struct->id_team = 1;
        $struct->setUserMetadata([$meta1, $meta2]);

        $result = $struct->getUserMetadata();

        self::assertCount(2, $result);
        self::assertInstanceOf(MetadataStruct::class, $result[0]);
        self::assertInstanceOf(MetadataStruct::class, $result[1]);
        self::assertSame('gplus_picture', $result[0]->key);
        self::assertSame('theme', $result[1]->key);
    }

    #[Test]
    public function getUserMetadataReturnsEmptyArrayByDefault(): void
    {
        $struct = new MembershipStruct();
        $struct->id_team = 1;

        $result = $struct->getUserMetadata();

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    #[Test]
    public function setUserMetadataWithNullCoercesToEmptyArray(): void
    {
        $struct = new MembershipStruct();
        $struct->id_team = 1;

        // The method has `if ($user_metadata == null)` which catches empty array
        $struct->setUserMetadata([]);

        self::assertSame([], $struct->getUserMetadata());
    }
}
