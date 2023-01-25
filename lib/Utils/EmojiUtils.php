<?php

class EmojiUtils
{
    const EMOJI_REGEX = '/([^-\p{L}\p{Zs}]+)/iu';

    /**
     * @param $string
     * @return int
     */
    public static function getMatches($string)
    {
        $count = 0;

        preg_match(self::EMOJI_REGEX, $string, $emojiMatches);

        return $count;
    }

    /**
     * @param $string
     * @return bool
     */
    public static function isEmoji($string)
    {
        preg_match(self::EMOJI_REGEX, $string, $matches);

        return !empty($matches);

    }
}