<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 14/11/25
 * Time: 15:58
 *
 */

namespace Utils\Engines\Validators;

use DomainException;
use Exception;
use Lara\LaraException;
use ReflectionException;
use Utils\Engines\EnginesFactory;
use Utils\Engines\Lara;
use Utils\Engines\MMT\MMTServiceApi;
use Utils\Engines\MMT\MMTServiceApiException;
use Utils\Engines\Validators\Contracts\EngineValidatorObject;
use Utils\Registry\AppConfig;
use Utils\Validator\Contracts\AbstractValidator;
use Utils\Validator\Contracts\ValidatorObject;

class LaraEngineValidator extends AbstractValidator
{

    /**
     * @param EngineValidatorObject $object
     * @return ValidatorObject|null
     * @throws ReflectionException
     * @throws Exception
     */
    public function validate(ValidatorObject $object): ?ValidatorObject
    {
        /**
         * @var $newTestCreatedMT Lara
         */
        $newTestCreatedMT = EnginesFactory::createTempInstance($object->engineStruct);
        $config = $newTestCreatedMT->getConfigStruct();
        $config['segment'] = "Hello World";
        $config['source'] = "en-US";
        $config['target'] = "it-IT";

        try {
            $newTestCreatedMT->get($config);
        } catch (LaraException $e) {
            throw new DomainException($e->getMessage(), $e->getCode(), $e);
        }

        // Check MMT License
        $mmtLicense = $newTestCreatedMT->getEngineRecord()->getExtraParamsAsArray()['MMT-License'];

        if (!empty($mmtLicense)) {
            $mmtClient = MMTServiceApi::newInstance()
                ->setIdentity("Matecat", ltrim(AppConfig::$BUILD_NUMBER, 'v'))
                ->setLicense($mmtLicense);

            try {
                $mmtClient->me();
            } catch (MMTServiceApiException $e) {
                $code = $e->getCode();
                $message = "ModernMT license not valid, please verify its validity and try again";
                throw new DomainException($message, $code, $e);
            }
        }

        return null;
    }

}