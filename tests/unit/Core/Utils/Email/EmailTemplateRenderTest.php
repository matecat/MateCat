<?php

declare(strict_types=1);

namespace Matecat\Core\Utils\Email;

use Closure;
use Matecat\TestHelpers\AbstractTest;
use Model\Comments\CommentStruct;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Teams\MembershipStruct;
use Model\Teams\TeamDao;
use Model\Teams\TeamStruct;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\Translators\JobsTranslatorsStruct;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Plugins\Features\ReviewExtended\Email\RevisionChangedNotificationEmail;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionProperty;
use Utils\Email\AbstractEmail;
use Utils\Email\CommentEmail;
use Utils\Email\CommentMentionEmail;
use Utils\Email\CommentResolveEmail;
use Utils\Email\ForgotPasswordEmail;
use Utils\Email\InvitedToTeamEmail;
use Utils\Email\MembershipCreatedEmail;
use Utils\Email\MembershipDeletedEmail;
use Utils\Email\ProjectAssignedEmail;
use Utils\Email\SendToTranslatorForDeliveryChangeEmail;
use Utils\Email\SendToTranslatorForJobSplitEmail;
use Utils\Email\SendToTranslatorForNewJobEmail;
use Utils\Email\SetPasswordRequestEmail;
use Utils\Email\SignupEmail;
use Utils\Email\WelcomeEmail;
use Utils\Registry\AppConfig;
use Utils\TmKeyManagement\ShareKeyEmail;
use Utils\TmKeyManagement\TmKeyStruct;

/**
 * Renders every email template with data shaped the way the database returns it: ids as strings,
 * `''`, `null`, empty lists, dates as `'2026-11-11 17:04:15'`. Each case asserts the text the reader
 * sees, so a template that silently prints nothing for a value fails here.
 *
 * The second half sends each email through its own class and checks two things: the template is
 * rendered once per message, and the message reaches the queue with the text in both parts.
 */
class EmailTemplateRenderTest extends AbstractTest
{
    private const string DB_DATE = '2026-11-11 17:04:15';

    /** The templates that wrap a message body instead of rendering one. */
    private const array LAYOUTS = ['skeleton.html', 'empty_skeleton.html'];

    /**
     * @return array<string, array{string, array<string, mixed>, list<string>, list<string>}>
     *         template, variables, texts that must appear, texts that must not appear
     */
    public static function templates(): array
    {
        $sender = ['uid' => '7', 'email' => 'pm@example.com', 'first_name' => 'Ada', 'last_name' => 'Lovelace'];
        $user = ['uid' => '9', 'email' => 'bob@example.com', 'first_name' => 'Bob', 'last_name' => 'Rossi'];

        return [
            'Comment/action_on_a_comment.html' => [
                'Comment/action_on_a_comment.html',
                [
                    'user' => $user,
                    'project' => ['id' => '42', 'name' => 'Airbnb Q4'],
                    'job' => ['id' => '100', 'source' => 'en-US', 'target' => 'it-IT'],
                    'commenter' => 'Jane Smith',
                    'action' => 'commented on',
                    'url' => 'https://dev.matecat.com/translate/x,comment',
                    'content' => 'Please check the term',
                    'id_segment' => '555',
                ],
                ['Hi Bob, Jane Smith commented on Airbnb Q4 (42) | en-US &gt; it-IT', 'Segment ID: 555'],
                [],
            ],
            'Project/project_assigned_content.html, with a due date' => [
                'Project/project_assigned_content.html',
                [
                    'user' => $user,
                    'sender' => $sender,
                    'project' => ['id' => '42', 'name' => 'Airbnb Q4', 'due_date' => self::DB_DATE],
                    'words_count' => '1,234',
                    'project_url' => 'https://dev.matecat.com/analyze/x/42-abc',
                ],
                ['Hi Bob,', 'Ada Lovelace assigned a project to you.', 'Due date: November 11, 2026, 5:04 pm PM', 'Total words count: 1,234'],
                [],
            ],
            'Project/project_assigned_content.html, without a due date' => [
                'Project/project_assigned_content.html',
                [
                    'user' => $user,
                    'sender' => $sender,
                    'project' => ['id' => '42', 'name' => 'Airbnb Q4', 'due_date' => null],
                    'words_count' => '0',
                    'project_url' => 'https://dev.matecat.com/analyze/x/42-abc',
                ],
                ['Total words count: 0'],
                ['Due date'],
            ],
            'ReviewExtended/batch_review_processor_alert.html' => [
                'ReviewExtended/batch_review_processor_alert.html',
                ['chunkId' => 100, 'chunkReviewId' => 7],
                ['The chunk with id #100 did not have', 'review with id #7 was created'],
                [],
            ],
            'ReviewExtended/penalty_points_drift_alert.html' => [
                'ReviewExtended/penalty_points_drift_alert.html',
                [
                    'mismatches' => [
                        ['id' => 3, 'id_job' => 100, 'password' => 'abc', 'source_page' => 2, 'recorded_penalty_points' => 1.5, 'actual_penalty_points' => 3.0],
                    ],
                    'shown' => 1,
                    'total' => 4,
                ],
                ['4 qa_chunk_reviews row(s)', 'actual 3 100 abc 2 1.5 3', 'Showing 1 of 4 drifted rows'],
                [],
            ],
            'Revise/second_pass_segment_changed_notice.html, author is the previous approver' => [
                'Revise/second_pass_segment_changed_notice.html',
                [
                    'changeAuthor' => $sender,
                    'recipientUser' => $user,
                    'segmentUrl' => 'https://dev.matecat.com/revise2/x#555',
                    'data' => ['isPreviousChangeAuthor' => true],
                    'segmentInfo' => [
                        'segment_source' => 'Hello',
                        'old_translation' => 'Ciao',
                        'new_translation' => 'Salve',
                        'old_status' => 'APPROVED2',
                        'new_status' => 'APPROVED',
                        'issues' => [
                            [
                                'category' => 'Style',
                                'severity' => 'Minor',
                                'comments' => [
                                    ['source_page' => '1', 'create_date' => self::DB_DATE, 'comment' => 'Too formal'],
                                    ['source_page' => '3', 'create_date' => self::DB_DATE, 'comment' => 'Agreed'],
                                ],
                            ],
                            ['category' => 'Terminology', 'severity' => 'Major', 'comments' => []],
                        ],
                    ],
                ],
                [
                    'Hi Bob,',
                    'Ada Lovelace changed a segment which was earlier approved by you.',
                    'Old status: APPROVED2',
                    'Style [Minor]',
                    'Translator (Nov 11, 2026 05:04 PM): Too formal',
                    'Reviewer (Nov 11, 2026 05:04 PM): Agreed',
                    'Terminology [Major]',
                ],
                [],
            ],
            'Revise/second_pass_segment_changed_notice.html, unknown author and no issues' => [
                'Revise/second_pass_segment_changed_notice.html',
                [
                    'changeAuthor' => null,
                    'recipientUser' => $user,
                    'segmentUrl' => 'https://dev.matecat.com/revise2/x#555',
                    'data' => ['isPreviousChangeAuthor' => false],
                    'segmentInfo' => [
                        'segment_source' => 'Hello',
                        'old_translation' => 'Ciao',
                        'new_translation' => 'Salve',
                        'old_status' => 'APPROVED2',
                        'new_status' => 'APPROVED',
                        'issues' => [],
                    ],
                ],
                ['someone changed a previously approved segment.'],
                ['earlier approved by you', 'Issues'],
            ],
            'ShareKey/message_content.html, with the sender address' => [
                'ShareKey/message_content.html',
                [
                    'senderFullName' => 'Ada Lovelace',
                    'senderEmail' => 'pm@example.com',
                    'tm_key_name' => 'Legal TM',
                    'tm_key_value' => 'a1b2c3',
                    'addressMail' => 'bob@example.com',
                ],
                ['Ada Lovelace (pm@example.com) shared', 'Legal TM', 'registered to bob@example.com'],
                [],
            ],
            'ShareKey/message_content.html, without the sender address' => [
                'ShareKey/message_content.html',
                [
                    'senderFullName' => 'Ada Lovelace',
                    'senderEmail' => '',
                    'tm_key_name' => '',
                    'tm_key_value' => 'a1b2c3',
                    'addressMail' => 'bob@example.com',
                ],
                ['Ada Lovelace shared'],
                ['()'],
            ],
            'Signup/forgot_password_content.html' => [
                'Signup/forgot_password_content.html',
                ['user' => $user, 'password_reset_url' => 'https://dev.matecat.com/reset/t0k'],
                ['Hi Bob,', 'https://dev.matecat.com/reset/t0k'],
                ['https[:]'],
            ],
            'Signup/set_password_request_content.html' => [
                'Signup/set_password_request_content.html',
                ['user' => $user, 'password_reset_url' => 'https://dev.matecat.com/reset/t0k'],
                ['Hi Bob,', 'https://dev.matecat.com/reset/t0k'],
                ['https[:]'],
            ],
            'Signup/signup_content.html' => [
                'Signup/signup_content.html',
                ['user' => $user, 'activation_url' => 'https://dev.matecat.com/activate/t0k', 'signup_url' => 'https://dev.matecat.com/'],
                ['Hi Bob,', 'https://dev.matecat.com/activate/t0k'],
                ['https[:]'],
            ],
            'Signup/welcome_content.html' => [
                'Signup/welcome_content.html',
                ['user' => $user],
                ['Dear Bob,'],
                [],
            ],
            'Team/email_invited_to_team.html' => [
                'Team/email_invited_to_team.html',
                [
                    'sender' => $sender,
                    'email' => 'new@example.com',
                    'team' => ['id' => '10', 'name' => 'Ben & Jerry <b>'],
                    'signup_url' => 'https://dev.matecat.com/signup',
                ],
                ['Hi new@example.com,', 'Ada Lovelace has invited you to join the team "Ben &amp; Jerry &lt;b&gt;"'],
                ['<b>'],
            ],
            'Team/membership_created_content.html' => [
                'Team/membership_created_content.html',
                ['user' => $user, 'sender' => $sender, 'team' => ['id' => '10', 'name' => 'Legal'], 'manageUrl' => 'https://dev.matecat.com/manage'],
                ['Hi Bob,', 'Ada Lovelace added you to the team Legal on Matecat.'],
                [],
            ],
            'Team/membership_deleted_content.html' => [
                'Team/membership_deleted_content.html',
                ['user' => $user, 'sender' => $sender, 'team' => ['id' => '10', 'name' => 'Legal']],
                ['Hi Bob,', 'Ada Lovelace removed you from the team Legal'],
                [],
            ],
            'Translator/job_change_delivery_send_to_translator_content.html' => [
                'Translator/job_change_delivery_send_to_translator_content.html',
                self::translatorVariables(),
                ['Hi Bob Rossi,', 'The new delivery date is: Monday, 15-Jun-26 10:00:00 UTC'],
                [],
            ],
            'Translator/job_new_send_to_translator_content.html' => [
                'Translator/job_new_send_to_translator_content.html',
                self::translatorVariables(),
                ['Hi Bob Rossi,', 'Ada Lovelace assigned a job to you.'],
                [],
            ],
            'Translator/job_split_send_to_translator_content.html' => [
                'Translator/job_split_send_to_translator_content.html',
                self::translatorVariables(),
                ['Hi Bob Rossi,', 'Ada Lovelace splitted a job'],
                [],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $variables
     * @param list<string> $expected
     * @param list<string> $absent
     */
    #[Test]
    #[DataProvider('templates')]
    public function aTemplateShowsItsValues(string $template, array $variables, array $expected, array $absent): void
    {
        $html = self::probe($template, $variables)->content();
        $visible = self::visible($html);

        foreach ($expected as $text) {
            $this->assertStringContainsString($text, $visible);
        }

        foreach ($absent as $text) {
            $this->assertStringNotContainsString($text, $html);
        }
    }

    /**
     * A template added without a case above is a template nobody has rendered with real data.
     */
    #[Test]
    public function everyTemplateHasARenderCase(): void
    {
        $root = AppConfig::$TEMPLATE_ROOT . '/Emails';
        $covered = array_unique(array_column(self::templates(), 0));

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            $template = substr($file->getPathname(), strlen($root) + 1);

            if ($file->isFile() && $file->getExtension() === 'html' && !in_array($template, self::LAYOUTS, true)) {
                $this->assertContains($template, $covered, "$template has no case in templates()");
            }
        }
    }

    /**
     * `false` hides the title; the body is embedded as the markup it is.
     */
    #[Test]
    public function theLayoutShowsTheTitleOnlyWhenAsked(): void
    {
        $probe = self::probe('Signup/welcome_content.html', ['user' => ['first_name' => 'Bob']]);

        $html = $probe->layout('<p>body</p>', showTitle: false);
        $this->assertStringNotContainsString('<h1>', $html);
        $this->assertStringContainsString('<p>body</p>', $html, 'the body is embedded as markup');

        $this->assertStringContainsString('<h1>Probe title</h1>', $probe->layout('<p>body</p>', showTitle: true));
    }

    /**
     * @return array<string, array{Closure(self): AbstractEmail, string}>
     */
    public static function emails(): array
    {
        return [
            'ForgotPasswordEmail' => [
                static fn(self $t): AbstractEmail => new class($t->user()) extends ForgotPasswordEmail {
                    use RecordsDelivery;
                },
                'Hi Bob,',
            ],
            'SetPasswordRequestEmail' => [
                static fn(self $t): AbstractEmail => new class($t->user()) extends SetPasswordRequestEmail {
                    use RecordsDelivery;
                },
                'an account already exists',
            ],
            'SignupEmail' => [
                static fn(self $t): AbstractEmail => new class($t->user()) extends SignupEmail {
                    use RecordsDelivery;
                },
                'To complete your registration',
            ],
            'WelcomeEmail' => [
                static fn(self $t): AbstractEmail => new class($t->user()) extends WelcomeEmail {
                    use RecordsDelivery;
                },
                'Dear Bob,',
            ],
            'InvitedToTeamEmail' => [
                static fn(self $t): AbstractEmail => new class($t->sender(), 'new@example.com', $t->team()) extends InvitedToTeamEmail {
                    use RecordsDelivery;
                },
                'has invited you to join the team',
            ],
            'MembershipCreatedEmail' => [
                static fn(self $t): AbstractEmail => new class($t->sender(), $t->membership(), $t->userDao(), $t->teamDao()) extends MembershipCreatedEmail {
                    use RecordsDelivery {
                        _getTemplateVariables as public;
                    }
                },
                'added you to the team Legal',
            ],
            'MembershipDeletedEmail' => [
                static fn(self $t): AbstractEmail => new class($t->sender(), $t->user(), $t->team()) extends MembershipDeletedEmail {
                    use RecordsDelivery;
                },
                'removed you from the team Legal',
            ],
            'ProjectAssignedEmail' => [
                static fn(self $t): AbstractEmail => new class($t->dbStub(), $t->sender(), $t->project(), $t->user(), []) extends ProjectAssignedEmail {
                    use RecordsDelivery;
                },
                'assigned a project to you',
            ],
            'ShareKeyEmail' => [
                static fn(self $t): AbstractEmail => new class($t->sender(), ['bob@example.com', 'Bob Rossi'], $t->memoryKey()) extends ShareKeyEmail {
                    use RecordsDelivery;
                },
                'shared the following language resource',
            ],
            'CommentEmail' => [
                static fn(self $t): AbstractEmail => new class(...$t->commentArgs()) extends CommentEmail {
                    use RecordsDelivery;
                },
                'Airbnb Q4 (42)',
            ],
            'CommentMentionEmail' => [
                static fn(self $t): AbstractEmail => new class(...$t->commentArgs()) extends CommentMentionEmail {
                    use RecordsDelivery;
                },
                'en-US &gt; it-IT',
            ],
            'CommentResolveEmail' => [
                static fn(self $t): AbstractEmail => new class(...$t->commentArgs()) extends CommentResolveEmail {
                    use RecordsDelivery;
                },
                'Airbnb Q4 (42)',
            ],
            'SendToTranslatorForNewJobEmail' => [
                static fn(self $t): AbstractEmail => new class($t->sender(), $t->translator(), 'Airbnb Q4', $t->userDao()) extends SendToTranslatorForNewJobEmail {
                    use RecordsDelivery;
                },
                'assigned a job to you',
            ],
            'SendToTranslatorForJobSplitEmail' => [
                static fn(self $t): AbstractEmail => new class($t->sender(), $t->translator(), 'Airbnb Q4', $t->userDao()) extends SendToTranslatorForJobSplitEmail {
                    use RecordsDelivery;
                },
                'splitted a job',
            ],
            'SendToTranslatorForDeliveryChangeEmail' => [
                static fn(self $t): AbstractEmail => new class($t->sender(), $t->translator(), 'Airbnb Q4', $t->userDao()) extends SendToTranslatorForDeliveryChangeEmail {
                    use RecordsDelivery;
                },
                'changed your delivery deadline',
            ],
            'RevisionChangedNotificationEmail' => [
                static fn(self $t): AbstractEmail => new class(
                    ['segment_source' => 'Hello', 'old_translation' => 'Ciao', 'new_translation' => 'Salve', 'old_status' => 'APPROVED2', 'new_status' => 'APPROVED', 'issues' => []],
                    ['recipient' => $t->user(), 'isPreviousChangeAuthor' => true],
                    'https://dev.matecat.com/revise2/x#555',
                    $t->sender()
                ) extends RevisionChangedNotificationEmail {
                    use RecordsDelivery;
                },
                'changed a segment which was earlier approved by you',
            ],
        ];
    }

    /**
     * Rendering twice ran every query in `_getTemplateVariables()` twice, and ShareKeyEmail rendered
     * a third time just to read its subject.
     *
     * @param Closure(self): AbstractEmail $factory
     */
    #[Test]
    #[DataProvider('emails')]
    public function anEmailRendersItsTemplateOnceAndQueuesTheText(Closure $factory, string $text): void
    {
        $email = $factory($this);
        $email->send();

        /** @var RecordsDelivery $email */
        $this->assertCount(1, $email->queued);

        $message = $email->queued[0];
        $this->assertCount(2, $message['address'], 'MailWorker reads the address and the name');
        $this->assertStringContainsString($text, self::visible($message['htmlBody']));
        $this->assertStringContainsString(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'), self::visible($message['altBody']));
        $this->assertNotEmpty($message['subject']);
        $this->assertSame(1, $email->renders, 'the template is rendered once per message');
    }

    public function user(): UserStruct
    {
        return $this->userStruct(9, 'bob@example.com', 'Bob', 'Rossi');
    }

    public function sender(): UserStruct
    {
        return $this->userStruct(7, 'pm@example.com', 'Ada', 'Lovelace');
    }

    public function team(): TeamStruct
    {
        $team = new TeamStruct();
        $team->id = 10;
        $team->name = 'Legal';

        return $team;
    }

    public function membership(): MembershipStruct
    {
        $membership = new MembershipStruct();
        $membership->id = 1;
        $membership->id_team = 10;
        $membership->uid = 9;
        $membership->setUser($this->user());
        (new ReflectionProperty(MembershipStruct::class, 'team'))->setValue($membership, $this->team());

        return $membership;
    }

    public function project(): ProjectStruct
    {
        $project = new ProjectStruct();
        $project->id = 42;
        $project->name = 'Airbnb Q4';
        $project->password = 'abc';
        $project->due_date = self::DB_DATE;

        return $project;
    }

    public function memoryKey(): MemoryKeyStruct
    {
        $tmKey = new TmKeyStruct();
        $tmKey->name = 'Legal TM';
        $tmKey->key = 'a1b2c3';

        $memoryKey = new MemoryKeyStruct();
        $memoryKey->uid = 7;
        $memoryKey->tm_key = $tmKey;

        return $memoryKey;
    }

    public function translator(): JobsTranslatorsStruct
    {
        $translator = new JobsTranslatorsStruct();
        $translator->email = 'bob@example.com';
        $translator->id_job = 100;
        $translator->job_password = 'pwd123';
        $translator->source = 'en-US';
        $translator->target = 'it-IT';
        $translator->delivery_date = '2026-06-15 10:00:00';
        $translator->job_owner_timezone = 0;
        $translator->added_by = 7;

        return $translator;
    }

    /**
     * The project arrives as a `ShapelessConcreteStruct` and the job as a `JobStruct`, as
     * `CommentController` passes them.
     *
     * @return array{UserStruct, CommentStruct, string, ShapelessConcreteStruct, JobStruct, \Model\DataAccess\IDatabase}
     */
    public function commentArgs(): array
    {
        $comment = new CommentStruct();
        $comment->message = 'Please check the term';
        $comment->first_name = 'Jane';
        $comment->last_name = 'Smith';
        $comment->full_name = 'Jane Smith';
        $comment->id_segment = 555;

        // As ProjectDao::getProjectData() returns it: its `id` is the id of a file, not of the project.
        $project = new ShapelessConcreteStruct();
        $project->id = '98765';
        $project->name = 'Airbnb Q4';

        $job = new JobStruct(['id' => 100, 'password' => 'abc', 'id_project' => 42, 'source' => 'en-US', 'target' => 'it-IT']);

        return [$this->user(), $comment, 'https://dev.matecat.com/translate/x', $project, $job, $this->dbStub()];
    }

    public function userDao(): UserDao
    {
        $stub = $this->createStub(UserDao::class);
        $stub->method('setCacheTTL')->willReturnSelf();
        $stub->method('getByUid')->willReturn($this->user());
        $stub->method('getByEmail')->willReturn($this->user());

        return $stub;
    }

    public function teamDao(): TeamDao
    {
        $stub = $this->createStub(TeamDao::class);
        $stub->method('setCacheTTL')->willReturnSelf();
        $stub->method('fetchById')->willReturn($this->team());

        return $stub;
    }

    public function dbStub(): \Model\DataAccess\IDatabase
    {
        [$dbStub] = $this->createDatabaseMock();

        return $dbStub;
    }

    private function userStruct(int $uid, string $email, string $first, string $last): UserStruct
    {
        $user = new UserStruct();
        $user->uid = $uid;
        $user->email = $email;
        $user->first_name = $first;
        $user->last_name = $last;
        $user->confirmation_token = 't0k';

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private static function translatorVariables(): array
    {
        return [
            'sender' => ['uid' => '7', 'email' => 'pm@example.com', 'first_name' => 'Ada', 'last_name' => 'Lovelace'],
            'user' => ['uid' => '9', 'first_name' => 'Bob', 'last_name' => 'Rossi', '_name' => 'Bob Rossi'],
            'email' => 'bob@example.com',
            'delivery_date' => 'Monday, 15-Jun-26 10:00:00 UTC',
            'project_url' => 'https://dev.matecat.com/translate/x/en-US-it-IT/100-pwd123',
        ];
    }

    /**
     * @param array<string, mixed> $variables
     */
    private static function probe(string $template, array $variables): TemplateProbe
    {
        return new TemplateProbe($template, $variables);
    }

    /**
     * The text a reader sees, entities kept: tags dropped and every run of whitespace made one space,
     * so a case can quote text that wraps or crosses a tag in the template.
     */
    private static function visible(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
    }
}

/**
 * Renders one template with the variables it is given, through the same code the email classes use.
 */
final class TemplateProbe extends AbstractEmail
{
    protected ?string $title = 'Probe title';

    private bool $showTitle = false;

    /**
     * @param array<string, mixed> $variables
     */
    public function __construct(string $template, private readonly array $variables)
    {
        $this->_setTemplate($template);
        $this->_setLayout('skeleton.html');
    }

    public function content(): string
    {
        return $this->_buildMessageContent();
    }

    public function layout(string $body, bool $showTitle): string
    {
        $this->showTitle = $showTitle;

        return $this->_buildHTMLMessage($body);
    }

    public function send(): void
    {
    }

    protected function _getTemplateVariables(): array
    {
        return $this->variables;
    }

    protected function _getLayoutVariables(): array
    {
        $vars = parent::_getLayoutVariables();
        $vars['showTitle'] = $this->showTitle;

        return $vars;
    }
}

/**
 * Counts the renders of an email and keeps what it would have queued, instead of queueing it.
 */
trait RecordsDelivery
{
    public int $renders = 0;

    /** @var list<array<string, mixed>> */
    public array $queued = [];

    protected function _getTemplateVariables(): array
    {
        $this->renders++;

        return parent::_getTemplateVariables();
    }

    protected function _enqueueEmailDelivery(array $mailConf): void
    {
        $this->queued[] = $mailConf;
    }
}
