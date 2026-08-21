<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 19/11/2016
 * Time: 17:06
 */

namespace Utils\Email;

use DomainException;
use Exception;
use InvalidArgumentException;
use Utils\ActiveMQ\WorkerClient;
use Utils\AsyncTasks\Workers\MailWorker;
use Utils\Logger\LoggerFactory;
use Utils\Registry\AppConfig;

abstract class AbstractEmail
{

    protected ?string $title = null;

    protected string $_layout_path;
    protected string $_template_path;

    /**
     * @return array<string, mixed>
     */
    abstract protected function _getTemplateVariables(): array;


    /**
     * @return void
     */
    abstract function send(): void;

    protected function _setLayout(string $layout): void
    {
        $this->_layout_path = AppConfig::$TEMPLATE_ROOT . '/Emails/' . $layout;
    }

    protected function _setLayoutByPath(string $path): void
    {
        $this->_layout_path = $path;
    }

    protected function _setTemplate(string $template): void
    {
        $this->_template_path = AppConfig::$TEMPLATE_ROOT . '/Emails/' . $template;
    }

    protected function _setTemplateByPath(string $path): void
    {
        $this->_template_path = $path;
    }

    /**
     * @param array<string, mixed> $mailConf
     * @throws DomainException
     * @throws InvalidArgumentException
     */
    protected function _enqueueEmailDelivery(array $mailConf): void
    {
        WorkerClient::enqueue(
            'MAIL',
            MailWorker::class,
            $mailConf,
            ['persistent' => WorkerClient::$_HANDLER->persistent]
        );

        LoggerFactory::doJsonLog('Message has been sent');
    }

    /**
     * @return string
     */
    /**
     * Make a value safe to place in a mail header.
     *
     * Team names are stored as the user typed them, so a name carrying CR or LF could
     * otherwise continue the Subject header into one of its own. PHPMailer strips line breaks
     * from headers as well, but a header context should not depend on a library internal.
     */
    protected function headerSafe(?string $value): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', str_replace(["\r", "\n"], ' ', $value ?? ''));

        return trim($collapsed ?? '');
    }

    /**
     * The one place email template variables are escaped.
     *
     * Doing it here rather than in each `<?=` is the whole point: eighty of the eighty-seven
     * interpolations across `lib/View/Emails` were raw when this was written, which is what a rule
     * applied by hand converges on. {@see EmailValue} escapes on read, so a template writes a value
     * and gets a safe one without knowing to ask.
     */
    protected function _buildMessageContent(): string
    {
        ob_start();
        extract(EmailValue::wrapAll($this->_getTemplateVariables(), $this->verbatimKeys()));
        include($this->_template_path);

        return ob_get_clean() ?: '';
    }

    /**
     * The template values a reader must receive exactly as given: the links the email exists to
     * offer, and the addresses it is about. Everything else is defanged — {@see LinkDefanger}.
     *
     * Listed by key name rather than detected by shape, so no attacker-written value can exempt
     * itself by imitating a link or an address. Matched at any depth, which is what covers the
     * `email` inside a `user`, `sender` or `commenter` array.
     *
     * A subclass carrying its own link adds it here. Forgetting to is a broken button in the next
     * render of that email rather than a silent gap, which is the failure mode this list is
     * arranged to have.
     *
     * @return list<string>
     */
    protected function verbatimKeys(): array
    {
        return ['signup_url', 'url', 'password_reset_url', 'activation_url', 'email'];
    }

    /**
     * @param string|null $messageContent
     *
     * @return string
     */
    protected function _buildHTMLMessage(?string $messageContent = null): string
    {
        ob_start();
        extract(EmailValue::wrapAll($this->_getLayoutVariables($messageContent), $this->verbatimKeys()));
        include($this->_layout_path);

        return ob_get_clean() ?: '';
    }

    /**
     * @param string|null $messageBody
     *
     * @return array<string, mixed>
     */
    protected function _getLayoutVariables(?string $messageBody = null): array
    {
        return [
            'title' => $this->title ?? 'Matecat',
            // Already-rendered markup: it went through _buildMessageContent(), where its own values
            // were escaped. Escaping it again would show the reader the tags of their own email.
            // The allowlist in EmailTemplateEscapingTest names this and nothing else.
            'messageBody' => EmailValue::raw(!empty($messageBody) ? $messageBody : $this->_buildMessageContent()),
            'closingLine' => "Kind regards, ",
            'showTitle' => false
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function _getDefaultMailConf(): array
    {
        $mailConf = [];

        $mailConf['Host'] = AppConfig::$SMTP_HOST;
        $mailConf['port'] = AppConfig::$SMTP_PORT;
        $mailConf['sender'] = AppConfig::$SMTP_SENDER;
        $mailConf['hostname'] = AppConfig::$SMTP_HOSTNAME;

        $mailConf['from'] = AppConfig::$SMTP_SENDER;
        $mailConf['fromName'] = AppConfig::$MAILER_FROM_NAME;
        $mailConf['returnPath'] = AppConfig::$MAILER_RETURN_PATH;

        return $mailConf;
    }

    /**
     * @throws Exception
     */
    protected function sendTo(string $address, string $name): void
    {
        $recipient = [$address, $name];

        $this->doSend(
            $recipient,
            $this->title ?? 'Matecat',
            $this->_buildHTMLMessage(),
            $this->_buildTxtMessage($this->_buildMessageContent())
        );
    }

    /**
     * @param array<int, string|null> $address
     *
     * @throws Exception
     */
    protected function doSend(array $address, ?string $subject, string $htmlBody, string $altBody): bool
    {
        $mailConf = $this->_getDefaultMailConf();

        $mailConf['address'] = $address;
        $mailConf['subject'] = $subject;

        $mailConf['htmlBody'] = $htmlBody;
        $mailConf['altBody'] = $altBody;

        $this->_enqueueEmailDelivery($mailConf);

        return true;
    }

    /**
     * @param string $messageBody
     *
     * @return string
     */
    protected function _buildTxtMessage(string $messageBody): string
    {
        $messageBody = preg_replace("#<[/]*span[^>]*>#i", "", $messageBody) ?? '';
        $messageBody = preg_replace("#<[/]*strong[^>]*>#i", "", $messageBody) ?? '';
        $messageBody = preg_replace("#<[/]*(ol|ul|li)[^>]*>#i", "\t", $messageBody) ?? '';
        $messageBody = preg_replace("#<[/]*(p)[^>]*>#i", "", $messageBody) ?? '';
        $messageBody = preg_replace("#<a.*?href=[\"'](.*)[\"'][^>]*>(.*?)</a>#i", "$2 $1", $messageBody) ?? '';
        $messageBody = preg_replace("#<br[^>]*>#i", "\r\n", $messageBody) ?? '';

        // Decoded last, and with the flags the values were escaped with.
        //
        // The flags have to match {@see EmailValue}: it escapes as HTML5, where an apostrophe
        // becomes `&apos;`, and the default decoding is HTML 4.01, which has no such entity — so
        // "O'Brien" reached the reader as "O&apos;Brien". Nothing else in this method notices,
        // because everything it rewrites is a tag.
        //
        // Decoding after the tags have been handled rather than before keeps a value that merely
        // contains markup from becoming markup: text a user typed as `&lt;br&gt;` stays the four
        // characters they wrote instead of turning into a line break.
        return html_entity_decode($messageBody, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

}