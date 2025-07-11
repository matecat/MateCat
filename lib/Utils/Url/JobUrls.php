<?php

namespace Utils\Url;

use INIT;
use Plugins\Features\ReviewExtended\ReviewUtils;

class JobUrls {

    // STATUS LABELS
    const LABEL_T  = 't';
    const LABEL_R1 = 'r1';
    const LABEL_R2 = 'r2';

    /**
     * @var int
     */
    private int $jid;

    /**
     * @var string
     */
    private string $projectName;

    /**
     * @var string
     */
    private string $source;

    /**
     * @var string
     */
    private string $target;

    /**
     * @var ?string
     */
    private ?string $segmentId;

    /**
     * @var array
     */
    private array $passwords;

    /**
     * @var array
     */
    private array $urls = [];

    /**
     * JobUrls constructor.
     *
     * @param int         $jid
     * @param string      $projectName
     * @param string      $source
     * @param string      $target
     * @param array       $passwords
     * @param string|null $httpHost
     * @param string|null $segmentId
     */
    public function __construct(
            int $jid,
            string $projectName,
            string $source,
            string $target,
            array $passwords = [],
            ?string $httpHost = null,
            ?string $segmentId = null
    ) {
        $this->jid         = $jid;
        $this->projectName = $projectName;
        $this->source      = $source;
        $this->target      = $target;
        $this->passwords   = $passwords;
        $this->segmentId   = $segmentId;
        $this->setUrls( $httpHost );
    }

    /**
     * @param string $label
     *
     * @return bool
     */
    private function isLabelAllowed( string $label ): bool {
        $allowed = [ self::LABEL_T, self::LABEL_R1, self::LABEL_R2 ];

        return in_array( $label, $allowed );
    }

    /**
     * set $this->urls from the provided password
     *
     * @param string|null $httpHost
     */
    private function setUrls( ?string $httpHost = null ) {

        // loop passwords array
        foreach ( $this->passwords as $label => $password ) {
            if ( $password and $this->isLabelAllowed( $label ) ) {

                switch ( $label ) {
                    default:
                    case self::LABEL_T:
                        $revisionNumber = null;
                        break;
                    case self::LABEL_R1:
                        $revisionNumber = 1;
                        break;
                    case self::LABEL_R2:
                        $revisionNumber = 2;
                        break;
                }

                $sourcePage = ReviewUtils::revisionNumberToSourcePage( $revisionNumber );

                $url = $this->httpHost( $httpHost );
                $url .= DIRECTORY_SEPARATOR;
                $url .= $this->getJobType( $sourcePage );
                $url .= DIRECTORY_SEPARATOR;
                $url .= $this->projectName;
                $url .= DIRECTORY_SEPARATOR;
                $url .= $this->source . '-' . $this->target;
                $url .= DIRECTORY_SEPARATOR;
                $url .= $this->jid . '-' . $password;

                if ( $this->segmentId ) {
                    $url .= '#' . $this->segmentId;
                }

                $this->urls[ $label ] = $url;
            }
        }
    }

    /**
     * @param string|null $httpHost
     *
     * @return string
     */
    private function httpHost( ?string $httpHost = null ): string {
        $host = INIT::$HTTPHOST;

        if ( !empty( $httpHost ) ) {
            $host = $httpHost;
        }

        return $host;
    }

    /**
     * Get the job type:
     *
     * - translate
     * - revise
     * - revise(n)
     *
     * @param int $sourcePage
     *
     * @return string|null
     */
    private function getJobType( int $sourcePage ): ?string {
        if ( $sourcePage == 1 ) {
            return 'translate';
        }

        if ( $sourcePage == 2 ) {
            return 'revise';
        }

        if ( $sourcePage > 2 ) {
            return 'revise' . ( $sourcePage - 1 );
        }

        return null;
    }

    /**
     * @return ?string
     */
    public function getTranslationUrl(): ?string {
        return $this->urls[ self::LABEL_T ] ?? null;
    }

    /**
     * @return ?string
     */
    public function getReviseUrl(): ?string {
        return $this->urls[ self::LABEL_R1 ] ?? null;
    }

    /**
     * @return ?string
     */
    public function getRevise2Url(): ?string {
        return $this->urls[ self::LABEL_R2 ] ?? null;
    }

    /**
     * @return array
     */
    public function getUrls(): array {
        return $this->urls;
    }

    /**
     * Get the URL from revision number (null|1|2)
     *
     * @param int|null $revisionNumber
     *
     * @return string|null
     */
    public function getUrlByRevisionNumber( ?int $revisionNumber = null ): ?string {

        if ( !$revisionNumber ) {
            return $this->getTranslationUrl();
        }

        if ( $revisionNumber === 1 ) {
            return $this->getReviseUrl();
        }

        if ( $revisionNumber === 2 ) {
            return $this->getRevise2Url();
        }

        return null;
    }

    /**
     * @return bool
     */
    public function hasReview(): bool {
        return isset( $this->urls[ self::LABEL_R1 ] ) and !isset( $this->urls[ self::LABEL_R2 ] );
    }

    /**
     * @return bool
     */
    public function hasSecondPassReview(): bool {
        return isset( $this->urls[ self::LABEL_R2 ] );
    }
}