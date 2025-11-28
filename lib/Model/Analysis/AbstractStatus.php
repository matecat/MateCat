<?php

namespace Model\Analysis;

use Controller\API\Commons\Exceptions\AuthenticationError;
use Exception;
use Model\Analysis\Constants\MatchConstantsFactory;
use Model\Conversion\ZipArchiveHandler;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao as FileMetadataDao;
use Model\Jobs\ChunkDao;
use Model\Jobs\JobStruct;
use Model\Projects\MetadataDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Users\UserStruct;
use ReflectionException;
use Utils\ActiveMQ\AMQHandler;
use Utils\Constants\ProjectStatus;
use Utils\Langs\LanguageDomains;
use Utils\OutsourceTo\OutsourceAvailable;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;
use Utils\Url\CanonicalRoutes;
use View\API\App\Json\Analysis\AnalysisChunk;
use View\API\App\Json\Analysis\AnalysisFile;
use View\API\App\Json\Analysis\AnalysisJob;
use View\API\App\Json\Analysis\AnalysisProject;
use View\API\App\Json\Analysis\AnalysisProjectSummary;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 04/05/15
 * Time: 13.37
 *
 *
 */
abstract class AbstractStatus
{

    /**
     * Carry the result from Executed Controller Action and returned in JSON format to the Client
     *
     * @var ?AnalysisProject
     */
    protected ?AnalysisProject $result = null;

    protected int    $total_segments   = 0;
    protected array  $_resultSet       = [];
    protected int    $_others_in_queue = 0;
    protected array  $_project_data    = [];
    protected string $status_project   = "";

    protected FeatureSet $featureSet;
    /**
     * @var ProjectStruct
     */
    protected ProjectStruct $project;
    /**
     * @var UserStruct|null
     */
    protected ?UserStruct $user;
    /**
     * @var mixed
     */
    protected mixed $subject;

    /**
     * @param array           $_project_data
     * @param FeatureSet      $features
     * @param UserStruct|null $user
     *
     * @throws ReflectionException
     */
    public function __construct(array $_project_data, FeatureSet $features, ?UserStruct $user = null)
    {
        if (is_null($user)) { // avoid null pointer exception when calling methods on class property user
            $user      = new UserStruct();
            $user->uid = -1;
        }
        $this->user          = $user;
        $this->project       = ProjectDao::findById($_project_data[ 0 ][ 'pid' ], 60 * 60);
        $this->_project_data = $_project_data;
        $this->featureSet    = $features;
    }

    /**
     * @return AnalysisProject
     */
    public function getResult(): AnalysisProject
    {
        return $this->result;
    }

    /**
     * Fetch data for the project
     *
     * @throws ReflectionException
     */
    protected function _fetchProjectData(): AbstractStatus
    {
        $this->_resultSet = AnalysisDao::getProjectStatsVolumeAnalysis($this->project->id);

        try {
            $amqHandler         = new AMQHandler();
            $segmentsBeforeMine = $amqHandler->getActualForQID($this->project->id);
        } catch (Exception) {
            $segmentsBeforeMine = null;
        }

        $this->_others_in_queue = ($segmentsBeforeMine > 0 ? $segmentsBeforeMine : 0);

        $this->total_segments = count($this->_resultSet);

        //get the status of a project
        $this->status_project = $this->_project_data[ 0 ][ 'status_analysis' ];

        $subject_handler = LanguageDomains::getInstance();
        $subjects        = $subject_handler->getEnabledHashMap();
        $this->subject   = $subjects[ $this->_project_data[ 0 ][ 'subject' ] ];

        return $this;
    }

    /**
     * Perform the computation
     *
     * @return $this
     * @throws Exception
     */
    public function fetchData(): AbstractStatus
    {
        return $this->_fetchProjectData()->loadObjects();
    }

    /**
     * @param $targetLang
     * @param $id_customer
     * @param $idJob
     *
     * @return bool
     * @throws AuthenticationError
     * @throws NotFoundException
     * @throws ValidationError
     * @throws EndQueueException
     * @throws ReQueueException
     */
    protected function isOutsourceEnabled($targetLang, $id_customer, $idJob): bool
    {
        $outsourceAvailableInfo = $this->featureSet->filter('outsourceAvailableInfo', $targetLang, $id_customer, $idJob);

        // if any plugin does not trigger the hook
        if (!is_array($outsourceAvailableInfo) or empty($outsourceAvailableInfo)) {
            $outsourceAvailableInfo = [
                    'disabled_email'         => false,
                    'custom_payable_rate'    => false,
                    'language_not_supported' => false,
            ];
        }

        return OutsourceAvailable::isOutsourceAvailable($outsourceAvailableInfo);
    }

    /**
     * @throws Exception
     */
    protected function loadObjects(): AbstractStatus
    {
        $target                 = null;
        $mt_qe_workflow_enabled = $this->project->getMetadataValue(MetadataDao::MT_QE_WORKFLOW_ENABLED) ?? false;
        $matchConstantsClass    = MatchConstantsFactory::getInstance($mt_qe_workflow_enabled);

        $this->result = $project = new AnalysisProject(
                $this->_project_data[ 0 ][ 'pname' ],
                $this->_project_data[ 0 ][ 'status_analysis' ],
                $this->_project_data[ 0 ][ 'create_date' ],
                $this->subject,
                new AnalysisProjectSummary(
                        $this->_others_in_queue,
                        $this->total_segments,
                        $this->status_project
                ),
                $matchConstantsClass
        );

        $project->setAnalyzeLink($this->getAnalyzeLink());

        foreach ($this->_resultSet as $segInfo) {
            if ($project->getSummary()->getTotalFastAnalysis() == 0 and $segInfo[ 'fast_analysis_wc' ] > 0) {
                $project->getSummary()->setTotalFastAnalysis((int)$segInfo[ 'fast_analysis_wc' ]);
            }

            /*
             *  Create & Set objects while iterating
             */
            if (!isset($job) || $job->getId() != $segInfo[ 'jid' ]) {
                $job = new AnalysisJob($segInfo[ 'jid' ], $segInfo[ 'source' ], $segInfo[ 'target' ]);
                $project->setJob($job);
            }

            if (!isset($chunk) || $chunk->getPassword() != $segInfo[ 'jpassword' ]) {
                $chunkStruct = ChunkDao::getByIdAndPassword($segInfo[ 'jid' ], $segInfo[ 'jpassword' ], 60 * 10);
                $chunk       = new AnalysisChunk($chunkStruct, $this->_project_data[ 0 ][ 'pname' ], $this->user, $matchConstantsClass);
                $job->setPayableRates(json_decode($chunkStruct->payable_rates));
                $job->setChunk($chunk);
            }

            // is outsource available?
            if ($target === null or $segInfo[ 'target' ] !== $target) {
                $job->setOutsourceAvailable(
                        $this->isOutsourceEnabled($segInfo[ 'target' ], $segInfo[ 'id_customer' ], $segInfo[ 'jid' ])
                );
                $target = $segInfo[ 'target' ];
            }

            if (!isset($file) || $file->getId() != $segInfo[ 'id_file' ] || !$chunk->hasFile($segInfo[ 'id_file' ])) {
                $originalFile = (!empty($segInfo[ 'tag_key' ]) and $segInfo[ 'tag_key' ] === 'original') ? $segInfo[ 'tag_value' ] : $segInfo[ 'filename' ];
                $id_file_part = (!empty($segInfo[ 'id_file_part' ])) ? (int)$segInfo[ 'id_file_part' ] : null;
                $metadata     = (new FileMetadataDao())->getByJobIdProjectAndIdFile((int)$this->_project_data[ 0 ][ 'pid' ], $segInfo[ 'id_file' ], 60 * 5);
                $file         = new AnalysisFile($segInfo[ 'id_file' ], $id_file_part, ZipArchiveHandler::getFileName($segInfo[ 'filename' ]), $originalFile, $matchConstantsClass, $metadata);
                $chunk->setFile($file);
            }
            // Runtime Initialization Completed

            $matchType = $matchConstantsClass::toExternalMatchTypeName($segInfo[ 'match_type' ] ?? 'default');

            // increment file totals
            $file->incrementRaw($segInfo[ 'raw_word_count' ] ?? 0);
            $file->incrementEquivalent($segInfo[ 'eq_word_count' ] ?? 0);

            // increment single file match
            $match = $file->getMatch($matchType);
            $match->incrementRaw($segInfo[ 'raw_word_count' ] ?? 0);
            $match->incrementEquivalent($segInfo[ 'eq_word_count' ] ?? 0);

            //increment chunk summary for the current match type
            $chunkMatchTotal = $chunk->getSummary()->getMatch($matchType);
            $chunkMatchTotal->incrementRaw($segInfo[ 'raw_word_count' ] ?? 0);
            $chunkMatchTotal->incrementEquivalent($segInfo[ 'eq_word_count' ] ?? 0);

            // increment job totals
            $job->incrementRaw($segInfo[ 'raw_word_count' ] ?? 0);
            $job->incrementEquivalent($segInfo[ 'eq_word_count' ] ?? 0);
            $job->incrementIndustry($segInfo[ 'standard_word_count' ] ?? 0); //backward compatibility, some old projects may have this field set as null

            // increment chunk totals
            $chunk->incrementRaw($segInfo[ 'raw_word_count' ] ?? 0);
            $chunk->incrementEquivalent($segInfo[ 'eq_word_count' ] ?? 0);
            $chunk->incrementIndustry($segInfo[ 'standard_word_count' ] ?? 0); //backward compatibility, some old projects may have this field set as null

            // increment project summary
            if ($segInfo[ 'st_status_analysis' ] == 'DONE') {
                $project->getSummary()->incrementAnalyzed();
            }
            $project->getSummary()->incrementRaw($segInfo[ 'raw_word_count' ] ?? 0);
            $project->getSummary()->incrementEquivalent($segInfo[ 'eq_word_count' ] ?? 0);
            $project->getSummary()->incrementIndustry($segInfo[ 'standard_word_count' ] ?? 0); //backward compatibility, some old projects may have this field set as null

        }

        if ($project->getSummary()->getSegmentsAnalyzed() == 0 && in_array(
                        $this->status_project,
                        [
                                ProjectStatus::STATUS_NEW,
                                ProjectStatus::STATUS_BUSY
                        ]
                )) {
            //Related to an issue in the outsourcing
            //Here, the Fast analysis was not performed, return the number of raw word counts
            //Needed because the "getProjectStatsVolumeAnalysis" query based on segment_translations always returns null
            //(there are no segment_translations)

            foreach ($this->_project_data as $_job_fallback) {
                $lang_pair = explode("|", $_job_fallback[ 'lang_pair' ]);
                $job       = new AnalysisJob($_job_fallback[ 'jid' ], $lang_pair[ 0 ], $lang_pair[ 1 ]);

                $project->setJob($job);
                $job->incrementIndustry(round($_job_fallback[ 'standard_analysis_wc' ]));
                $job->incrementEquivalent(round($_job_fallback[ 'standard_analysis_wc' ] ?? 0));  //backward compatibility, some old projects may have this field set as null
                $job->incrementRaw(round($_job_fallback[ 'standard_analysis_wc' ]));

                $chunkStruct                = new JobStruct();
                $chunkStruct->id            = $_job_fallback[ 'jid' ];
                $chunkStruct->password      = $_job_fallback[ 'jpassword' ];
                $chunkStruct->source        = $lang_pair[ 0 ];
                $chunkStruct->target        = $lang_pair[ 1 ];
                $chunkStruct->payable_rates = $_job_fallback[ 'payable_rates' ];

                $chunk = new AnalysisChunk($chunkStruct, $this->_project_data[ 0 ][ 'pname' ], $this->user, $matchConstantsClass);
                $job->setPayableRates(json_decode($chunkStruct->payable_rates));
                $job->setChunk($chunk);
            }

            $project->getSummary()->incrementRaw($this->_project_data[ 0 ][ 'standard_analysis_wc' ] ?? 0);
            $project->getSummary()->incrementIndustry($this->_project_data[ 0 ][ 'standard_analysis_wc' ] ?? 0);  //backward compatibility, some old projects may have this field set as null
            $project->getSummary()->incrementEquivalent($this->_project_data[ 0 ][ 'standard_analysis_wc' ] ?? 0);

            return $this;
        }

        return $this;
    }

    /**
     * @return string
     * @throws Exception
     */
    private function getAnalyzeLink(): string
    {
        return CanonicalRoutes::analyze([
                'project_name' => $this->project->name,
                'id_project'   => $this->project->id,
                'password'     => $this->project->password,
        ]);
    }

}