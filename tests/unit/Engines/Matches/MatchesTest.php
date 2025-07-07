<?php

use TestHelpers\AbstractTest;
use Utils\Engines\Results\MyMemory\Matches;

class MatchesTest extends AbstractTest {


    public function test_empty_constructor()
    {
        $match = new Matches([]);

        $this->assertEquals($match->id, "0");
        $this->assertEquals($match->create_date, "1970-01-01 00:00:00");
        $this->assertEquals($match->last_update_date, "1970-01-01 00:00:00");
        $this->assertEquals($match->usage_count, 0);
        $this->assertEquals($match->match, 0);
    }

    public function test_real_constructor()
    {
        $createDate = date( "Y-m-d" );

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

        $this->assertEquals($matches['id'], "234");
        $this->assertEquals($matches['source'], 'en-US');
        $this->assertEquals($matches['target'], 'it-IT');
        $this->assertEquals($matches['segment'], 'This is a fancy match');
        $this->assertEquals($matches['translation'], 'Questa è una traduzione fantastica');
        $this->assertEquals($matches['match'], '85%');
        $this->assertEquals($matches['created_by'], 'MT-Altlang');
        $this->assertEquals($matches['create_date'], $createDate);
    }

    public function test_tms_constructor()
    {
        $match = new Matches([
            'id' => '123134123',
            'raw_segment' => 'This is a sample page for Demo purposes.',
            'raw_translation' =>  'Ceci est un exemple de page à des fins de démonstration',
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

        $this->assertEquals($matches['id'], "123134123");
        $this->assertEquals($matches['source'], 'en-US');
        $this->assertEquals($matches['target'], 'fr-FR');
        $this->assertEquals($matches['segment'], 'This is a sample page for Demo purposes.');
        $this->assertEquals($matches['translation'], 'Ceci est un exemple de page à des fins de démonstration');
        $this->assertEquals($matches['match'], '100%');
        $this->assertEquals($matches['created_by'], 'Public TM');
        $this->assertEquals($matches['create_date'], '2024-12-30 15:56:32');
        $this->assertEquals($matches['quality'], 74);
        $this->assertEquals($matches['usage_count'], 2);
        $this->assertTrue($matches['ICE']);
        $this->assertEquals($matches['memory_key'], 'FDSFDSFDS8FDSFDS8FSD');
        $this->assertEquals($matches['tm_properties'], [
            ["type" => "x-project_id", "value" => "86"],
            ["type" => "x-project_id", "value" => "654"],
        ]);
        $this->assertEquals($matches['prop'], [
            'test' => 'value'
        ]);
    }

    public function test_lara_constructor()
    {
        $createDate = date( "Y-m-d" );

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

        $this->assertEquals($matches['id'], "234");
        $this->assertEquals($matches['source'], 'en-US');
        $this->assertEquals($matches['target'], 'it-IT');
        $this->assertEquals($matches['segment'], 'This is a fancy match');
        $this->assertEquals($matches['translation'], 'Questa è una traduzione fantastica');
        $this->assertEquals($matches['match'], '85%');
        $this->assertEquals($matches['score'], 0.901);
        $this->assertEquals($matches['created_by'], 'MT-Lara');
        $this->assertEquals($matches['create_date'], $createDate);
    }
}