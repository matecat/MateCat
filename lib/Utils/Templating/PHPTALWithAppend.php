<?php

namespace Utils\Templating;

use PHPTAL;

/**
 * @property string $basepath
 * @property string $hostpath
 * @property string $build_number
 * @property string $support_mail
 * @property PHPTalBoolean $enableMultiDomainApi
 * @property int $ajaxDomainsNumber
 * @property int $maxFileSize
 * @property int $maxTMXFileSize
 * @property array<string, array<int, array{key: string, value: string}>>|null $flashMessages
 * @property PHPTalMap $user_plugins
 * @property PHPTalBoolean $isLoggedIn
 * @property string $userMail
 * @property PHPTalBoolean $isAnInternalUser
 * @property list<string> $footer_js
 * @property list<string> $config_js
 * @property list<string> $css_resources
 * @property string $googleAuthURL
 * @property string $githubAuthUrl
 * @property string $linkedInAuthUrl
 * @property string $microsoftAuthUrl
 * @property string $facebookAuthUrl
 * @property PHPTalBoolean $googleDriveEnabled
 * @property string $gdriveAuthURL
 * @property string $x_nonce_unique_id
 * @property string|null $x_self_ajax_location_hosts
 */
class PHPTALWithAppend extends PHPTAL
{

    protected array $internal_store = [];

    /**
     *
     * This method populates an array of arrays that can be used
     * to push values on the template so that plugins can append
     * their own JavaScript or assets.
     *
     * @param string $name
     * @param mixed $value
     */
    public function append(string $name, mixed $value): void
    {
        if (!array_key_exists($name, $this->internal_store)) {
            $this->internal_store[$name] = [];
        }

        $this->internal_store[$name][] = $value;

        $this->$name = $this->internal_store[$name];
    }
}