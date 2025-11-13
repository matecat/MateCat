<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/09/16
 * Time: 15:57
 */

namespace View\API\V2\Json;

use Exception;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Plugins\Features\ReviewExtended\ReviewUtils;
use ReflectionException;
use Utils\Url\CanonicalRoutes;

class ProjectUrls
{

    protected array $data;
    protected array $jobs   = [];
    protected array $files  = [];
    protected array $chunks = [];

    /*
     * @var array
     */
    private array $formatted = ['files' => [], 'jobs' => []];

    /**
     * ProjectUrls constructor.
     *
     * @param $data ShapelessConcreteStruct[]
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @param bool $keyAssoc
     *
     * @return array
     * @throws Exception
     */
    public function render(bool $keyAssoc = false): array
    {
        foreach ($this->data as $record) {
            if (!array_key_exists($record[ 'id_file' ], $this->files)) {
                $this->files[ $record[ 'id_file' ] ] = [
                        'id'                       => $record[ 'id_file' ],
                        'name'                     => $record[ 'filename' ],
                        'original_download_url'    => $this->downloadOriginalUrl($record),
                        'translation_download_url' => $this->downloadFileTranslationUrl($record),
                        'xliff_download_url'       => $this->downloadXliffUrl($record)
                ];
            }

            if (!array_key_exists($record[ 'jid' ], $this->jobs)) {
                $this->jobs[ $record[ 'jid' ] ] = [
                        'id'                       => $record[ 'jid' ],
                        'target_lang'              => $record[ 'target' ],
                        'original_download_url'    => $this->downloadOriginalUrl($record),
                        'translation_download_url' => $this->downloadTranslationUrl($record),
                        'xliff_download_url'       => $this->downloadXliffUrl($record),
                        'chunks'                   => []
                ];
            }

            $this->generateChunkUrls($record);
        }

        //maintain index association for external array access
        if (!$keyAssoc) {
            $this->formatted[ 'jobs' ] = array_values($this->jobs);
            foreach ($this->formatted[ 'jobs' ] as &$chunks) {
                $chunks[ 'chunks' ] = array_values($chunks[ 'chunks' ]);
            }
            $this->formatted[ 'files' ] = array_values($this->files);
        } else {
            $this->formatted[ 'jobs' ]  = $this->jobs;
            $this->formatted[ 'files' ] = $this->files;
        }

        // start over for jobs

        return $this->formatted;
    }


    /**
     * @throws ReflectionException
     * @throws Exception
     */
    protected function generateChunkUrls(ShapelessConcreteStruct $record): void
    {
        if (!array_key_exists($record[ 'jpassword' ], $this->chunks)) {
            $this->chunks[ $record[ 'jpassword' ] ] = 1;

            $this->jobs[ $record[ 'jid' ] ][ 'chunks' ][ $record[ 'jpassword' ] ] = [
                    'password'      => $record[ 'jpassword' ],
                    'translate_url' => $this->translateUrl($record),
            ];

            $reviews = (new ChunkReviewDao())->findChunkReviews(new JobStruct(['id' => $record[ 'jid' ], 'password' => $record[ 'jpassword' ]]));

            foreach ($reviews as $review) {
                $revisionNumber = ReviewUtils::sourcePageToRevisionNumber($review->source_page);
                $reviseUrl      = CanonicalRoutes::revise(
                        $record[ 'name' ],
                        $record[ 'jid' ],
                        $review->review_password,
                        $record[ 'source' ],
                        $record[ 'target' ],
                        ['revision_number' => $revisionNumber]
                );

                $this->jobs[ $record[ 'jid' ] ][ 'chunks' ][ $record[ 'jpassword' ] ] [ 'revise_urls' ] [] = [
                        'revision_number' => $revisionNumber,
                        'url'             => $reviseUrl
                ];
            }
        }
    }


    public function getData(): array
    {
        return $this->data;
    }


    /**
     * @throws Exception
     */
    protected function downloadOriginalUrl(ShapelessConcreteStruct $record): string
    {
        return CanonicalRoutes::downloadOriginal(
                $record[ 'jid' ],
                $record[ 'jpassword' ],
                $record[ 'id_file' ]
        );
    }

    /**
     * @throws Exception
     */
    protected function downloadXliffUrl(ShapelessConcreteStruct $record): string
    {
        return CanonicalRoutes::downloadXliff(
                $record[ 'jid' ],
                $record[ 'jpassword' ]
        );
    }

    /**
     * @throws Exception
     */
    protected function downloadFileTranslationUrl(ShapelessConcreteStruct $record): string
    {
        return CanonicalRoutes::downloadTranslation(
                $record[ 'jid' ],
                $record[ 'jpassword' ]
        );
    }

    /**
     * @throws Exception
     */
    protected function downloadTranslationUrl(ShapelessConcreteStruct $record): string
    {
        return CanonicalRoutes::downloadTranslation(
                $record[ 'jid' ],
                $record[ 'jpassword' ]
        );
    }

    /**
     * @throws Exception
     */
    protected function translateUrl(ShapelessConcreteStruct $record): string
    {
        return CanonicalRoutes::translate(
                $record[ 'name' ],
                $record[ 'jid' ],
                $record[ 'jpassword' ],
                $record[ 'source' ],
                $record[ 'target' ]
        );
    }

    /**
     * @throws Exception
     */
    protected function reviseUrl(ShapelessConcreteStruct $record): string
    {
        return CanonicalRoutes::revise(
                $record[ 'name' ],
                $record[ 'jid' ],
                $record[ 'jpassword' ],
                $record[ 'source' ],
                $record[ 'target' ]
        );
    }
}