<?php

class EmojiUtils
{
    /**
     * @param $string
     * @return bool
     */
    public static function isEmoji($string)
    {
        $regex = '/([^-\p{L}\x00-\x7F]+)/iu';

        preg_match($regex, $string, $matches);

        return !empty($matches);
    }
}