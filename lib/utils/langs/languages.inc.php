<?php

class languages {

    private  $languages = array(
        'Afrikaans' => array('2' => 'af', '3' => 'afr', 'c' => 'ZA', '3066' => 'af-ZA')
        , 'Albanian' => array('2' => 'sq', '3' => 'alb', 'c' => 'AL', '3066' => 'sq-AL')
        , 'Amharic' => array('2' => 'am', '3' => 'amh', 'c' => '..', '3066' => 'am-AM')
        , 'Antigua and Barbuda Creole English' => array('2' => 'aig', '3' => 'aig', 'c' => '..', '3066' => 'aig-AIG')
        , 'Arabic' => array('2' => 'ar', '3' => 'ara', 'c' => 'SA', '3066' => 'ar-SA')
        , 'Armenian' => array('2' => 'hy', '3' => 'arm', 'c' => 'AM', '3066' => 'hy-AM')
        , 'Azerbaijani' => array('2' => 'az', '3' => 'aze', 'c' => 'AZ', '3066' => 'az-AZ')
        , 'Bahamas Creole English' => array('2' => 'bah', '3' => 'bah', 'c' => '..', '3066' => 'bah-BAH')
        , 'Bajan' => array('2' => 'bjs', '3' => 'bjs', 'c' => '..', '3066' => 'bjs-BJS')
        , 'Balkan Gipsy' => array('2' => 'rm', '3' => 'rmn', 'c' => 'RO', '3066' => 'rm-RO')
        , 'Basque' => array('2' => 'eu', '3' => 'eus', 'c' => 'ES', '3066' => 'eu-ES')
        , 'Bemba' => array('2' => 'bem', '3' => 'bem', 'c' => '..', '3066' => 'bem-BEM')
        , 'Bengali' => array('2' => 'bn', '3' => 'ben', 'c' => 'IN', '3066' => 'bn-IN')
        , 'Bielarus' => array('2' => 'be', '3' => 'bel', 'c' => 'BY', '3066' => 'be-BY')
        , 'Bislama' => array('2' => 'bi', '3' => 'bis', 'c' => '..', '3066' => 'bi-BI')
        , 'Bosnian' => array('2' => 'bs', '3' => 'bos', 'c' => 'BA', '3066' => 'bs-BA')
        , 'Breton' => array('2' => 'br', '3' => 'bre', 'c' => 'FR', '3066' => 'br-FR')
        , 'Bulgarian' => array('2' => 'bg', '3' => 'bul', 'c' => 'BG', '3066' => 'bg-BG')
        , 'Burmese' => array('2' => 'my', '3' => 'bur', 'c' => 'MM', '3066' => 'my-MM')
        , 'Catalan' => array('2' => 'ca', '3' => 'cat', 'c' => 'ES', '3066' => 'ca-ES')
        , 'Cebuano' => array('2' => 'cb', '3' => 'ceb', 'c' => 'PH', '3066' => 'cb-PH')
        , 'Chamorro' => array('2' => 'ch', '3' => 'cha', 'c' => '..', '3066' => 'ch-CH')
        , 'Chinese' => array('2' => 'zh', '3' => 'chi', 'c' => 'CN', '3066' => 'zh-CN')
        , 'Chinese Traditional' => array('2' => 'zh', '3' => 'chi', 'c' => 'TW', '3066' => 'zh-TW')
        , 'Classical Greek' => array('2' => 'XNA', '3' => 'grc', 'c' => 'GR', '3066' => 'XN-GR')
        , 'Comorian, Ngazidja' => array('2' => 'zdj', '3' => 'zdj', 'c' => '..', '3066' => 'zdj-ZDJ')
        , 'Coptic' => array('2' => 'cop', '3' => 'cop', 'c' => 'XNA', '3066' => 'cop-XNA')
        , 'Crioulo, Upper Guinea' => array('2' => 'pov', '3' => 'pov', 'c' => '..', '3066' => 'pov-POV')
        , 'Croatian' => array('2' => 'hr', '3' => 'hrv', 'c' => 'HR', '3066' => 'hr-HR')
#,'Croatian'=>array('2'=>'hr','3'=>'scr','c'=>'HR','3066'=>'hr-HR')
        , 'Czech' => array('2' => 'cs', '3' => 'cze', 'c' => 'CZ', '3066' => 'cs-CZ')
        , 'Danish' => array('2' => 'da', '3' => 'dan', 'c' => 'DK', '3066' => 'da-DK')
        , 'Dutch' => array('2' => 'nl', '3' => 'dut', 'c' => 'NL', '3066' => 'nl-NL')
        , 'Dzongkha' => array('2' => 'dz', '3' => 'dzo', 'c' => '..', '3066' => 'dz-DZ')
        , 'English' => array('2' => 'en', '3' => 'eng', 'c' => 'GB', '3066' => 'en-GB')
        , 'English US' => array('2' => 'en', '3' => 'eng', 'c' => 'US', '3066' => 'en-US')
        , 'Esperanto' => array('2' => 'eo', '3' => 'epo', 'c' => 'XNA', '3066' => 'eo-XN')
        , 'Estonian' => array('2' => 'et', '3' => 'est', 'c' => 'EE', '3066' => 'et-EE')
        , 'Fanagalo' => array('2' => 'fn', '3' => 'fng', 'c' => 'FN', '3066' => 'fn-FNG')
        , 'Faroese' => array('2' => 'fo', '3' => 'fao', 'c' => 'FO', '3066' => 'fo-FO')
        , 'Filipino' => array('2' => 'fiXXX', '3' => 'fil', 'c' => 'PH', '3066' => 'fil-PH')
        , 'Finnish' => array('2' => 'fi', '3' => 'fin', 'c' => 'FI', '3066' => 'fi-FI')
        , 'Flemish' => array('2' => 'nl', '3' => 'XNA', 'c' => 'BE', '3066' => 'nl-BE')
        , 'French' => array('2' => 'fr', '3' => 'fre', 'c' => 'FR', '3066' => 'fr-FR')
        , 'Galician' => array('2' => 'gl', '3' => 'glg', 'c' => 'ES', '3066' => 'gl-ES')
        , 'Georgian' => array('2' => 'ka', '3' => 'geo', 'c' => 'GE', '3066' => 'ka-GE')
        , 'German' => array('2' => 'de', '3' => 'ger', 'c' => 'DE', '3066' => 'de-DE')
        , 'Greek' => array('2' => 'el', '3' => 'gre', 'c' => 'GR', '3066' => 'el-GR')
        , 'Grenadian Creole English' => array('2' => 'gcl', '3' => 'gcl', 'c' => '..', '3066' => 'gcl-GCL')
        , 'Gujarati' => array('2' => 'gu', '3' => 'guj', 'c' => 'IN', '3066' => 'gu-IN')
        , 'Guyanese Creole English' => array('2' => 'gyn', '3' => 'gyn', 'c' => '..', '3066' => 'gyn-GYN')
        , 'Haitian Creole French' => array('2' => 'ht', '3' => 'hat', 'c' => 'HT', '3066' => 'ht-HT')
        , 'Hausa' => array('2' => 'ha', '3' => 'hau', 'c' => '..', '3066' => 'ha-HA')
        , 'Hawaiian' => array('2' => 'XNA', '3' => 'haw', 'c' => 'US', '3066' => 'XN-US')
        , 'Hebrew' => array('2' => 'he', '3' => 'heb', 'c' => 'IL', '3066' => 'he-IL')
        , 'Hindi' => array('2' => 'hi', '3' => 'hin', 'c' => 'IN', '3066' => 'hi-IN')
        , 'Hungarian' => array('2' => 'hu', '3' => 'hun', 'c' => 'HU', '3066' => 'hu-HU')
        , 'Icelandic' => array('2' => 'is', '3' => 'ice', 'c' => 'IS', '3066' => 'is-IS')
        , 'Indonesian' => array('2' => 'id', '3' => 'ind', 'c' => 'ID', '3066' => 'id-ID')
        , 'Inuktitut, Greenlandic' => array('2' => 'kl', '3' => 'kal', 'c' => '..', '3066' => 'kl-KL')
        , 'Irish Gaelic' => array('2' => 'ga', '3' => 'gle', 'c' => 'IE', '3066' => 'ga-IE')
        , 'Italian' => array('2' => 'it', '3' => 'ita', 'c' => 'IT', '3066' => 'it-IT')
        , 'Jamaican Creole English' => array('2' => 'xx', '3' => 'jam', 'c' => 'JM', '3066' => 'xx-JM')
        , 'Japanese' => array('2' => 'ja', '3' => 'jpn', 'c' => 'JA', '3066' => 'ja-JA')
        , 'Javanese' => array('2' => 'jw', '3' => 'jav', 'c' => 'ID', '3066' => 'jw-ID')
        , 'Kabuverdianu' => array('2' => 'kea', '3' => 'kea', 'c' => '..', '3066' => 'kea-KEA')
        , 'Kabylian' => array('2' => 'kab', '3' => 'kab', 'c' => 'DZ', '3066' => 'kab-DZ')
        , 'Kannada' => array('2' => 'kn', '3' => 'kan', 'c' => 'IN', '3066' => 'ka-IN')
        , 'Kazakh' => array('2' => 'kk', '3' => 'kaz', 'c' => 'KZ', '3066' => 'kk-KZ')
        , 'Khmer' => array('2' => 'km', '3' => 'khm', 'c' => 'KM', '3066' => 'km-KM')
        , 'Kinyarwanda' => array('2' => 'rw', '3' => 'kin', 'c' => '..', '3066' => 'rw-RW')
        , 'Kirundi' => array('2' => 'rn', '3' => 'run', 'c' => 'RN', '3066' => 'rn-RN')
        , 'Korean' => array('2' => 'ko', '3' => 'kor', 'c' => 'KR', '3066' => 'ko-KR')
        , 'Kurdish' => array('2' => 'ku', '3' => 'kur', 'c' => 'TR', '3066' => 'ku-TR')
        , 'Kurdish Sorani' => array('2' => 'ku', '3' => 'kur', 'c' => 'TR', '3066' => 'ku-TR')
        , 'Kyrgyz' => array('2' => 'ky', '3' => 'kir', 'c' => '..', '3066' => 'ky-KY')
        , 'Lao' => array('2' => 'lo', '3' => 'lao', 'c' => '..', '3066' => 'lo-LO')
        , 'Latin' => array('2' => 'la', '3' => 'lat', 'c' => 'XNA', '3066' => 'la-XN')
        , 'Latvian' => array('2' => 'lv', '3' => 'lav', 'c' => 'LV', '3066' => 'lv-LV')
        , 'Lithuanian' => array('2' => 'lt', '3' => 'lit', 'c' => 'LT', '3066' => 'lt-LT')
        , 'Luxembourgish' => array('2' => 'lb', '3' => 'ltz', 'c' => '..', '3066' => 'lb-LB')
        , 'Macedonian' => array('2' => 'mk', '3' => 'mac', 'c' => 'MK', '3066' => 'mk-MK')
        , 'Malagasy' => array('2' => 'mg', '3' => 'mlg', 'c' => '..', '3066' => 'mg-MG')
        , 'Malay' => array('2' => 'ms', '3' => 'may', 'c' => 'MY', '3066' => 'ms-MY')
        , 'Malayalam' => array('2' => 'ml', '3' => 'mal', 'c' => 'IN', '3066' => 'ml-IN')
        , 'Maldivian' => array('2' => 'dv', '3' => 'div', 'c' => '..', '3066' => 'dv-DV')
        , 'Maltese' => array('2' => 'mt', '3' => 'mlt', 'c' => 'MT', '3066' => 'mt-MT')
        , 'Manx Gaelic' => array('2' => 'gv', '3' => 'glv', 'c' => 'IM', '3066' => 'gv-IM')
        , 'Maori' => array('2' => 'mi', '3' => 'mao', 'c' => 'NZ', '3066' => 'mi-NZ')
        , 'Marati' => array('2' => 'mr', '3' => 'mar', 'c' => 'IN', '3066' => 'mr-IN')
        , 'Marshallese' => array('2' => 'mh', '3' => 'mah', 'c' => '..', '3066' => 'mh-MH')
        , 'Mende' => array('2' => 'men', '3' => 'men', 'c' => '..', '3066' => 'men-MEN')
        , 'Mongolian' => array('2' => 'mn', '3' => 'mon', 'c' => 'MN', '3066' => 'mn-MN')
        , 'Morisyen' => array('2' => 'mfe', '3' => 'mfe', 'c' => '..', '3066' => 'mfe-MFE')
        , 'Nepali' => array('2' => 'ne', '3' => 'nep', 'c' => 'NP', '3066' => 'ne-NP')
        , 'Niuean' => array('2' => 'niu', '3' => 'niu', 'c' => '..', '3066' => 'niu-NIU')
        , 'Norwegian' => array('2' => 'no', '3' => 'nor', 'c' => 'NO', '3066' => 'no-NO')
        , 'Nyanja' => array('2' => 'ny', '3' => 'nya', 'c' => '..', '3066' => 'ny-NY')
        , 'Pakistani' => array('2' => 'ur', '3' => 'urd', 'c' => 'PK', '3066' => 'ur-PK')
        , 'Palauan' => array('2' => 'pau', '3' => 'pau', 'c' => '..', '3066' => 'pau-PAU')
        , 'Panjabi' => array('2' => 'pa', '3' => 'pan', 'c' => 'IN', '3066' => 'pa-IN')
        , 'Papiamentu' => array('2' => 'x1', '3' => 'pap', 'c' => 'PAP', '3066' => 'pap-PAP')
        , 'Pashto' => array('2' => 'ps', '3' => 'pst', 'c' => 'PK', '3066' => 'ps-PK')
        , 'Persian' => array('2' => 'fa', '3' => 'per', 'c' => 'IR', '3066' => 'fa-IR')
        , 'Pijin' => array('2' => 'pis', '3' => 'pis', 'c' => '..', '3066' => 'pis-PIS')
        , 'Polish' => array('2' => 'pl', '3' => 'pol', 'c' => 'PL', '3066' => 'pl-PL')
        , 'Portuguese' => array('2' => 'pt', '3' => 'por', 'c' => 'PT', '3066' => 'pt-PT')
        , 'Portuguese Brazil' => array('2' => 'pt', '3' => 'por', 'c' => 'BR', '3066' => 'pt-BR')
        , 'Potawatomi' => array('2' => 'pot', '3' => 'pot', 'c' => 'US', '3066' => 'pot-US')
        , 'Quebecois' => array('2' => 'fr', '3' => 'fre', 'c' => 'CA', '3066' => 'fr-CA')
        , 'Quechua' => array('2' => 'qu', '3' => 'que', 'c' => 'XN', '3066' => 'qu-XN')   // non sono sicuro!
        , 'Romanian' => array('2' => 'ro', '3' => 'rum', 'c' => 'RO', '3066' => 'ro-RO')
        , 'Russian' => array('2' => 'ru', '3' => 'rus', 'c' => 'RU', '3066' => 'ru-RU')
        , 'Saint Lucian Creole French' => array('2' => 'acf', '3' => 'acf', 'c' => '..', '3066' => 'acf-ACF')
        , 'Samoan' => array('2' => 'sm', '3' => 'smo', 'c' => '..', '3066' => 'sm-SM')
        , 'Sango' => array('2' => 'sg', '3' => 'sag', 'c' => '..', '3066' => 'sg-SG')
        , 'Scots Gaelic' => array('2' => 'gd', '3' => 'gla', 'c' => 'GB', '3066' => 'gd-GB')
#,'Serbian'=>array('2'=>'sr','3'=>'scc','c'=>'CS','3066'=>'sr-CS')
        , 'Serbian' => array('2' => 'sr', '3' => 'srp', 'c' => 'RS', '3066' => 'sr-RS')
        , 'Seselwa Creole French' => array('2' => 'crs', '3' => 'crs', 'c' => '..', '3066' => 'crs-CRS')
        , 'Shona' => array('2' => 'sn', '3' => 'sna', 'c' => '..', '3066' => 'sn-SN')
        , 'Sinhala' => array('2' => 'si', '3' => 'sin', 'c' => 'LK', '3066' => 'si-LK')
        , 'Slovak' => array('2' => 'sk', '3' => 'slo', 'c' => 'SK', '3066' => 'sk-SK')
        , 'Slovenian' => array('2' => 'sl', '3' => 'slv', 'c' => 'SI', '3066' => 'sl-SI')
        , 'Somali' => array('2' => 'so', '3' => 'som', 'c' => 'SO', '3066' => 'so-SO')
        , 'Sotho, Southern' => array('2' => 'st', '3' => 'sot', 'c' => '..', '3066' => 'st-ST')
        , 'Spanish' => array('2' => 'es', '3' => 'spa', 'c' => 'ES', '3066' => 'es-ES')
        , 'Spanish Latin America' => array('2' => 'es', '3' => 'spa', 'c' => 'MX', '3066' => 'es-MX')
        , 'Sranan Tongo' => array('2' => 'srn', '3' => 'srn', 'c' => '..', '3066' => 'srn-SRN')
        , 'Swahili' => array('2' => 'sw', '3' => 'swa', 'c' => 'SZ', '3066' => 'sw-SZ')
        , 'Swedish' => array('2' => 'sv', '3' => 'swe', 'c' => 'SE', '3066' => 'sv-SE')
        , 'Swiss German' => array('2' => 'de', '3' => 'ger', 'c' => 'CH', '3066' => 'de-CH')
        , 'Syriac (Aramaic)' => array('2' => 'syc', '3' => 'syc', 'c' => 'TR', '3066' => 'syc-TR')
        , 'Tagalog' => array('2' => 'tl', '3' => 'tgl', 'c' => 'PH', '3066' => 'tl-PH')
#,'Tagalog'=>array('2'=>'tl','3'=>'tlg','c'=>'PH','3066'=>'tl-PH')
        , 'Tajik' => array('2' => 'tg', '3' => 'tgk', 'c' => 'TJ', '3066' => 'tg-TJ')
        , 'Tamashek (Tuareg)' => array('2' => 'tmh', '3' => 'tmh', 'c' => 'DZ', '3066' => 'tmh-DZ')
        , 'Tamil' => array('2' => 'ta', '3' => 'tam', 'c' => 'LK', '3066' => 'ta-LK')
        , 'Telugu' => array('2' => 'te', '3' => 'tel', 'c' => 'IN', '3066' => 'te-IN')
        , 'Tetum' => array('2' => 'tet', '3' => 'tet', 'c' => '..', '3066' => 'tet-TET')
        , 'Thai' => array('2' => 'th', '3' => 'tha', 'c' => 'TH', '3066' => 'th-TH')
        , 'Tibetan' => array('2' => 'bo', '3' => 'tib', 'c' => 'CN', '3066' => 'bo-CN')
        , 'Tigrinya' => array('2' => 'ti', '3' => 'tir', 'c' => 'TI', '3066' => 'ti-TI')
        , 'Tokelauan' => array('2' => 'tkl', '3' => 'tkl', 'c' => '..', '3066' => 'tkl-TKL')
        , 'Tok Pisin' => array('2' => 'tpi', '3' => 'tpi', 'c' => '..', '3066' => 'tpi-TPI')
        , 'Tongan' => array('2' => 'to', '3' => 'ton', 'c' => '..', '3066' => 'to-TO')
        , 'Tswana' => array('2' => 'tn', '3' => 'tsn', 'c' => '..', '3066' => 'tn-TN')
        , 'Turkish' => array('2' => 'tr', '3' => 'tur', 'c' => 'TR', '3066' => 'tr-TR')
        , 'Turkmen' => array('2' => 'tk', '3' => 'tuk', 'c' => '..', '3066' => 'tk-TK')
        , 'Tuvaluan' => array('2' => 'tvl', '3' => 'tvl', 'c' => '..', '3066' => 'tvl-TVL')
        , 'Ukrainian' => array('2' => 'uk', '3' => 'ukr', 'c' => 'UA', '3066' => 'uk-UA')
        , 'Uma' => array('2' => 'ppk', '3' => 'ppk', 'c' => 'ID', '3066' => 'ppk-ID')
        , 'Uzbek' => array('2' => 'uz', '3' => 'uzb', 'c' => '..', '3066' => 'uz-UZ')
        , 'Vietnamese' => array('2' => 'vi', '3' => 'vie', 'c' => 'VN', '3066' => 'vi-VN')
        , 'Vincentian Creole English' => array('2' => 'svc', '3' => 'svc', 'c' => '..', '3066' => 'svc-SVC')
        , 'Virgin Islands Creole English' => array('2' => 'vic', '3' => 'vic', 'c' => '..', '3066' => 'vic-VIC')
        , 'Wallisian' => array('2' => 'wls', '3' => 'wls', 'c' => '..', '3066' => 'wls-WLS')
        , 'Welsh' => array('2' => 'cy', '3' => 'cym', 'c' => 'GB', '3066' => 'cy-GB')
        , 'Wolof' => array('2' => 'wo', '3' => 'wol', 'c' => 'SN', '3066' => 'wo-SN')
        , 'Xhosa' => array('2' => 'xh', '3' => 'xho', 'c' => 'ZA', '3066' => 'xh-ZA')
        , 'Yiddish' => array('2' => 'yi', '3' => 'yid', 'c' => 'YDD', '3066' => 'yi-YD')
        , 'Zulu' => array('2' => 'zu', '3' => 'zul', 'c' => 'ZU', '3066' => 'zu-ZU')
    );
    private static $instance = null;
    private $lang_trad;
    private $lang;
    

    private function __construct($lang) {
        $this->lang=$lang;
        if (empty($this->lang)){
            $this->lang='en';
        }
        
        $this->setLangResources();
    }
    
    private function setLangResources(){
        
        if (!file_exists(INIT::$UTILS_ROOT."/langs/langs_$this->lang.inc.php")){
            throw new Exception("language files not set <langs_$this->lang.inc.php>");
        }
        include ("langs_$this->lang.inc.php");
        $this->lang_trad=$languages;
    }

    public static function getInstance($lang='en') {
        if (self::$instance == null) {
            $c = __CLASS__;
            self::$instance = new $c($lang);
        }
        
        return self::$instance;
    }
    
    
    
    
    public  function iso2Language($code){        
        $code=  str_replace("_", "-", $code);
        //echo strlen($code);exit;
        $key="";
        if (strlen($code)==2){
            $key="2";
        }
        
        if (strlen($code)==3){
            $key="3";
        }
        
        if (strlen($code)>3){
            $key="3066";
        }
        
        
        foreach ($this->languages as $k =>$v){
        //    echo $v["$key"]."\n";
            if ($v["$key"]==$code){
                if (array_key_exists($k, $this->lang_trad)){
                    return $this->lang_trad[$k];
                }
                return $k;
            }            
        } 
        return "$code";
    }
    
    
    public function checkKeys(){
        //print_r ($this->languages);
        echo "--- check languages --> lang_trad\n";
        foreach ($this->languages as $k=> $v){           
            if(array_key_exists($k, $this->lang_trad)){
                //echo "$k exists as key in lang_trad\n ";
            }else{
                echo "FAIL $k not exists as key in lang_trad_$this->lang\n ";
            }
        }
        
        
        echo "\n\n--- check   lang_trad --> languages\n";
        foreach ($this->lang_trad as $k=> $v){           
            if(array_key_exists($k, $this->languages)){
                //echo "$k exists as key in languages\n ";
            }else{
                echo "FAIL2 $k not exists as key in languages\n ";
            }
        }
        
    }

}

//
//$a=languages::getInstance("en");
//$a->checkKeys();
//$b= $a->iso2Language("af-ZA");
//var_dump( $b);

?>
