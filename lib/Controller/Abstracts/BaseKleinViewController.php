<?php

namespace Controller\Abstracts;

use Controller\API\Commons\ViewValidators\MandatoryKeysValidator;
use Exception;
use Klein\App;
use Klein\Request;
use Klein\Response;
use Klein\ServiceProvider;
use Model\ConnectedServices\Oauth\Facebook\FacebookProvider;
use Model\ConnectedServices\Oauth\Github\GithubProvider;
use Model\ConnectedServices\Oauth\Google\GoogleProvider;
use Model\ConnectedServices\Oauth\LinkedIn\LinkedInProvider;
use Model\ConnectedServices\Oauth\Microsoft\MicrosoftProvider;
use Model\ConnectedServices\Oauth\OauthClient;
use PHPTAL;
use Utils\Registry\AppConfig;
use Utils\Templating\PHPTalBoolean;
use Utils\Templating\PHPTalMap;
use Utils\Templating\PHPTALWithAppend;
use Utils\Tools\Utils;

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 06/10/16
 * Time: 10:24
 */
abstract class BaseKleinViewController extends AbstractStatefulKleinController implements IController
{

    protected bool $isView = true;

    /**
     * @var PHPTALWithAppend
     */
    protected PHPTAL $view;

    /**
     * @var integer
     */
    protected int $httpCode;

    /**
     * @param Request $request
     * @param Response $response
     * @param ServiceProvider|null $service
     * @param App|null $app
     *
     * @throws Exception
     */
    public function __construct(Request $request, Response $response, ?ServiceProvider $service = null, ?App $app = null)
    {
        parent::__construct($request, $response, $service, $app);
        $this->timingLogFileName = 'view_controller_calls_time.log';
        $this->appendValidator(new MandatoryKeysValidator($this));
    }

    /**
     * @param string $template_name
     * @param array $params
     * @param int $code
     *
     * @return void
     * @throws Exception
     */
    public function setView(string $template_name, array $params = [], int $code = 200): void
    {
        $this->view = new PHPTALWithAppend(AppConfig::$TEMPLATE_ROOT . "/$template_name");
        $this->httpCode = $code;

        $this->view->{'basepath'} = AppConfig::$BASEURL;
        $this->view->{'hostpath'} = AppConfig::$HTTPHOST;
        $this->view->{'build_number'} = AppConfig::$BUILD_NUMBER;
        $this->view->{'support_mail'} = AppConfig::$SUPPORT_MAIL;
        $this->view->{'enableMultiDomainApi'} = new PHPTalBoolean(AppConfig::$ENABLE_MULTI_DOMAIN_API);
        $this->view->{'ajaxDomainsNumber'} = AppConfig::$AJAX_DOMAINS;
        $this->view->{'maxFileSize'} = AppConfig::$MAX_UPLOAD_FILE_SIZE;
        $this->view->{'maxTMXFileSize'} = AppConfig::$MAX_UPLOAD_TMX_FILE_SIZE;
        $this->view->{'flashMessages'} = FlashMessage::flush();

        if ($this->isLoggedIn()) {
            // Load the feature set for the user (plus the autoloaded ones)
            $this->featureSet->loadFromUserEmail($this->user->email);
        }

        $this->view->{'user_plugins'} = new PHPTalMap($this->featureSet->getCodes());
        $this->view->{'isLoggedIn'} = new PHPTalBoolean($this->isLoggedIn());
        $this->view->{'userMail'} = $this->getUser()->email;
        $this->view->{'isAnInternalUser'} = new PHPTalBoolean($this->featureSet->filter("isAnInternalUser", $this->getUser()->email));

        $this->view->{'footer_js'} = [];
        $this->view->{'config_js'} = [];
        $this->view->{'css_resources'} = [];

        // init oauth clients
        $this->view->{'googleAuthURL'} = (AppConfig::$GOOGLE_OAUTH_CLIENT_ID) ? OauthClient::getInstance(GoogleProvider::PROVIDER_NAME)->getAuthorizationUrl($_SESSION) : "";
        $this->view->{'githubAuthUrl'} = (AppConfig::$GITHUB_OAUTH_CLIENT_ID) ? OauthClient::getInstance(GithubProvider::PROVIDER_NAME)->getAuthorizationUrl($_SESSION) : "";
        $this->view->{'linkedInAuthUrl'} = (AppConfig::$LINKEDIN_OAUTH_CLIENT_ID) ? OauthClient::getInstance(LinkedInProvider::PROVIDER_NAME)->getAuthorizationUrl($_SESSION) : "";
        $this->view->{'microsoftAuthUrl'} = (AppConfig::$LINKEDIN_OAUTH_CLIENT_ID) ? OauthClient::getInstance(MicrosoftProvider::PROVIDER_NAME)->getAuthorizationUrl($_SESSION) : "";
        $this->view->{'facebookAuthUrl'} = (AppConfig::$FACEBOOK_OAUTH_CLIENT_ID) ? OauthClient::getInstance(FacebookProvider::PROVIDER_NAME)->getAuthorizationUrl($_SESSION) : "";

        $this->view->{'googleDriveEnabled'} = new PHPTalBoolean(AppConfig::isGDriveConfigured());
        $this->view->{'gdriveAuthURL'} = ($this->isLoggedIn() && AppConfig::isGDriveConfigured()) ? OauthClient::getInstance(
            GoogleProvider::PROVIDER_NAME,
            AppConfig::$HTTPHOST . "/gdrive/oauth/response"
        )->getAuthorizationUrl($_SESSION, 'drive') : "";

        /**
         * This is a unique ID generated at runtime.
         * It is injected into the nonce attribute of `< script >` tags to allow browsers to safely execute the contained CSS and JavaScript.
         */
        $this->view->{'x_nonce_unique_id'} = Utils::uuid4();
        $this->view->{'x_self_ajax_location_hosts'} = AppConfig::$ENABLE_MULTI_DOMAIN_API ? " *.ajax." . parse_url(AppConfig::$HTTPHOST)['host'] : null;

        $this->addParamsToView($params);

        $this->view->setOutputMode(PHPTAL::HTML5);
    }

    /**
     * @throws Exception
     */
    public function addParamsToView(array $params): void
    {
        if (!isset($this->view)) {
            throw new Exception('View not set. Method `setView` must be called before `addParams`');
        }

        foreach ($params as $key => $value) {
            $this->view->{$key} = $value;
        }
    }

    /**
     * @param $httpCode integer
     */
    public function setCode(int $httpCode): void
    {
        $this->httpCode = $httpCode;
    }

    /**
     * @param int|null $code
     *
     * @return never
     */
    public function render(?int $code = null): never
    {
        $this->response->noCache();
        $this->response->code($code ?? $this->httpCode);
        $this->response->body($this->view->execute());
        $this->response->send();
        $this->_logWithTime();
        die();
    }

    public function redirectToWantedUrl(): never
    {
        header("Location: " . AppConfig::$HTTPHOST . AppConfig::$BASEURL . $_SESSION['wanted_url'], false);
        unset($_SESSION['wanted_url']);
        exit;
    }

}