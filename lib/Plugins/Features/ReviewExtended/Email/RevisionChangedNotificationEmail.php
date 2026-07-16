<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 11/06/2019
 * Time: 13:06
 */

namespace Plugins\Features\ReviewExtended\Email;

use Exception;
use Model\Users\UserStruct;
use TypeError;
use Utils\Email\AbstractEmail;

class RevisionChangedNotificationEmail extends AbstractEmail
{

    /**
     * @var UserStruct|null
     */
    protected ?UserStruct $changeAuthor;
    protected string $segmentUrl;
    /**
     * @var UserStruct
     */
    protected UserStruct $recipientUser;

    protected ?string $title = 'Revised segment changed';
    /** @var array<string, mixed> */
    protected array $data;
    /** @var array<string, mixed> */
    protected array $_segmentInfo;

    /**
     * @param array<string, mixed>  $segmentInfo
     * @param array<string, mixed>  $data
     * @param string                $segmentUrl
     * @param UserStruct|null       $changeAuthor
     * @throws TypeError
     */
    public function __construct(array $segmentInfo, array $data, string $segmentUrl, ?UserStruct $changeAuthor = null)
    {
        $this->_segmentInfo = $segmentInfo;
        $this->data = $data;
        $this->recipientUser = $data['recipient'];
        $this->segmentUrl = $segmentUrl;
        $this->changeAuthor = $changeAuthor;

        $this->_setlayout('skeleton.html');
        $this->_settemplate('Revise/second_pass_segment_changed_notice.html');
    }

    /**
     * @return array<string, mixed>
     */
    protected function _getTemplateVariables(): array
    {
        return [
            'changeAuthor' => $this->changeAuthor?->toArray(),
            'recipientUser' => $this->data['recipient']->toArray(),
            'segmentUrl' => $this->segmentUrl,
            'data' => $this->data,
            'segmentInfo' => $this->_segmentInfo
        ];
    }

    /**
     * @throws Exception
     */
    public function send(): void
    {
        $recipientEmail = $this->recipientUser->email;
        if ($recipientEmail !== null && false === $this->isRecipientTheChangeAuthor($recipientEmail, $this->changeAuthor)) {
            $this->sendTo($recipientEmail, $this->recipientUser->fullName());
        }
    }

    /**
     * @param string $email
     * @param UserStruct|null $user
     *
     * @return bool
     */
    private function isRecipientTheChangeAuthor(string $email, UserStruct $user = null): bool
    {
        if (null === $user) {
            return false;
        }

        return $user->getEmail() === $email;
    }
}