<?php
namespace Utils\OutsourceTo;


class OutsourceAvailable {
    /**
     * @param $outsourceAvailableInfo
     *
     * @return bool
     */
    public static function isOutsourceAvailable( $outsourceAvailableInfo ): bool {

        if ( !is_array( $outsourceAvailableInfo ) ) {
            return false;
        }

        $check = 0;

        foreach ( $outsourceAvailableInfo as $key => $info ) {
            if ( $key === 'custom_payable_rate' and $info === true ) {
                $check++;
            }

            if ( $key === 'disabled_email' and $info === true ) {
                $check++;
            }

            if ( $key === 'language_not_supported' and $info === true ) {
                $check++;
            }
        }

        return $check === 0;
    }
}