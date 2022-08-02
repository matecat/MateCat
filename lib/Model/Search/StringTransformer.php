<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 16/04/18
 * Time: 14.09
 *
 */

namespace Search;

class StringTransformer
{
    /**
     * This function transform a string from DB (layer 0) to the closest
     * possible version of the string as presented in the UI
     * in order to obtain the best search matches
     *
     * Example:
     *
     * <bx id="23"/><g id="1">Ciao</g> questa è una stringa --------------> 23 1 Ciao questa è una stringa
     *
     * @param string $string
     * @param null   $originalMap
     *
     * @return mixed
     */
    public static function transform($string, $originalMap = null){

        $string = self::stripTags($string);

        if($originalMap){
            $string = self::replaceOriginalMap($string, $originalMap);
        }

        //$string = self::trimExtraSpaces($string);

        return $string;
    }

    /**
     * This function strips selected tags from $string and substitutes with the corresponding id attribute
     *
     * @param $string
     *
     * @return string
     */
    private static function stripTags($string)
    {
        $tags = [
            'g',
            'bx',
            'ex',
            'x',
        ];

        $temporaryPlaceholder = '###########_____TEMPORARY_PLACEHOLDER_____###########';

        foreach ($tags as $tag){
            $regex = '/<'.$tag.' id="(.*)"(.*)>/sU';
            $regex2 = '/<\/'.$tag.'>/sU';

            $string = preg_replace ($regex, '$1'.$temporaryPlaceholder, $string);
            $string = preg_replace ($regex2, $temporaryPlaceholder, $string);
        }

        // with the placeholder trick we avoid the double space insert
        return str_replace([' '.$temporaryPlaceholder, $temporaryPlaceholder.' ', $temporaryPlaceholder],' ', $string);
    }

    /**
     * ONLY FOR XLIFF 2.0
     *
     * This function replaces <ph> and <pc> tags with the corresponding values stored in $originalMap
     *
     * @param string $string
     * @param string $originalMap
     *
     * @return mixed
     */
    private static function replaceOriginalMap($string, $originalMap)
    {
        $originalMap = json_decode($originalMap);

        foreach ($originalMap as $key => $value){
            $regex = '/<ph id="'.$key.'" dataRef="'.$key.'">/sU';
            $regex2 = '/<pc id="'.$key.'" (dataRefStart="'.$key.'"|dataRefEnd="'.$key.'")(.*)>/sU';

            $string = preg_replace ($regex, $value. ' ', $string);
            $string = preg_replace ($regex2, $value. ' ', $string);

        }

        $string = str_replace('</pc>', ' ', $string);

        return $string;
    }

    /**
     * @param $string
     *
     * @return string
     */
    private static function trimExtraSpaces($string)
    {
        return trim(preg_replace('/ {2,}/', ' ', $string));
    }
}