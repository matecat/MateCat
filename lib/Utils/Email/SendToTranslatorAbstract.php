<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 25/04/17
 * Time: 22.14
 *
 */

namespace Utils\Email;


use DateInvalidTimeZoneException;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Model\Translators\JobsTranslatorsStruct;
use Model\Users\UserStruct;
use ReflectionException;

abstract class SendToTranslatorAbstract extends AbstractEmail
{

    protected UserStruct $user;
    protected string $projectName;
    protected JobsTranslatorsStruct $translator;
    protected array $_RoutesMethod;

    /**
     * @throws DateInvalidTimeZoneException
     * @throws Exception
     */
    public function __construct(UserStruct $user, JobsTranslatorsStruct $translator, string $projectName)
    {
        $this->user = $user;
        $this->translator = $translator;
        $this->title = "Matecat - Translation Job";
        $this->projectName = $projectName;

        $translator->delivery_date =
            (new Datetime($translator->delivery_date))
                ->setTimezone(new DateTimeZone($this->_offsetToTimeZone($translator->job_owner_timezone)))
                ->format(DateTimeInterface::RFC850);

        $this->_setLayout('skeleton.html');
    }

    /**
     * @throws Exception
     */
    public function send(): void
    {
        $recipient = [$this->translator->email];

        //we need to get the bodyHtmlMessage only once because JWT changes if called more than once
        // otherwise html message will differ from the alternative text message
        $bodyHtmlMessage = $this->_buildMessageContent();

        $this->doSend(
            $recipient,
            $this->title,
            $this->_buildHTMLMessage($bodyHtmlMessage),
            $this->_buildTxtMessage($bodyHtmlMessage)
        );
    }

    /**
     * @throws ReflectionException
     */
    protected function _getTemplateVariables(): array
    {
        $userRecipient = $this->translator->getUser()->getArrayCopy();
        if (!empty($userRecipient['uid'])) {
            $userRecipient['_name'] = $userRecipient['first_name'] . " " . $userRecipient['last_name'];
        } else {
            $userRecipient['_name'] = $this->translator->email;
        }

        return [
            'sender' => $this->user->toArray(),
            'user' => $userRecipient,
            'email' => $this->translator->email,
            'delivery_date' => $this->translator->delivery_date,
            'project_url' => call_user_func(
                $this->_RoutesMethod,
                $this->projectName,
                $this->translator->id_job,
                $this->translator->job_password,
                $this->translator->source,
                $this->translator->target
            )
        ];
    }

    protected function _offsetToTimeZone($offset)
    {
        $offset = $offset * 60 * 60;
        $abbreviations_list = array_reverse(timezone_abbreviations_list());
        foreach ($abbreviations_list as $abbreviation) {
            foreach ($abbreviation as $city) {
                if ($city['offset'] == $offset && $city['timezone_id'] != null) {
                    return $city['timezone_id'];
                }
            }
        }

        return 'UTC';
    }

}