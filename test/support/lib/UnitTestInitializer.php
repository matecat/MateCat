<?php

/**
 * This class can be used to delegate the responsibility of creation of environment of an example project.
 * It offers methods to return primary keys of objects created that are in database.
 * @group regression
 * User: dinies
 * Date: 30/06/16
 * Time: 11.23
 */
class UnitTestInitializer extends IntegrationTest
{
    /**
     * @var Database
     */
    protected $con;
    /**
     * @var StdClass
     */
    protected $test_data;
    /**
     * @var Projects_ProjectStruct
     */
    protected $project;

    public function __construct( $con ) {
        /**
         * @var $con Database
         */

        $this->con = $con;

        /**
         * environment initialization
         */
        $this->test_data = new stdClass();

        $this->prepareUserAndApiKey();
        $this->project = integrationCreateTestProject(array(
                'headers' => $this->test_data->headers,
                'files' => array(
                    test_file_path('xliff/file-with-hello-world.xliff')
                ),
                'params' => array(
                    'target_lang' => 'fr-FR'
                )
            )
        );
    }

    /**
     * @param null $index
     * @return mixed
     * It returns the job in position of the index if specified, otherwise it returns the first job fetched from database
     */
    public function getJob($index = NULL){
        $query="SELECT j.* FROM jobs AS j JOIN projects AS p ON p.id = id_project WHERE p.id={$this->project->id_project}";
        $jobs_array=$this->con->query($query)->fetchAll(PDO::FETCH_ASSOC);

        if (!$index){
            return $jobs_array['0'];
        }

        return $jobs_array["{$index}"];
    }

    /**
     * @return array
     * It returns an array of segments of the project, if the job was split can be specified the index of the chunk wanted.
     */
    public function getSegments(){

        $job= $this->getJob();

        $query="SELECT s.* FROM segments AS s WHERE s.id >= {$job['job_first_segment']} AND s.id <= {$job['job_last_segment']}";
        $segments_array = $this->con->query($query)->fetchAll(PDO::FETCH_ASSOC);

        return $segments_array;
    }

    /**
     * It returns all the segment translations of the indexed segment.
     * @param int $index
     * @return array
     */
    public function getSegmentTranslations($index = NULL){

        if (!$index){
            $index = 0;
        }

        $segments = $this->getSegments();
        $query="SELECT st.* FROM segment_translations AS st WHERE st.id_segment={$segments["{$index}"]['id']}";
        $segmentTranslation=$this->con->query($query)->fetchAll(PDO::FETCH_ASSOC);
        return $segmentTranslation;
    }

    public function getProject(){
        $query="SELECT * FROM projects WHERE id={$this->project->id_project}";
        $wrapped_job = $this->con->query($query)->fetchAll(PDO::FETCH_ASSOC);
        return $wrapped_job['0'];
    }
}