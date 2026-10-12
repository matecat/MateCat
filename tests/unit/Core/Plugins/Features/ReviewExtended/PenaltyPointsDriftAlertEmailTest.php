<?php

declare(strict_types=1);

namespace Matecat\Core\Plugins\Features\ReviewExtended;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Plugins\Features\ReviewExtended\Email\PenaltyPointsDriftAlertEmail;
use ReflectionMethod;

/**
 * The alert email is what an operator actually sees when the standing drift check fires, so these
 * tests render the template rather than only inspecting the template variables — the truncation
 * notice lives in the template, and a variables-only test would stay green with a broken one.
 */
class PenaltyPointsDriftAlertEmailTest extends AbstractTest
{
    /**
     * @return array<int, array{id:int,id_job:int,password:string,source_page:int,recorded_penalty_points:float,actual_penalty_points:float}>
     */
    private function makeRows(int $count, int $idJob = 500): array
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'id' => 1000 + $i,
                'id_job' => $idJob + $i,
                'password' => 'pw' . $i,
                'source_page' => 2,
                'recorded_penalty_points' => 10.5 + $i,
                'actual_penalty_points' => 3.25 + $i,
            ];
        }

        return $rows;
    }

    private function render(PenaltyPointsDriftAlertEmail $email): string
    {
        return (string)(new ReflectionMethod($email, '_buildMessageContent'))->invoke($email);
    }

    /**
     * @return array<string, mixed>
     */
    private function templateVariables(PenaltyPointsDriftAlertEmail $email): array
    {
        /** @var array<string, mixed> $vars */
        $vars = (new ReflectionMethod($email, '_getTemplateVariables'))->invoke($email);

        return $vars;
    }

    /**
     * The total defaults to the page size, so a caller that does not page keeps working and the
     * truncation notice stays off. This is the back-compatibility the optional argument buys.
     */
    #[Test]
    public function templateVariablesDefaultTheTotalToThePageSize(): void
    {
        $vars = $this->templateVariables(new PenaltyPointsDriftAlertEmail($this->makeRows(3)));

        $this->assertSame(3, $vars['shown']);
        $this->assertSame(3, $vars['total']);
        $this->assertCount(3, $vars['mismatches']);
    }

    #[Test]
    public function templateVariablesKeepTheTotalSeparateFromThePage(): void
    {
        $vars = $this->templateVariables(new PenaltyPointsDriftAlertEmail($this->makeRows(50), 812));

        $this->assertSame(50, $vars['shown']);
        $this->assertSame(812, $vars['total']);
    }

    #[Test]
    public function renderedBodyListsOneRowPerMismatch(): void
    {
        $html = $this->render(new PenaltyPointsDriftAlertEmail($this->makeRows(3)));

        // header row + one row per mismatch
        $this->assertSame(4, substr_count($html, '<tr>'));

        $this->assertStringContainsString('500', $html);
        $this->assertStringContainsString('10.5', $html);
        $this->assertStringContainsString('3.25', $html);
    }

    /**
     * A split job reports one drifted row per password, and without the password column two of them
     * render as visually identical rows — same id_job, same source_page — so an operator cannot tell
     * which chunk drifted or re-run the repair for one.
     */
    #[Test]
    public function renderedBodyIdentifiesTheChunkByPassword(): void
    {
        $rows = $this->makeRows(2);
        $rows[0]['password'] = 'first_chunk';
        $rows[1]['password'] = 'second_chunk';
        $rows[1]['id_job'] = $rows[0]['id_job'];

        $html = $this->render(new PenaltyPointsDriftAlertEmail($rows));

        $this->assertStringContainsString('first_chunk', $html);
        $this->assertStringContainsString('second_chunk', $html);
    }

    /**
     * The first run after deploy is both the one most likely to return a large set and the one that
     * goes out by email, so a capped page has to say so — otherwise the report reads as the whole
     * picture and the remaining drift looks repaired.
     */
    #[Test]
    public function renderedBodyShowsTheTruncationNoticeWhenTheResultIsCapped(): void
    {
        $html = $this->render(new PenaltyPointsDriftAlertEmail($this->makeRows(50), 812));

        $this->assertStringContainsString('Showing 50 of 812', $html);
        // The headline count is the total, not the page.
        $this->assertStringContainsString('812 qa_chunk_reviews row(s)', $html);
    }

    #[Test]
    public function renderedBodyOmitsTheTruncationNoticeWhenNothingWasCapped(): void
    {
        $html = $this->render(new PenaltyPointsDriftAlertEmail($this->makeRows(3), 3));

        $this->assertStringNotContainsString('Showing', $html);
        $this->assertStringContainsString('3 qa_chunk_reviews row(s)', $html);
    }

    /**
     * password comes from the database as a job password, but it reaches an HTML table, so the
     * template escapes it rather than trusting the column's contents.
     */
    #[Test]
    public function renderedBodyEscapesThePassword(): void
    {
        $rows = $this->makeRows(1);
        $rows[0]['password'] = '<img src=x onerror=alert(1)>';

        $html = $this->render(new PenaltyPointsDriftAlertEmail($rows));

        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;img src=x', $html);
    }

    #[Test]
    public function subjectNamesTheDriftSoOperatorsCanFilterOnIt(): void
    {
        $email = new PenaltyPointsDriftAlertEmail($this->makeRows(1));

        $title = (new \ReflectionProperty($email, 'title'))->getValue($email);

        $this->assertSame('Alert: qa_chunk_reviews penalty_points drift detected', $title);
    }

    /**
     * send() fans out to every address in inc/Error_Mail_List.ini and must be a no-op when the list
     * is empty rather than attempting a send with no recipient.
     */
    #[Test]
    public function sendFansOutOncePerConfiguredAlertAddress(): void
    {
        $mailConf = @parse_ini_file(\Utils\Registry\AppConfig::$ROOT . '/inc/Error_Mail_List.ini', true);
        $expected = is_array($mailConf) && !empty($mailConf['email_list']) ? count($mailConf['email_list']) : 0;

        $email = $this->getMockBuilder(PenaltyPointsDriftAlertEmail::class)
            ->setConstructorArgs([$this->makeRows(1)])
            ->onlyMethods(['sendTo'])
            ->getMock();

        $email->expects($this->exactly($expected))->method('sendTo');

        $email->send();
    }
}
