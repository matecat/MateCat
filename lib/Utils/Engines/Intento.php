<?php

class Engines_Intento extends Engines_AbstractEngine {

    const INTENTO_USER_AGENT = 'Intento.MatecatPlugin/1.0.0';
    const INTENTO_PROVIDER_KEY = 'd3ic8QPYVwRhy6IIEHi6yiytaORI2kQk';
    const INTENTO_API_URL = 'https://api.inten.to';

    protected $_config = array(
            'segment' => null,
            'source'  => null,
            'target'  => null
        );

    public function __construct($engineRecord) {
        parent::__construct($engineRecord);
        if ($this->engineRecord->type != "MT") {
            throw new Exception("Engine {$this->engineRecord->id} is not a MT engine, found {$this->engineRecord->type} -> {$this->engineRecord->class_load}");
        }
    }

    /**
     * @param $lang
     *
     * @return mixed
     * @throws Exception
     */
    protected function _fixLangCode($lang) {
        $r = explode("-", strtolower(trim($lang)));

        return $r[0];
    }

    /**
     * @param $rawValue
     *
     * @return array
     */
    protected function _decode($rawValue, $parameters = null, $function = null) {
        $all_args = func_get_args();
        if (is_string($rawValue)) {
            $result = json_decode($rawValue, false);
            if ($result AND isset($result->id)) {
                $id = $result->id;
                if (isset($result->response) AND isset($result->done) AND $result->done == true) {
                    $text = $result->response[0]->results[0];
                    $decoded = array(
                        'data' => array(
                            'translations' => array(
                                array('translatedText' => $this->_resetSpecialStrings($text))
                            )
                        )
                    );

                } elseif (isset($result->done) AND $result->done == false) {
                    sleep(2);
                    $cnf = array('async' => true, 'id' => $id);

                    return $this->_curl_async($cnf, $parameters, $function);
                } elseif (isset($result->error) AND $result->error != null) {
                    $decoded = array(
                        'error' => array(
                            'code' => '-2',
                            'message' => $result->error->reason
                        )
                    );
                } else {
                    $cnf = array('async' => true, 'id' => $id);

                    return $this->_curl_async($cnf, $parameters, $function);
                }
            } else {
                $decoded = array(
                    'error' => array(
                        'code' => '-1',
                        'message' => ''
                    )
                );
            }

        } else {
            if ($rawValue AND array_key_exists('responseStatus', $rawValue) AND array_key_exists('error', $rawValue)) {
                $_response_error = json_decode($rawValue['error']["response"], true);
                $decoded = array(
                    'error' => array(
                        'code' => array_key_exists('error', $_response_error) ? array_key_exists('code', $_response_error['error']) ? -$_response_error['error']['code'] : '-1' : '-1',
                        'message' => array_key_exists('error', $_response_error) ? array_key_exists('message', $_response_error['error']) ? $_response_error['error']['message'] : '' : ''
                    )
                );
            } else {
                $decoded = array(
                    'error' => array(
                        'code' => '-1',
                        'message' => ''
                    )
                );
            }

        }

        $mt_result = new Engines_Results_MT($decoded);

        if ($mt_result->error->code < 0) {
            $mt_result = $mt_result->get_as_array();
            $mt_result['error'] = (array)$mt_result['error'];

            return $mt_result;
        }

        $mt_match_res = new Engines_Results_MyMemory_Matches(
            $this->_preserveSpecialStrings($parameters['context']['text']),
            $mt_result->translatedText,
            100 - $this->getPenalty() . "%",
            "MT-" . $this->getName(),
            date("Y-m-d")
        );

        $mt_res = $mt_match_res->getMatches();

        return $mt_res;

    }

    public function get($_config) {
        $_config['segment'] = $this->_preserveSpecialStrings($_config['segment']);
        $_config['source'] = $this->_fixLangCode($_config['source']);
        $_config['target'] = $this->_fixLangCode($_config['target']);

        $parameters = array();
        if ($this->apikey != null AND $this->apikey != '') {
            $_headers = array('apikey: ' . $this->apikey, 'Content-Type: application/json');
        }

        $parameters['context']['from'] = $_config['source'];
        $parameters['context']['to'] = $_config['target'];
        $parameters['context']['text'] = $_config['segment'];
        $provider = $this->provider;
        if ($provider != null AND $provider != '') {
            $parameters['service']['async'] = true;
            $parameters['service']['provider'] = $provider;
            if ($this->providerkey != null AND $this->providerkey != '') {
                $providerkey = json_decode($this->providerkey);
                $parameters['service']['auth'][$provider] = array($providerkey);
            }
            if ($this->providercategory != null AND $this->providercategory != '') {
                $parameters['context']['category'] = $this->providercategory;
            }
        }

        $this->_setIntentoUserAgent(); //Set Intento User Agent

        $this->_setAdditionalCurlParams(
            array(
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($parameters),
                CURLOPT_HTTPHEADER => $_headers
            )
        );

        $this->call("translate_relative_url", $parameters, true);

        return $this->result;

    }

    protected function _curl_async($config, $parameters = null, $function = null) {
        $id = $config['id'];
        if ($this->apikey != null AND $this->apikey != '') {
            $_headers = array('apikey: ' . $this->apikey, 'Content-Type: application/json');
        }

        $this->_setIntentoUserAgent(); //Set Intento User Agent

        $this->_setAdditionalCurlParams(
            array(
                CURLOPT_HTTPHEADER => $_headers
            )
        );

        $url = self::INTENTO_API_URL . '/operations/' . $id;
        $curl_opt = array(
            CURLOPT_HTTPGET => true,
            CURLOPT_TIMEOUT => static::GET_REQUEST_TIMEOUT
        );
        $rawValue = $this->_call($url, $curl_opt);

        return $this->_decode($rawValue, $parameters, $function);

    }

    public function set($_config) {

        //if engine does not implement SET method, exit
        return true;
    }

    public function update($config) {

        //if engine does not implement UPDATE method, exit
        return true;
    }

    public function delete($_config) {

        //if engine does not implement DELETE method, exit
        return true;

    }

    /**
     *  Set Matecat + Intento user agent
     */
    private function _setIntentoUserAgent() {
        $this->curl_additional_params[CURLOPT_USERAGENT] = self::INTENTO_USER_AGENT . ' ' . INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER;
    }

    /**
     * Get provider list
     */
    public static function getProviderList() {
        $redisHandler = new RedisHandler();
        $conn = $redisHandler->getConnection();
        $result = $conn->get('IntentoProviders');
        if ($result) {
            return json_decode($result);
        }

        $_api_url = self::INTENTO_API_URL . '/ai/text/translate?fields=auth&integrated=true&published=true';
        $curl = curl_init($_api_url);
        $_params = array(
            CURLOPT_HTTPHEADER => array('apikey: ' . self::INTENTO_PROVIDER_KEY, 'Content-Type: application/json'),
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER . ' ' . self::INTENTO_USER_AGENT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        );
        curl_setopt_array($curl, $_params);
        $response = curl_exec($curl);
        $result = json_decode($response);
        curl_close($curl);
        $_providers = array();
        if ($result) {
            foreach ($result as $value) {
                $example = (array)$value->auth;
                $example = json_encode($example);
                $_providers[$value->id] = array('id' => $value->id, 'name' => $value->name, 'vendor' => $value->vendor, 'auth_example' => $example);
            }
            ksort($_providers);
        }
        $conn->set('IntentoProviders', json_encode($_providers));
        $conn->expire('IntentoProviders', 60 * 60 * 24);

        return $_providers;
    }
}
