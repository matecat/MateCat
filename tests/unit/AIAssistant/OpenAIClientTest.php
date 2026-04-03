<?php

namespace unit\Controllers;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\AIAssistant\AIClientFactory;
use Utils\AIAssistant\AIClientInterface;

class OpenAIClientTest extends AbstractTest
{
    static $total = 0;
    static $correct = 0;

    private AIClientInterface $openAIClient;

    /**
     * @throws \Exception
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->openAIClient = AIClientFactory::create("openai");
    }

    public static function tearDownAfterClass(): void
    {
        $acceptance = 0.7;
        $accuracy = self::$correct / self::$total;

        fwrite(STDERR, "\nAI Accuracy: " . ($accuracy * 100) . "%\n");

        if ($accuracy < $acceptance) {
            self::fail("AI accuracy below threshold. Expected at least " . ($acceptance * 100) . "%, got " . ($accuracy * 100) . "%.");
        }
    }

    #[Test]
    #[DataProvider('excellentFeedbacks')]
    public function test_excellent_feedbacks(
        string $localized_source,
        string $localized_target,
        string $text,
        string $translation,
        string $style,
    )
    {
        $this->run_test(
            "Excellent",
            $localized_source,
            $localized_target,
            $text,
            $translation,
            $style,
        );
    }

    #[Test]
    #[DataProvider('goodFeedbacks')]
    public function test_good_feedbacks(
        string $localized_source,
        string $localized_target,
        string $text,
        string $translation,
        string $style,
    )
    {
        $this->run_test(
            "Good",
            $localized_source,
            $localized_target,
            $text,
            $translation,
            $style,
        );
    }

    #[Test]
    #[DataProvider('couldBeImprovedFeedbacks')]
    public function test_could_be_improved_feedbacks(
        string $localized_source,
        string $localized_target,
        string $text,
        string $translation,
        string $style,
    )
    {
        $this->run_test(
            "Could Be Improved",
            $localized_source,
            $localized_target,
            $text,
            $translation,
            $style,
        );
    }

    #[Test]
    #[DataProvider('badFeedbacks')]
    public function test_bad_feedbacks(
        string $localized_source,
        string $localized_target,
        string $text,
        string $translation,
        string $style,
    )
    {
        $this->run_test(
            "Does Not Match Source",
            $localized_source,
            $localized_target,
            $text,
            $translation,
            $style,
        );
    }


    /**
     * Executes a test to evaluate the quality of a translation using the specified input parameters.
     *
     * @param string $expected_feedback The expected feedback category for the translation.
     * @param string $localized_source The source language of the text in its localized representation.
     * @param string $localized_target The target language of the translation in its localized representation.
     * @param string $text The original text to be translated.
     * @param string $translation The translated text to be evaluated.
     * @*/
    public function run_test(
        string $expected_feedback,
        string $localized_source,
        string $localized_target,
        string $text,
        string $translation,
        string $style
    )
    {
        $message = $this->openAIClient->evaluateTranslation(
            sourceLanguage: $localized_source,
            targetLanguage: $localized_target,
            text: $text,
            translation: $translation,
            style: $style
        );

        if($message === false){
            $this->fail("No feedback message returned.");
        }

        if(!is_array($message)){
            $this->fail("Data is in wrong format. Expected an array with 'category' and 'comment' keys.");
        }

        // check if the message is in the expected format
        $this->assertArrayHasKey('category', $message);
        $this->assertArrayHasKey('comment', $message);
        $category = $message['category'];

        $expected = [
            "Excellent",
            "Good",
            "Could Be Improved",
            "Does Not Match Source"
        ];

        // test is the category is one of the expected values
        $this->assertContains($category, $expected);

        self::$total++;

        if ($expected_feedback === $category) {
            self::$correct++;
        } else {
            fwrite(STDERR, sprintf(
                "\n[AI MISMATCH] Expected: %s, Got: %s\nText: %s\nTranslation: %s\n",
                $expected_feedback,
                $category,
                $text,
                $translation
            ));

            $this->markTestSkipped("AI mismatch");
        }
    }

    /** ---------------------- Data Providers ---------------------- */
    public static function excellentFeedbacks(): array
    {
        return [
            [
                'localized_source' => 'English',
                'localized_target' => 'Italian',
                'text' => 'Good morning, how are you?',
                'translation' => 'Buongiorno, come stai?',
                'style' => 'faithful',
            ],
            [
                'text' => 'I would like a cup of coffee.',
                'translation' => 'Vorrei una tazza di caffè.',
                'localized_source' => 'English',
                'localized_target' => 'Italian',
                'style' => 'faithful',
            ],
            [
                'text' => 'She is reading a very interesting book.',
                'translation' => 'Sta leggendo un libro molto interessante.',
                'localized_source' => 'English',
                'localized_target' => 'Italian',
                'style' => 'faithful',
            ],
            [
                'text' => 'The weather is nice today.',
                'translation' => 'Oggi il tempo è bello.',
                'localized_source' => 'English',
                'localized_target' => 'Italian',
                'style' => 'faithful',
            ],
            [
                'text' => 'I will call you later.',
                'translation' => 'Ti chiamerò più tardi.',
                'localized_source' => 'English',
                'localized_target' => 'Italian',
                'style' => 'faithful',
            ],
            [
                'text' => 'This is exactly what I needed.',
                'translation' => 'Questo è esattamente ciò di cui avevo bisogno.',
                'localized_source' => 'English',
                'localized_target' => 'Italian',
                'style' => 'faithful',
            ],
            [
                'text' => 'We are going to the restaurant tonight.',
                'translation' => 'Stasera andiamo al ristorante.',
                'localized_source' => 'English',
                'localized_target' => 'Italian',
                'style' => 'faithful',
            ],
            [
                'text' => 'He works in a large company.',
                'translation' => 'Lavora in una grande azienda.',
                'localized_source' => 'English',
                'localized_target' => 'Italian',
                'style' => 'faithful',
            ],
            [
                'text' => 'I completely agree with you.',
                'translation' => 'Sono completamente d’accordo con te.',
                'localized_source' => 'English',
                'localized_target' => 'Italian',
                'style' => 'faithful',
            ],
            [
                'text' => 'Can you help me with this task?',
                'translation' => 'Puoi aiutarmi con questo compito?',
                'localized_source' => 'English',
                'localized_target' => 'Italian',
                'style' => 'faithful',
            ],
        ];
    }

    public static function goodFeedbacks(): array
    {
        return [
        [
            'text' => 'Good morning, how are you?',
            'translation' => 'Buongiorno, come va?',
            'localized_source' => 'English',
            'localized_target' => 'Italian',
            'style' => 'faithful',
        ],
        [
            'text' => 'I would like a cup of coffee.',
            'translation' => 'Vorrei un caffè.',
            'localized_source' => 'English',
            'localized_target' => 'Italian',
            'style' => 'faithful',
        ],
        [
            'text' => 'She is reading a very interesting book.',
            'translation' => 'Lei sta leggendo un libro interessante.',
            'localized_source' => 'English',
            'localized_target' => 'Italian',
            'style' => 'faithful',
        ],
        [
            'text' => 'The weather is nice today.',
            'translation' => 'Il tempo oggi è bello.',
            'localized_source' => 'English',
            'localized_target' => 'Italian',
            'style' => 'faithful',
        ],
        [
            'text' => 'I will call you later.',
            'translation' => 'Ti chiamo più tardi.',
            'localized_source' => 'English',
            'localized_target' => 'Italian',
            'style' => 'faithful',
        ],
        [
            'text' => 'This is exactly what I needed.',
            'translation' => 'Questo è proprio quello che mi serviva.',
            'localized_source' => 'English',
            'localized_target' => 'Italian',
            'style' => 'faithful',
        ],
        [
            'text' => 'We are going to the restaurant tonight.',
            'translation' => 'Andiamo al ristorante stasera.',
            'localized_source' => 'English',
            'localized_target' => 'Italian',
            'style' => 'faithful',
        ],
        [
            'text' => 'He works in a large company.',
            'translation' => 'Lavora in una azienda grande.',
            'localized_source' => 'English',
            'localized_target' => 'Italian',
            'style' => 'faithful',
        ],
        [
            'text' => 'I completely agree with you.',
            'translation' => 'Sono d’accordo con te.',
            'localized_source' => 'English',
            'localized_target' => 'Italian',
            'style' => 'faithful',
        ],
        [
            'text' => 'Can you help me with this task?',
            'translation' => 'Puoi aiutarmi con questo?',
            'localized_source' => 'English',
            'localized_target' => 'Italian',
            'style' => 'faithful',
        ],
    ];
    }

    public static function couldBeImprovedFeedbacks(): array
    {
        return [
            [
                'text' => 'Good morning, how are you?',
                'translation' => 'Buongiorno, come tu stai?',
                'localized_source' => 'English',
                'localized_target' => 'Italian',
                'style' => 'faithful',
            ],
            [
                'text' => 'I would like a cup of coffee.',
                'translation' => 'Vorrei una coppa di caffè.',
                'localized_source' => 'English',
                'localized_target' => 'Italian',
                'style' => 'faithful',
            ],
            [
                'text' => 'She is reading a very interesting book.',
                'translation' => 'Lei legge un libro molto interessante adesso.',
                'localized_source' => 'English',
                'localized_target' => 'Italian',
                'style' => 'faithful',
            ],
            [
                'text' => 'The weather is nice today.',
                'translation' => 'Il tempo è molto bello giorno oggi.',
                'localized_source' => 'English',
                'localized_target' => 'Italian',
                'style' => 'faithful',
            ],
            [
                'text' => 'I will call you later.',
                'translation' => 'Ti chiamerò più tardi dopo.',
                'localized_source' => 'English',
                'localized_target' => 'Italian',
                'style' => 'faithful',
            ],
            [
                'text' => 'This is exactly what I needed.',
                'translation' => 'Questo è esattamente quello che avevo bisogno di.',
                'localized_source' => 'English',
                'localized_target' => 'Italian',
                'style' => 'faithful',
            ],
            [
                'text' => 'We are going to the restaurant tonight.',
                'translation' => 'Noi andiamo al ristorante questa notte.',
                'localized_source' => 'English',
                'localized_target' => 'Italian',
                'style' => 'faithful',
            ],
            [
                'text' => 'He works in a large company.',
                'translation' => 'Egli lavora in compagnia molto grande.',
                'localized_source' => 'English',
                'localized_target' => 'Italian',
                'style' => 'faithful',
            ],
            [
                'text' => 'I completely agree with you.',
                'translation' => 'Sono completamente d’accordo con te tutto.',
                'localized_source' => 'English',
                'localized_target' => 'Italian',
                'style' => 'faithful',
            ],
            [
                'text' => 'Can you help me with this task?',
                'translation' => 'Puoi aiutarmi con questo lavoro qui?',
                'localized_source' => 'English',
                'localized_target' => 'Italian',
                'style' => 'faithful',
            ],
        ];
    }

    public static function badFeedbacks(): array
    {
        return [
        [
            'text' => 'Good morning, how are you?',
            'translation' => 'Il gatto è sul tavolo.',
            'localized_source' => 'English',
            'localized_target' => 'Italian',
            'style' => 'faithful',
        ],
        [
            'text' => 'I would like a cup of coffee.',
            'translation' => 'Domani andiamo al mare.',
            'localized_source' => 'English',
            'localized_target' => 'Italian',
            'style' => 'faithful',
        ],
        [
            'text' => 'She is reading a very interesting book.',
            'translation' => 'Mi piace giocare a calcio.',
            'localized_source' => 'English',
            'localized_target' => 'Italian',
            'style' => 'faithful',
        ],
        [
            'text' => 'The weather is nice today.',
            'translation' => 'Questo computer è molto veloce.',
            'localized_source' => 'English',
            'localized_target' => 'Italian',
            'style' => 'faithful',
        ],
        [
            'text' => 'I will call you later.',
            'translation' => 'Sto cucinando la cena.',
            'localized_source' => 'English',
            'localized_target' => 'Italian',
            'style' => 'faithful',
        ],
        [
            'text' => 'This is exactly what I needed.',
            'translation' => 'La porta è chiusa.',
            'localized_source' => 'English',
            'localized_target' => 'Italian',
            'style' => 'faithful',
        ],
        [
            'text' => 'We are going to the restaurant tonight.',
            'translation' => 'Il cielo è blu.',
            'localized_source' => 'English',
            'localized_target' => 'Italian',
            'style' => 'faithful',
        ],
        [
            'text' => 'He works in a large company.',
            'translation' => 'Mi sveglio alle sette.',
            'localized_source' => 'English',
            'localized_target' => 'Italian',
            'style' => 'faithful',
        ],
        [
            'text' => 'I completely agree with you.',
            'translation' => 'Sto leggendo un giornale.',
            'localized_source' => 'English',
            'localized_target' => 'Italian',
            'style' => 'faithful',
        ],
        [
            'text' => 'Can you help me with this task?',
            'translation' => 'La macchina è parcheggiata fuori.',
            'localized_source' => 'English',
            'localized_target' => 'Italian',
            'style' => 'faithful',
        ],
    ];
    }
}