<?php

namespace unit\Engines\Matches;

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Engines\Results\MyMemory\Matches;

class MatchesTest extends AbstractTest
{


    #[Test]
    public function test_empty_constructor()
    {
        $match = new Matches([]);

        $this->assertEquals("0", $match->id);
        $this->assertEquals("1970-01-01 00:00:00", $match->create_date);
        $this->assertEquals("1970-01-01 00:00:00", $match->last_update_date);
        $this->assertEquals(0, $match->usage_count);
        $this->assertEquals(0, $match->match);
    }

    #[Test]
    public function test_real_constructor()
    {
        $createDate = date("Y-m-d");

        $match = new Matches([
            'id' => '234',
            'source' => 'en-US',
            'target' => 'it-IT',
            'raw_segment' => 'This is a fancy match',
            'raw_translation' => 'Questa è una traduzione fantastica',
            'match' => "85%",
            'created-by' => "MT-Altlang",
            'create-date' => $createDate,
        ]);

        $matches = $match->getMatches();

        $this->assertEquals("234", $matches['id']);
        $this->assertEquals('en-US', $matches['source']);
        $this->assertEquals('it-IT', $matches['target']);
        $this->assertEquals('This is a fancy match', $matches['segment']);
        $this->assertEquals('Questa è una traduzione fantastica', $matches['translation']);
        $this->assertEquals('85%', $matches['match']);
        $this->assertEquals('MT-Altlang', $matches['created_by']);
        $this->assertEquals($createDate, $matches['create_date']);
    }

    #[Test]
    public function test_tms_constructor()
    {
        $match = new Matches([
            'id' => '123134123',
            'raw_segment' => 'This is a sample page for Demo purposes.',
            'raw_translation' => 'Ceci est un exemple de page à des fins de démonstration',
            'match' => '100%',
            'created-by' => "Public TM",
            'create-date' => '2024-12-30 15:56:32',
            'prop' => [
                'test' => 'value'
            ],
            'quality' => 74,
            'usage-count' => 2,
            'subject' => '',
            'reference' => '',
            'last-updated-by' => 'MateCat',
            'last-update-date' => '2024-12-30',
            'tm_properties' => '[{"type":"x-project_id","value":86},{"type":"x-project_id","value":654}]',
            'key' => 'FDSFDSFDS8FDSFDS8FSD',
            'ICE' => 1,
        ]);

        $matches = $match->getMatches(2, [], 'en-US', 'fr-FR');

        $this->assertEquals("123134123", $matches['id']);
        $this->assertEquals('en-US', $matches['source']);
        $this->assertEquals('fr-FR', $matches['target']);
        $this->assertEquals('This is a sample page for Demo purposes.', $matches['segment']);
        $this->assertEquals('Ceci est un exemple de page à des fins de démonstration', $matches['translation']);
        $this->assertEquals('100%', $matches['match']);
        $this->assertEquals('Public TM', $matches['created_by']);
        $this->assertEquals('2024-12-30 15:56:32', $matches['create_date']);
        $this->assertEquals(74, $matches['quality']);
        $this->assertEquals(2, $matches['usage_count']);
        $this->assertTrue($matches['ICE']);
        $this->assertEquals('FDSFDSFDS8FDSFDS8FSD', $matches['memory_key']);
        $this->assertEquals([
            ["type" => "x-project_id", "value" => "86"],
            ["type" => "x-project_id", "value" => "654"],
        ], $matches['tm_properties']);
        $this->assertEquals([
            'test' => 'value'
        ], $matches['prop']);
    }

    #[Test]
    public function test_lara_constructor()
    {
        $createDate = date("Y-m-d");

        $match = new Matches([
            'id' => '234',
            'source' => 'en-US',
            'target' => 'it-IT',
            'raw_segment' => 'This is a fancy match',
            'raw_translation' => 'Questa è una traduzione fantastica',
            'match' => "85%",
            'score' => 0.901,
            'created-by' => "MT-Lara",
            'create-date' => $createDate,
        ]);

        $matches = $match->getMatches();

        $this->assertEquals("234", $matches['id']);
        $this->assertEquals('en-US', $matches['source']);
        $this->assertEquals('it-IT', $matches['target']);
        $this->assertEquals('This is a fancy match', $matches['segment']);
        $this->assertEquals('Questa è una traduzione fantastica', $matches['translation']);
        $this->assertEquals('85%', $matches['match']);
        $this->assertEquals(0.901, $matches['score']);
        $this->assertEquals('MT-Lara', $matches['created_by']);
        $this->assertEquals($createDate, $matches['create_date']);
    }
}
