<?php

class DuplicateNewLineTest extends PHPUnit_Framework_TestCase
{
    public function testCanDuplicateNewLinesTest()
    {
        $strings = [
            [
                'original' => "This is markdown **string** a " . PHP_EOL ." new line",
                'expected' => "This is markdown **string** a " . PHP_EOL . PHP_EOL ." new line",
            ],
            [
                'original' => "This is markdown **string** a " . PHP_EOL ."                             new line",
                'expected' => "This is markdown **string** a " . PHP_EOL . PHP_EOL ." new line",
            ],
            [
                'original' => "This is markdown **string** a " . PHP_EOL . PHP_EOL ." new line",
                'expected' => "This is markdown **string** a " . PHP_EOL . PHP_EOL ." new line",
            ],
            [
                'original' => "This is markdown **string** a " . PHP_EOL ." " . PHP_EOL ." new line",
                'expected' => "This is markdown **string** a " . PHP_EOL . PHP_EOL ." new line",
            ],
            [
                'original' => "This is markdown **string** a " . PHP_EOL ." " . PHP_EOL ." " . PHP_EOL ." new line",
                'expected' => "This is markdown **string** a " . PHP_EOL . PHP_EOL . " " . PHP_EOL . PHP_EOL ." new line",
            ],
            [
                'original' => "This is markdown **string** " . PHP_EOL ." with two break " . PHP_EOL ." new lines",
                'expected' => "This is markdown **string** " . PHP_EOL . PHP_EOL ." with two break " . PHP_EOL . PHP_EOL ." new lines",
            ],
            [
                'original' => "This is markdown **string** \n with two break \n new lines",
                'expected' => "This is markdown **string** \n\n with two break \n\n new lines",
            ],
            [
                'original' => "This is markdown **string** no new line",
                'expected' => "This is markdown **string** no new line",
            ],
        ];

        foreach ($strings as $string){
            $this->assertEquals(
                Utils::duplicateNewLines($string['original']),
                $string['expected'],
                'Error: expected ' . $string['expected']
            );
        }
    }
}