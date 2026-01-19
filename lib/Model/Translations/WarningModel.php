<?php

namespace Model\Translations;


/**
 * Class WarningModel
 *
 * This class handles interactions with translation warnings, taking care to
 * merge the wanring fields on segment_translations so to make it consistent
 * with the severity of warnings being saved.
 *
 * TODO: ensure it handles also legacy warnings, setting the field to 1 when
 * when necessary.
 *
 *
 * @package Translations
 */
class WarningModel
{

    const int ERROR = 1;
    const int WARNING = 2;
    const int NOTICE = 4;
    const int INFO = 8;
    const int DEBUG = 16;

}