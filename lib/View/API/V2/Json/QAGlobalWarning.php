<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 13/03/18
 * Time: 14.46
 *
 */

namespace View\API\V2\Json;


use Utils\LQA\QA;

class QAGlobalWarning extends QAWarning
{

    /** @var array<int, mixed> */
    protected array $tagIssues;

    /** @var array<int, mixed> */
    protected array $translationMismatches;

    /**
     * QAGlobalWarning constructor.
     *
     * from query: getWarning(id_job, password)
     *
     * @param array<int, mixed> $tagIssues
     * @param array<int, mixed> $translationMismatches
     */
    public function __construct(array $tagIssues, array $translationMismatches)
    {
        $this->tagIssues = $tagIssues;
        $this->translationMismatches = $translationMismatches;
    }

    /**
     * @return array<string, mixed>
     */
    public function render(): array
    {
        $this->structure = [
            'ERROR' => [
                'Categories' => []
            ],
            'WARNING' => [
                'Categories' => []
            ],
            'INFO' => [
                'Categories' => []
            ]
        ];


        foreach ($this->tagIssues as $_item) {
            $exceptionList = QA::JSONtoExceptionList($_item['serialized_errors_list']);

            if (count($exceptionList[QA::ERROR]) > 0) {
                foreach ($exceptionList[QA::ERROR] as $exception_error) {
                    $this->pushErrorSegment(QA::ERROR, $exception_error->outcome, (string)$_item['id_segment']);
                }
            }

            if (count($exceptionList[QA::WARNING]) > 0) {
                foreach ($exceptionList[QA::WARNING] as $exception_error) {
                    $this->pushErrorSegment(QA::WARNING, $exception_error->outcome, (string)$_item['id_segment']);
                }
            }

            if (count($exceptionList[QA::INFO]) > 0) {
                foreach ($exceptionList[QA::INFO] as $exception_error) {
                    $this->pushErrorSegment(QA::INFO, $exception_error->outcome, (string)$_item['id_segment']);
                }
            }
        }

        foreach ($this->translationMismatches as $row) {
            if (!empty($row['first_of_my_job'])) {
                $this->structure[QA::WARNING]['Categories']['MISMATCH'][] = $row['first_of_my_job'];
            }
        }

        $out['details'] = $this->structure;

        return $out;
    }


}