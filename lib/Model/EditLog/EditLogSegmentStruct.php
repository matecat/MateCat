<?php

namespace Model\EditLog;

use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\IDaoStruct;
use MyMemory;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 05/10/15
 * Time: 11.33
 */
class EditLogSegmentStruct extends AbstractDaoObjectStruct implements IDaoStruct {

    const EDIT_TIME_SLOW_CUT = 30;
    const EDIT_TIME_FAST_CUT = 0.25;

    /**
     * @var int
     */
    public int $id;

    /**
     * @var ?string
     */
    public ?string $suggestion = null;

    /**
     * @var ?string
     */
    public ?string $translation = null;

    /**
     * @var int
     */
    public int $raw_word_count;

    /**
     * @var int
     */
    public int $time_to_edit;

    /**
     * @var ?string
     */
    public ?string $target_language_code = null;

    /**
     * @return float
     */
    public function getSecsPerWord(): float {
        $val = @round( ( $this->time_to_edit / 1000 ) / $this->raw_word_count, 1 );

        return ( $val != INF ? $val : 0 );
    }

    /**
     * Returns true if the number of seconds per word
     * @return bool
     */
    public function isValidForEditLog(): bool {
        $secsPerWord = $this->getSecsPerWord();

        return ( $secsPerWord > self::EDIT_TIME_FAST_CUT ) &&
                ( $secsPerWord < self::EDIT_TIME_SLOW_CUT );
    }

    /**
     * @return float
     */
    public function getPEE(): float {

        $post_editing_effort = round(
                ( 1 - MyMemory::TMS_MATCH(
                                self::cleanSegmentForPee( $this->suggestion ),
                                self::cleanSegmentForPee( $this->translation ),
                                $this->target_language_code
                        )
                ) * 100
        );

        if ( $post_editing_effort < 0 ) {
            $post_editing_effort = 0;
        } elseif ( $post_editing_effort > 100 ) {
            $post_editing_effort = 100;
        }

        return $post_editing_effort;

    }

    private static function cleanSegmentForPee( $segment ): string {
        return htmlspecialchars_decode( $segment, ENT_QUOTES );
    }
}
