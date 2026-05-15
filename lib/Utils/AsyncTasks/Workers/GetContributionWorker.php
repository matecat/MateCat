<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/05/16
 * Time: 20.36
 *
 */

namespace Utils\AsyncTasks\Workers;

use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\Analysis\Constants\InternalMatchesConstants;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobsMetadataMarshaller;
use Model\Jobs\JobStruct;
use Model\MTQE\Templates\DTO\MTQEWorkflowParams;
use Model\Translations\SegmentTranslationDao;
use Model\Users\UserStruct;
use ReflectionException;
use TypeError;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\Interface\MatchSorterInterface;
use Utils\AsyncTasks\Workers\Service\MatchSorter;
use Utils\Constants\EngineConstants;
use Utils\Constants\TranslationStatus;
use Utils\Contribution\GetContributionRequest;
use Utils\Engines\MyMemory;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;
use Utils\LQA\PostProcess;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Commons\AbstractElement;
use Utils\TaskRunner\Commons\AbstractWorker;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;
use Utils\TmKeyManagement\TmKeyManager;
use Utils\TmKeyManagement\TmKeyStruct;
use Utils\Tools\Utils;

class GetContributionWorker extends AbstractWorker
{
    private MatchSorterInterface $matchSorter;

    public function __construct(AMQHandler $queueHandler, ?MatchSorterInterface $matchSorter = null)
    {
        parent::__construct($queueHandler);
        $this->matchSorter = $matchSorter ?? new MatchSorter();
    }

    /**
     * @throws EndQueueException
     * @throws TypeError
     * @throws Exception
     */
    public function process(AbstractElement $queueElement): void
    {
        if (!$queueElement instanceof QueueElement) {
            throw new EndQueueException('Expected QueueElement, got ' . get_class($queueElement));
        }

        $this->_checkForReQueueEnd($queueElement);

        $contributionStruct = new GetContributionRequest($queueElement->params->toArray());

        $this->_checkDatabaseConnection();

        $this->_execGetContribution($contributionStruct);
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    protected function _execGetContribution(GetContributionRequest $contributionStruct): void
    {
        $jobStruct = $contributionStruct->getJobStruct();

        $featureSet = new FeatureSet();
        $featureSet->loadForProject($contributionStruct->getProjectStruct());

        [$mt_result, $matches] = $this->_getMatches($contributionStruct, $jobStruct, $jobStruct->target, $featureSet);

        $matches = $this->matchSorter->sortMatches($mt_result, $matches);

        if (!$contributionStruct->concordanceSearch) {
            //execute these lines only in segment contribution search,
            //in case of user concordance search, skip these lines
            $this->updateAnalysisSuggestion($matches, $contributionStruct);
        }

        $matches = array_slice($matches, 0, $contributionStruct->resultNum);
        $this->normalizeMTMatches($matches, $contributionStruct, $featureSet);

        $this->_publishPayload($matches, $contributionStruct, $featureSet, $jobStruct->target);

        // cross-language matches
        if (!empty($contributionStruct->crossLangTargets)) {
            $crossLangMatches = [];

            foreach ($contributionStruct->crossLangTargets as $lang) {
                // double-check for not black lang
                if ($lang !== '') {
                    [, $matches] = $this->_getMatches($contributionStruct, $jobStruct, $lang, $featureSet, true);

                    $matches = array_slice($matches, 0, $contributionStruct->resultNum);
                    $this->normalizeMTMatches($matches, $contributionStruct, $featureSet);

                    foreach ($matches as $match) {
                        $crossLangMatches[] = $match;
                    }
                }
            }

            if (!empty($crossLangMatches)) {
                $crossLangMatches = $this->matchSorter->sortMatches([], $crossLangMatches);
            }

            if (false === $contributionStruct->concordanceSearch) {
                $this->_publishPayload($crossLangMatches, $contributionStruct, $featureSet, $jobStruct->target, true);
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $content
     *
     * @throws Exception
     */
    protected function _publishPayload(array $content, GetContributionRequest $contributionStruct, FeatureSet $featureSet, string $targetLang, bool $isCrossLang = false): void
    {
        $type = 'contribution';

        if ($contributionStruct->concordanceSearch) {
            $type = 'concordance';
        }

        if ($isCrossLang) {
            $type = 'cross_language_matches';
        }

        $jobStruct = $contributionStruct->getJobStruct();

        /** @var MateCatFilter $Filter */
        $Filter = MateCatFilter::getInstance(
            $featureSet,
            $jobStruct->source,
            $targetLang,
            $contributionStruct->dataRefMap
        );

        foreach ($content as &$match) {
            if ($match['created_by'] == 'MT!') {
                $match['created_by'] = EngineConstants::MT; //MyMemory returns MT!
            }

            // Convert &#10; to layer2 placeholder for the UI
            // Those strings are on layer 1, force the transition to layer 2.
            $match['segment'] = $Filter->fromLayer1ToLayer2($match['segment'] ?? '');
            $match['translation'] = $Filter->fromLayer1ToLayer2($match['translation'] ?? '');
        }

        $_object = [
            '_type' => $type,
            'data' => [
                'id_job' => $contributionStruct->getJobStruct()->id,
                'passwords' => $contributionStruct->getJobStruct()->password,
                'payload' => [
                    'id_segment' => (string)$contributionStruct->segmentId,
                    'matches' => $content,
                ],
                'id_client' => $contributionStruct->id_client,
            ]
        ];

        $this->publishToNodeJsClients($_object);
        $this->_doLog($_object);
    }


    /**
     * @return list<string>
     *
     * @throws Exception
     */
    protected function _extractAvailableKeysForUser(GetContributionRequest $contributionStruct): array
    {
        //find all the job's TMs with write grants and make a contribution to them
        $tm_keys = TmKeyManager::getJobTmKeys($contributionStruct->getJobStruct()->tm_keys, 'r', 'tm', $contributionStruct->getUser()->uid, $contributionStruct->userRole);

        $keyList = [];
        if (!empty($tm_keys)) {
            foreach ($tm_keys as $tm_info) {
                if ($tm_info->key !== null) {
                    $keyList[] = $tm_info->key;
                }
            }
        }

        return $keyList;
    }

    /**
     * @param list<array<string, mixed>> $matches
     *
     * @throws Exception
     */
    public function normalizeMTMatches(array &$matches, GetContributionRequest $contributionStruct, FeatureSet $featureSet): void
    {
        $jobStruct = $contributionStruct->getJobStruct();

        foreach ($matches as &$match) {
            if ($this->matchSorter->isMtMatch($match)) {
                $match['match'] = EngineConstants::MT;

                $QA = new PostProcess($match['segment'], $match['translation']); // layer 1 here
                $QA->setFeatureSet($featureSet);
                $QA->setSourceSegLang($jobStruct->source);
                $QA->setTargetSegLang($jobStruct->target);
                $QA->realignMTSpaces();

                //this should every time be ok because MT preserve tags, but we use the check on the errors
                //for logic correctness
                if (!$QA->thereAreErrors()) {
                    // Note: DomDocument class forces the conversion of some entities like &#10; to the original character "\n"
                    $match['translation'] = $QA->getTrgNormalized();
                } else {
                    $this->_doLog($QA->getErrors());
                }
            }

            $user = new UserStruct();

            if (!$contributionStruct->getUser()->isAnonymous()) {
                $user = $contributionStruct->getUser();
            }

            $match['created_by'] = Utils::changeMemorySuggestionSource(
                $match,
                $contributionStruct->getJobStruct()->tm_keys,
                $user->uid
            );

            $match = $this->_matchRewrite($match);

            if ($contributionStruct->concordanceSearch) {
                $regularExpressions = $this->tokenizeSourceSearch($contributionStruct->getContexts()->segment ?? '');

                if (!$contributionStruct->fromTarget) {
                    [$match['segment'], $match['translation']] = $this->_formatConcordanceValues($match['segment'], $match['translation'], $regularExpressions);
                } else {
                    [$match['translation'], $match['segment']] = $this->_formatConcordanceValues($match['segment'], $match['translation'], $regularExpressions);
                }
            }
        }
    }

    /**
     * @param array<string, string> $regularExpressions
     *
     * @return array{string, string}
     */
    private function _formatConcordanceValues(string $_source, string $_target, array $regularExpressions): array
    {
        $_source = strip_tags(html_entity_decode($_source));
        $_source = preg_replace('#\x{20}{2,}#u', chr(0x20), $_source) ?? $_source;

        $_source = preg_replace(array_keys($regularExpressions), array_values($regularExpressions), $_source) ?? $_source;
        $_target = strip_tags(html_entity_decode($_target));

        return [$_source, $_target];
    }

    /**
     * @param array<string, mixed> $match
     *
     * @return array<string, mixed>
     */
    protected function _matchRewrite(array $match): array
    {
        if (!empty($match['score']) && $match['score'] >= 0.9) {
            $match['match'] = 'ICE_MT';
        }

        return $match;
    }

    /**
     * Build tokens to mark with highlight placeholders
     * the source RESULTS occurrences (correspondences) with text search incoming from ajax
     *
     * @return array<string, string> Pattern is in the key and replacement in the value
     */
    protected function tokenizeSourceSearch(string $text): array
    {
        $text = strip_tags(html_entity_decode($text));

        /**
         * remove most punctuation symbols
         *
         * \x{84} => „
         * \x{82} => ‚ //single low quotation mark
         * \x{91} => '
         * \x{92} => '
         * \x{93} => "
         * \x{94} => "
         * \x{B7} => · //Middle dot - Georgian comma
         * \x{AB} => «
         * \x{BB} => »
         */
        $tmp_text = preg_replace('#[\x{BB}\x{AB}\x{B7}\x{84}\x{82}\x{91}\x{92}\x{93}\x{94}.(){}\[\];:,\"\'\#+*]+#u', chr(0x20), $text) ?? $text;
        $tmp_text = str_replace(' - ', chr(0x20), $tmp_text);
        $tmp_text = preg_replace('#\x{20}{2,}#u', chr(0x20), $tmp_text) ?? $tmp_text;

        $tokenizedBySpaces = explode(" ", $tmp_text);
        $regularExpressions = [];
        foreach ($tokenizedBySpaces as $token) {
            $token = trim($token);
            if ($token != '') {
                $regularExp = '|(\s{1})?' . addslashes($token) . '(\s{1})?|ui'; /* unicode insensitive */
                $regularExpressions[$regularExp] = '$1#{' . $token . '}#$2'; /* unicode insensitive */
            }
        }

        //sort by the len of the Keys (regular expressions) in desc ordering
        /*
         *

            Normal Ordering:
            array(
                '|(\s{1})?a(\s{1})?|ui'         => '$1#{a}#$2',
                '|(\s{1})?beautiful(\s{1})?|ui' => '$1#{beautiful}#$2',
            );
            Obtained Result:
            preg_replace result => Be#{a}#utiful //WRONG

            With reverse ordering:
            array(
                '|(\s{1})?beautiful(\s{1})?|ui' => '$1#{beautiful}#$2',
                '|(\s{1})?a(\s{1})?|ui'         => '$1#{a}$2#',
            );
            Obtained Result:
            preg_replace result => #{be#{a}#utiful}#

         */
        uksort($regularExpressions, self::_sortByLenDesc(...));

        return $regularExpressions;
    }

     /**
      * @return array{array<string, mixed>, array<int, array<string, mixed>>}
      *
      * @throws EndQueueException
      * @throws ReQueueException
      * @throws Exception
      * @throws TypeError
      */
     protected function _getMatches(GetContributionRequest $contributionStruct, JobStruct $jobStruct, string $targetLang, FeatureSet $featureSet, bool $isCrossLang = false): array
    {
        $_config = [];
        $_config['segment'] = $contributionStruct->getContexts()->segment;
        $_config['source'] = $jobStruct->source;
        $_config['target'] = $targetLang;
        $_config['uid'] = $contributionStruct->getUser()->uid ?? 0;

        $_config['email'] = AppConfig::$MYMEMORY_API_KEY;

        $_config['context_before'] = $contributionStruct->getContexts()->context_before;
        $_config['context_after'] = $contributionStruct->getContexts()->context_after;
        $_config['id_user'] = $this->_extractAvailableKeysForUser($contributionStruct);
        $_config['num_result'] = $contributionStruct->resultNum;
        $_config['isConcordance'] = $contributionStruct->concordanceSearch;
        $_config['lara_style'] = $contributionStruct->lara_style;
        $_config['reasoning'] = $contributionStruct->reasoning;

        $_config['dialect_strict'] = $contributionStruct->dialect_strict;
        $_config['priority_key'] = $contributionStruct->tm_prioritization;
        $_config[JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value] = $contributionStruct->subfiltering_handlers;

        // penalty_key
        $penalty_key = TmKeyManager::getPenaltyMap($contributionStruct->getJobStruct()->tm_keys, 'r', 'tm', $contributionStruct->getUser()->uid, $contributionStruct->userRole);
        if (!empty($penalty_key)) {
            $_config['penalty_key'] = $penalty_key;
        }

        if (!empty($contributionStruct->public_tm_penalty)) {
            $_config['public_tm_penalty'] = $contributionStruct->public_tm_penalty;
        }

        if ($contributionStruct->concordanceSearch && $contributionStruct->fromTarget) {
            //invert direction
            $_config['target'] = $jobStruct->source;
            $_config['source'] = $targetLang;
        }

        if ($jobStruct->id_tms == 1) {
            /**
             * MyMemory Enabled
             */

            $_config['get_mt'] = true;
            $_config['mt_only'] = false;
            if ($jobStruct->id_mt_engine != 1) {
                /**
                 * Don't get MT contribution from MyMemory (Custom MT)
                 */
                $_config['get_mt'] = false;
            }

            if ($jobStruct->only_private_tm) {
                $_config['onlyprivate'] = true;
            }

            $_TMS = true; /* MyMemory */
        } elseif ($jobStruct->id_tms == 0 && $jobStruct->id_mt_engine == 1) {
            /**
             * MyMemory disabled but MT Enabled, and it is NOT a custom engine (MT through MyMemory)
             * So tell to MyMemory to get MT only
             */
            $_config['get_mt'] = true;
            $_config['mt_only'] = true;

            $_TMS = true; /* MyMemory */
        }

        if ($isCrossLang) {
            $_config['get_mt'] = false;
        }

        /**
         * if No TM server and No MT selected $_TMS is not defined,
         * so we want not to perform TMS Call
         * This calls the TMEngine to get memories
         */
        $tms_match = [];

        if (isset($_TMS)) {
            /** @var MyMemory $tmEngine */
            $tmEngine = $contributionStruct->getTMEngine($featureSet);
            $config = array_merge($tmEngine->getConfigStruct(), $_config);

            $temp_matches = [];

            if ($this->issetSourceAndTarget($config)) {
                $tmEngine->setMTPenalty(
                    $contributionStruct->mt_quality_value_in_editor ? 100 - $contributionStruct->mt_quality_value_in_editor : null
                ); // can be (100-102 == -2). In AbstractEngine it will be set as (100 - -2 == 102)
                $temp_matches = $tmEngine->get($config);
            }

            if (!empty($temp_matches)) {
                /** @var GetMemoryResponse $temp_matches */
                $tms_match = $temp_matches->get_matches_as_array(1);
            }
        }

        $mt_result = [];

        if (
            $jobStruct->id_mt_engine > 1 /* Get MT Directly */ &&
            !$contributionStruct->concordanceSearch &&
            !$isCrossLang
        ) {
            if ($contributionStruct->mt_quality_value_in_editor > 99 || empty($tms_match) || (int)str_replace("%", "", $tms_match[0]['match']) < 100) {
                /**
                 * Call The MT EnginesFactory IF
                 * - The user has set an MT Quality value in the editor > 99
                 * OR
                 * - The TM EnginesFactory has not returned any match
                 * OR
                 * - The TM EnginesFactory has returned a match with a score < 100
                 */
                $mt_engine = $contributionStruct->getMTEngine($featureSet);
                $config = $mt_engine->getConfigStruct();

                $config['pid'] = $jobStruct->id_project;
                $config['id_project'] = $contributionStruct->getProjectStruct()->id;
                $config['segment'] = $contributionStruct->getContexts()->segment;
                $config['source'] = $jobStruct->source;
                $config['target'] = $jobStruct->target;
                $config['email'] = AppConfig::$MYMEMORY_API_KEY;
                $config['segid'] = $contributionStruct->segmentId;
                $config['job_id'] = $jobStruct->id;
                $config['job_password'] = $jobStruct->password;
                $config['session'] = $contributionStruct->getSessionId();
                $config['all_job_tm_keys'] = $jobStruct->tm_keys;
                $config['context_list_before'] = $contributionStruct->context_list_before;
                $config['context_list_after'] = $contributionStruct->context_list_after;
                $config['user_id'] = $contributionStruct->getUser()->uid;
                $config['tuid'] = $jobStruct->id . ":" . $contributionStruct->segmentId;
                $config['translation'] = $contributionStruct->translation;
                $config['lara_style'] = $contributionStruct->lara_style;
                $config['reasoning'] = $contributionStruct->reasoning;
                $config[JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value] = $contributionStruct->subfiltering_handlers;

                $tm_keys = TmKeyManager::getOwnerKeys([$jobStruct->tm_keys], 'r');
                $config['keys'] = array_map(function (TmKeyStruct $tm_key): string {
                    return $tm_key->key ?? '';
                }, $tm_keys);

                if ($contributionStruct->mt_evaluation) {
                    $config['include_score'] = $contributionStruct->mt_evaluation;
                }

                if ($contributionStruct->mt_qe_workflow_enabled) {
                    $mt_qe_config = new MTQEWorkflowParams($contributionStruct->mt_qe_workflow_parameters ?? []);
                    $config['mt_qe_engine_id'] = $mt_qe_config->qe_model_version;
                }

                $mt_engine->setMTPenalty(
                    $contributionStruct->mt_quality_value_in_editor ? 100 - $contributionStruct->mt_quality_value_in_editor : null
                ); // can be (100-102 == -2). In AbstractEngine it will be set as (100 - -2 == 102)

                try {
                    $mtResponse = $mt_engine->get($config);
                    if (!empty($mtResponse->matches)) {
                        $mt_result = $mtResponse->get_matches_as_array(1)[0] ?? [];
                    }
                } catch (Exception $e) {
                    $this->_doLog($e->getMessage());
                }
            }
        }

        return [$mt_result, $tms_match];
    }

    /**
     * @param array<string, mixed> $_config
     */
    private function issetSourceAndTarget(array $_config): bool
    {
        return (isset($_config['source']) and $_config['source'] !== '' and isset($_config['target']) and $_config['target'] !== '');
    }

    private function _sortByLenDesc(string $stringA, string $stringB): int
    {
        if (strlen($stringA) == strlen($stringB)) {
            return 0;
        }

        return (strlen($stringB) < strlen($stringA)) ? -1 : 1;
    }

    /**
     * @param array<int, array<string, mixed>> $matches
     *
     * @throws ReflectionException
     * @throws Exception
     */
    private function updateAnalysisSuggestion(array $matches, GetContributionRequest $contributionStruct): void
    {
        if (
            count($matches) > 0 and
            $contributionStruct->segmentId !== null and
            !empty($contributionStruct->getJobStruct()->id)
        ) {
            $segmentTranslation = SegmentTranslationDao::findBySegmentAndJob($contributionStruct->segmentId, $contributionStruct->getJobStruct()->id);

            if ($segmentTranslation === null) {
                return;
            }

            // Run updateFirstTimeOpenedContribution ONLY on translations in NEW status
            if ($segmentTranslation->status != TranslationStatus::STATUS_NEW) {
                return;
            }

            //copy the first match before we rewrite the created_by field
            $match = $matches[0];

            foreach ($matches as $k => $m) {
                // normalize data for saving `suggestions_array`

                if ($m['created_by'] == 'MT!') {
                    $matches[$k]['created_by'] = EngineConstants::MT; //MyMemory returns MT!
                } else {
                    $user = new UserStruct();

                    if (!$contributionStruct->getUser()->isAnonymous()) {
                        $user = $contributionStruct->getUser();
                    }

                    $matches[$k]['created_by'] = Utils::changeMemorySuggestionSource(
                        $m,
                        $contributionStruct->getJobStruct()->tm_keys,
                        $user->uid
                    );
                }
            }

            $suggestions_json_array = json_encode($matches);

            $data = [];
            $data['suggestions_array'] = $suggestions_json_array;
            $data['suggestion'] = $match['raw_translation']; // this is Layer 0
            $data['translation'] = $match['raw_translation']; // this is Layer 0
            $data['suggestion_match'] = str_replace('%', '', $match['match']);

            //If the analysis was not requested (engine not used), some database fields are not set, in particular suggestion_source
            if (empty($segmentTranslation->suggestion_source)) {
                if (!str_contains($match['created_by'], InternalMatchesConstants::MT)) {
                    $data['suggestion_source'] = InternalMatchesConstants::TM;
                } else {
                    $data['suggestion_source'] = InternalMatchesConstants::MT;
                }
            }

            $where = [
                'id_segment' => $contributionStruct->segmentId,
                'id_job' => $contributionStruct->getJobStruct()->id,
                'status' => TranslationStatus::STATUS_NEW
            ];

            SegmentTranslationDao::updateFirstTimeOpenedContribution($data, $where);
        }
    }
}