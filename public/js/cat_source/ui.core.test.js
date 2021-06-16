import {screen} from '@testing-library/react'
import EventSource from 'eventsourcemock'

Object.defineProperty(window, 'EventSource', {
  value: EventSource,
})

test('renders properly', async () => {
  global.config = {
    source_rfc: 'en-US',
    target_rfc: 'it-IT',
    tag_projection_languages: {
      'en-de': 'English - German',
      'en-es': 'English - Spanish',
      'en-fr': 'English - French',
      'en-it': 'English - Italian',
      'en-pt': 'English - Portuguese',
      'en-ru': 'English - Russian',
      'en-cs': 'English - Czech',
      'en-nl': 'English - Dutch',
      'en-fi': 'English - Finnish',
      'en-pl': 'English - Polish',
      'en-da': 'English - Danish',
      'en-sv': 'English - Swedish',
      'en-el': 'English - Greek',
      'en-hu': 'English - Hungarian',
      'en-lt': 'English - Lithuanian',
      'en-ja': 'English - Japanese',
      'en-et': 'English - Estonian',
      'en-sk': 'English - Slovak',
      'de-it': 'German - Italian',
      'de-fr': 'German - French',
      'fr-it': 'French - Italian',
      'fr-nl': 'French - Dutch',
      'it-es': 'Italian - Spanish',
      'nl-fi': 'Dutch - Finnish',
      'da-sv': 'Danish - Swedish',
      'nl-pt': 'Dutch - Portuguese',
      'zh-en': 'Chinese - English',
      'cs-de': 'Czech - German',
    },
    searchable_statuses: [
      {
        value: 'NEW',
        label: 'NEW',
      },
      {
        value: 'DRAFT',
        label: 'DRAFT',
      },
      {
        value: 'TRANSLATED',
        label: 'TRANSLATED',
      },
      {
        value: 'APPROVED',
        label: 'APPROVED',
      },
      {
        value: 'REJECTED',
        label: 'REJECTED',
      },
    ],
    languages_array: [
      {
        code: 'af-ZA',
        name: 'Afrikaans',
        direction: 'ltr',
      },
      {
        code: 'sq-AL',
        name: 'Albanian',
        direction: 'ltr',
      },
      {
        code: 'am-ET',
        name: 'Amharic',
        direction: 'ltr',
      },
      {
        code: 'aig-AG',
        name: 'Antigua and Barbuda Creole English',
        direction: 'ltr',
      },
      {
        code: 'ar-SA',
        name: 'Arabic',
        direction: 'rtl',
      },
      {
        code: 'ar-EG',
        name: 'Arabic Egyptian',
        direction: 'rtl',
      },
      {
        code: 'an-ES',
        name: 'Aragonese',
        direction: 'ltr',
      },
      {
        code: 'hy-AM',
        name: 'Armenian',
        direction: 'ltr',
      },
      {
        code: 'asm-IN',
        name: 'Assamese',
        direction: 'ltr',
      },
      {
        code: 'ast-ES',
        name: 'Asturian',
        direction: 'ltr',
      },
      {
        code: 'de-AT',
        name: 'Austrian German',
        direction: 'ltr',
      },
      {
        code: 'az-AZ',
        name: 'Azerbaijani',
        direction: 'ltr',
      },
      {
        code: 'bah-BS',
        name: 'Bahamas Creole English',
        direction: 'ltr',
      },
      {
        code: 'bjs-BB',
        name: 'Bajan',
        direction: 'ltr',
      },
      {
        code: 'ba-RU',
        name: 'Bashkir',
        direction: 'ltr',
      },
      {
        code: 'eu-ES',
        name: 'Basque',
        direction: 'ltr',
      },
      {
        code: 'bem-ZM',
        name: 'Bemba',
        direction: 'ltr',
      },
      {
        code: 'bn-IN',
        name: 'Bengali',
        direction: 'ltr',
      },
      {
        code: 'be-BY',
        name: 'Belarusian',
        direction: 'ltr',
      },
      {
        code: 'fr-BE',
        name: 'Belgian French',
        direction: 'ltr',
      },
      {
        code: 'bh-IN',
        name: 'Bihari',
        direction: 'ltr',
      },
      {
        code: 'bi-VU',
        name: 'Bislama',
        direction: 'ltr',
      },
      {
        code: 'gax-KE',
        name: 'Borana',
        direction: 'ltr',
      },
      {
        code: 'bs-BA',
        name: 'Bosnian',
        direction: 'ltr',
      },
      {
        code: 'bs-Cyrl-BA',
        name: 'Bosnian (Cyrillic)',
        direction: 'ltr',
      },
      {
        code: 'br-FR',
        name: 'Breton',
        direction: 'ltr',
      },
      {
        code: 'bg-BG',
        name: 'Bulgarian',
        direction: 'ltr',
      },
      {
        code: 'my-MM',
        name: 'Burmese',
        direction: 'ltr',
      },
      {
        code: 'ca-ES',
        name: 'Catalan',
        direction: 'ltr',
      },
      {
        code: 'cav-ES',
        name: 'Catalan Valencian',
        direction: 'ltr',
      },
      {
        code: 'cb-PH',
        name: 'Cebuano',
        direction: 'ltr',
      },
      {
        code: 'shu-TD',
        name: 'Chadian Arabic',
        direction: 'rtl',
      },
      {
        code: 'cha-GU',
        name: 'Chamorro',
        direction: 'ltr',
      },
      {
        code: 'chr-US',
        name: 'Cherokee',
        direction: 'ltr',
      },
      {
        code: 'zh-CN',
        name: 'Chinese Simplified',
        direction: 'ltr',
      },
      {
        code: 'zh-TW',
        name: 'Chinese Traditional',
        direction: 'ltr',
      },
      {
        code: 'zh-HK',
        name: 'Chinese Trad. (Hong Kong)',
        direction: 'ltr',
      },
      {
        code: 'zh-MO',
        name: 'Chinese Traditional Macau',
        direction: 'ltr',
      },
      {
        code: 'ctg-BD',
        name: 'Chittagonian',
        direction: 'ltr',
      },
      {
        code: 'grc-GR',
        name: 'Classical Greek',
        direction: 'ltr',
      },
      {
        code: 'zdj-KM',
        name: 'Comorian Ngazidja',
        direction: 'ltr',
      },
      {
        code: 'hr-HR',
        name: 'Croatian',
        direction: 'ltr',
      },
      {
        code: 'cs-CZ',
        name: 'Czech',
        direction: 'ltr',
      },
      {
        code: 'da-DK',
        name: 'Danish',
        direction: 'ltr',
      },
      {
        code: 'fa-AF',
        name: 'Dari',
        direction: 'rtl',
      },
      {
        code: 'nl-NL',
        name: 'Dutch',
        direction: 'ltr',
      },
      {
        code: 'dzo-BT',
        name: 'Dzongkha',
        direction: 'ltr',
      },
      {
        code: 'en-GB',
        name: 'English',
        direction: 'ltr',
      },
      {
        code: 'en-US',
        name: 'English US',
        direction: 'ltr',
      },
      {
        code: 'en-AU',
        name: 'English Australia',
        direction: 'ltr',
      },
      {
        code: 'en-CA',
        name: 'English Canada',
        direction: 'ltr',
      },
      {
        code: 'en-IN',
        name: 'English India',
        direction: 'ltr',
      },
      {
        code: 'en-IE',
        name: 'English Ireland',
        direction: 'ltr',
      },
      {
        code: 'en-NZ',
        name: 'English New Zealand',
        direction: 'ltr',
      },
      {
        code: 'en-SG',
        name: 'English Singapore',
        direction: 'ltr',
      },
      {
        code: 'eo-EU',
        name: 'Esperanto',
        direction: 'ltr',
      },
      {
        code: 'et-EE',
        name: 'Estonian',
        direction: 'ltr',
      },
      {
        code: 'fo-FO',
        name: 'Faroese',
        direction: 'ltr',
      },
      {
        code: 'fj-FJ',
        name: 'Fijian',
        direction: 'ltr',
      },
      {
        code: 'ff-FUL',
        name: 'Fula',
        direction: 'ltr',
      },
      {
        code: 'fil-PH',
        name: 'Filipino',
        direction: 'ltr',
      },
      {
        code: 'fi-FI',
        name: 'Finnish',
        direction: 'ltr',
      },
      {
        code: 'nl-BE',
        name: 'Flemish',
        direction: 'ltr',
      },
      {
        code: 'fr-FR',
        name: 'French',
        direction: 'ltr',
      },
      {
        code: 'fr-CA',
        name: 'French Canada',
        direction: 'ltr',
      },
      {
        code: 'fr-CH',
        name: 'French Swiss',
        direction: 'ltr',
      },
      {
        code: 'gl-ES',
        name: 'Galician',
        direction: 'ltr',
      },
      {
        code: 'mfi-NG',
        name: 'Gamargu',
        direction: 'ltr',
      },
      {
        code: 'grt-IN',
        name: 'Garo',
        direction: 'ltr',
      },
      {
        code: 'ka-GE',
        name: 'Georgian',
        direction: 'ltr',
      },
      {
        code: 'de-DE',
        name: 'German',
        direction: 'ltr',
      },
      {
        code: 'gil-KI',
        name: 'Gilbertese',
        direction: 'ltr',
      },
      {
        code: 'glw-NG',
        name: 'Glavda',
        direction: 'ltr',
      },
      {
        code: 'el-GR',
        name: 'Greek',
        direction: 'ltr',
      },
      {
        code: 'gcl-GD',
        name: 'Grenadian Creole English',
        direction: 'ltr',
      },
      {
        code: 'gu-IN',
        name: 'Gujarati',
        direction: 'ltr',
      },
      {
        code: 'gyn-GY',
        name: 'Guyanese Creole English',
        direction: 'ltr',
      },
      {
        code: 'ht-HT',
        name: 'Haitian Creole French',
        direction: 'ltr',
      },
      {
        code: 'ha-NE',
        name: 'Hausa',
        direction: 'ltr',
      },
      {
        code: 'US-HI',
        name: 'Hawaiian',
        direction: 'ltr',
      },
      {
        code: 'he-IL',
        name: 'Hebrew',
        direction: 'rtl',
      },
      {
        code: 'hig-NG',
        name: 'Higi',
        direction: 'ltr',
      },
      {
        code: 'hil-PH',
        name: 'Hiligaynon',
        direction: 'ltr',
      },
      {
        code: 'mrj-RU',
        name: 'Hill Mari',
        direction: 'ltr',
      },
      {
        code: 'hi-IN',
        name: 'Hindi',
        direction: 'ltr',
      },
      {
        code: 'hmn-CN',
        name: 'Hmong',
        direction: 'ltr',
      },
      {
        code: 'hu-HU',
        name: 'Hungarian',
        direction: 'ltr',
      },
      {
        code: 'is-IS',
        name: 'Icelandic',
        direction: 'ltr',
      },
      {
        code: 'ibo-NG',
        name: 'Igbo',
        direction: 'ltr',
      },
      {
        code: 'ilo-PH',
        name: 'Ilocano',
        direction: 'ltr',
      },
      {
        code: 'id-ID',
        name: 'Indonesian',
        direction: 'ltr',
      },
      {
        code: 'kal-GL',
        name: 'Inuktitut Greenlandic',
        direction: 'ltr',
      },
      {
        code: 'ga-IE',
        name: 'Irish Gaelic',
        direction: 'ltr',
      },
      {
        code: 'it-IT',
        name: 'Italian',
        direction: 'ltr',
      },
      {
        code: 'it-CH',
        name: 'Italian Swiss',
        direction: 'ltr',
      },
      {
        code: 'jam-JM',
        name: 'Jamaican Creole English',
        direction: 'ltr',
      },
      {
        code: 'ja-JP',
        name: 'Japanese',
        direction: 'ltr',
      },
      {
        code: 'jv-ID',
        name: 'Javanese',
        direction: 'rtl',
      },
      {
        code: 'quc-GT',
        name: "K'iche'",
        direction: 'ltr',
      },
      {
        code: 'kea-CV',
        name: 'Kabuverdianu',
        direction: 'ltr',
      },
      {
        code: 'kln-KE',
        name: 'Kalenjin',
        direction: 'ltr',
      },
      {
        code: 'kam-KE',
        name: 'Kamba',
        direction: 'ltr',
      },
      {
        code: 'kn-IN',
        name: 'Kannada',
        direction: 'ltr',
      },
      {
        code: 'kr-KAU',
        name: 'Kanuri',
        direction: 'ltr',
      },
      {
        code: 'kar-MM',
        name: 'Karen',
        direction: 'ltr',
      },
      {
        code: 'ks-IN',
        name: 'Kashmiri',
        direction: 'ltr',
      },
      {
        code: 'kk-KZ',
        name: 'Kazakh',
        direction: 'ltr',
      },
      {
        code: 'kha-IN',
        name: 'Khasi',
        direction: 'ltr',
      },
      {
        code: 'km-KH',
        name: 'Khmer',
        direction: 'ltr',
      },
      {
        code: 'kik-KE',
        name: 'Kikuyu',
        direction: 'ltr',
      },
      {
        code: 'rw-RW',
        name: 'Kinyarwanda',
        direction: 'ltr',
      },
      {
        code: 'run-RN',
        name: 'Kirundi',
        direction: 'ltr',
      },
      {
        code: 'guz-KE',
        name: 'Kisii',
        direction: 'ltr',
      },
      {
        code: 'kok-IN',
        name: 'Konkani',
        direction: 'ltr',
      },
      {
        code: 'ko-KR',
        name: 'Korean',
        direction: 'ltr',
      },
      {
        code: 'ku-KMR',
        name: 'Kurdish Kurmanji',
        direction: 'ltr',
      },
      {
        code: 'ku-CKB',
        name: 'Kurdish Sorani',
        direction: 'rtl',
      },
      {
        code: 'ky-KG',
        name: 'Kyrgyz',
        direction: 'ltr',
      },
      {
        code: 'lo-LA',
        name: 'Lao',
        direction: 'ltr',
      },
      {
        code: 'la-XN',
        name: 'Latin',
        direction: 'ltr',
      },
      {
        code: 'lv-LV',
        name: 'Latvian',
        direction: 'ltr',
      },
      {
        code: 'ln-LIN',
        name: 'Lingala',
        direction: 'ltr',
      },
      {
        code: 'lt-LT',
        name: 'Lithuanian',
        direction: 'ltr',
      },
      {
        code: 'lua-CD',
        name: 'Luba-Kasai',
        direction: 'ltr',
      },
      {
        code: 'lug-UG',
        name: 'Luganda',
        direction: 'ltr',
      },
      {
        code: 'luo-KE',
        name: 'Luo',
        direction: 'ltr',
      },
      {
        code: 'luy-KE',
        name: 'Luhya',
        direction: 'ltr',
      },
      {
        code: 'lb-LU',
        name: 'Luxembourgish',
        direction: 'ltr',
      },
      {
        code: 'mas-KE',
        name: 'Maa',
        direction: 'ltr',
      },
      {
        code: 'mk-MK',
        name: 'Macedonian',
        direction: 'ltr',
      },
      {
        code: 'mg-MLG',
        name: 'Malagasy',
        direction: 'ltr',
      },
      {
        code: 'ms-MY',
        name: 'Malay',
        direction: 'ltr',
      },
      {
        code: 'ml-IN',
        name: 'Malayalam',
        direction: 'ltr',
      },
      {
        code: 'div-DV',
        name: 'Maldivian',
        direction: 'rtl',
      },
      {
        code: 'mt-MT',
        name: 'Maltese',
        direction: 'ltr',
      },
      {
        code: 'mfi-CM',
        name: 'Mandara',
        direction: 'ltr',
      },
      {
        code: 'mni-IN',
        name: 'Manipuri',
        direction: 'ltr',
      },
      {
        code: 'mrt-NG',
        name: 'Margi',
        direction: 'ltr',
      },
      {
        code: 'mhr-RU',
        name: 'Mari',
        direction: 'ltr',
      },
      {
        code: 'mi-NZ',
        name: 'Maori',
        direction: 'ltr',
      },
      {
        code: 'mr-IN',
        name: 'Marathi',
        direction: 'ltr',
      },
      {
        code: 'mh-MH',
        name: 'Marshallese',
        direction: 'ltr',
      },
      {
        code: 'mer-KE',
        name: 'Meru',
        direction: 'ltr',
      },
      {
        code: 'nyf-KE',
        name: 'Mijikenda',
        direction: 'ltr',
      },
      {
        code: 'lus-IN',
        name: 'Mizo',
        direction: 'ltr',
      },
      {
        code: 'mn-MN',
        name: 'Mongolian',
        direction: 'ltr',
      },
      {
        code: 'sr-ME',
        name: 'Montenegrin',
        direction: 'ltr',
      },
      {
        code: 'mfe-MU',
        name: 'Morisyen',
        direction: 'ltr',
      },
      {
        code: 'ndc-MZ',
        name: 'Ndau',
        direction: 'ltr',
      },
      {
        code: 'nr-ZA',
        name: 'Ndebele',
        direction: 'ltr',
      },
      {
        code: 'ne-NP',
        name: 'Nepali',
        direction: 'ltr',
      },
      {
        code: 'nb-NO',
        name: 'Norwegian Bokmål',
        direction: 'ltr',
      },
      {
        code: 'nn-NO',
        name: 'Norwegian Nynorsk',
        direction: 'ltr',
      },
      {
        code: 'ny-NYA',
        name: 'Nyanja',
        direction: 'ltr',
      },
      {
        code: 'oc-FR',
        name: 'Occitan',
        direction: 'ltr',
      },
      {
        code: 'oc-ES',
        name: 'Occitan Aran',
        direction: 'ltr',
      },
      {
        code: 'ory-IN',
        name: 'Odia',
        direction: 'ltr',
      },
      {
        code: 'pau-PW',
        name: 'Palauan',
        direction: 'ltr',
      },
      {
        code: 'pi-IN',
        name: 'Pali',
        direction: 'ltr',
      },
      {
        code: 'pa-IN',
        name: 'Punjabi',
        direction: 'ltr',
      },
      {
        code: 'pnb-PK',
        name: 'Punjabi (Pakistan)',
        direction: 'rtl',
      },
      {
        code: 'pap-CW',
        name: 'Papiamentu',
        direction: 'ltr',
      },
      {
        code: 'ps-PK',
        name: 'Pashto',
        direction: 'rtl',
      },
      {
        code: 'fa-IR',
        name: 'Persian',
        direction: 'rtl',
      },
      {
        code: 'pl-PL',
        name: 'Polish',
        direction: 'ltr',
      },
      {
        code: 'pt-PT',
        name: 'Portuguese',
        direction: 'ltr',
      },
      {
        code: 'pt-BR',
        name: 'Portuguese Brazil',
        direction: 'ltr',
      },
      {
        code: 'qu-PE',
        name: 'Quechua',
        direction: 'ltr',
      },
      {
        code: 'rhg-MM',
        name: 'Rohingya',
        direction: 'rtl',
      },
      {
        code: 'rhl-MM',
        name: 'Rohingyalish',
        direction: 'ltr',
      },
      {
        code: 'ro-RO',
        name: 'Romanian',
        direction: 'ltr',
      },
      {
        code: 'roh-CH',
        name: 'Romansh',
        direction: 'ltr',
      },
      {
        code: 'run-BI',
        name: 'Rundi',
        direction: 'ltr',
      },
      {
        code: 'ru-RU',
        name: 'Russian',
        direction: 'ltr',
      },
      {
        code: 'acf-LC',
        name: 'Saint Lucian Creole French',
        direction: 'ltr',
      },
      {
        code: 'smo-WS',
        name: 'Samoan',
        direction: 'ltr',
      },
      {
        code: 'sa-IN',
        name: 'Sanskrit',
        direction: 'ltr',
      },
      {
        code: 'gd-GB',
        name: 'Scots Gaelic',
        direction: 'ltr',
      },
      {
        code: 'seh-ZW',
        name: 'Sena',
        direction: 'ltr',
      },
      {
        code: 'sr-Latn-RS',
        name: 'Serbian Latin',
        direction: 'ltr',
      },
      {
        code: 'sr-Cyrl-RS',
        name: 'Serbian Cyrillic',
        direction: 'ltr',
      },
      {
        code: 'crs-SC',
        name: 'Seselwa Creole French',
        direction: 'ltr',
      },
      {
        code: 'nso-ZA',
        name: 'Sesotho',
        direction: 'ltr',
      },
      {
        code: 'tn-ZA',
        name: 'Setswana (South Africa)',
        direction: 'ltr',
      },
      {
        code: 'sna-ZW',
        name: 'Shona',
        direction: 'ltr',
      },
      {
        code: 'snd-PK',
        name: 'Sindhi',
        direction: 'rtl',
      },
      {
        code: 'si-LK',
        name: 'Sinhala',
        direction: 'ltr',
      },
      {
        code: 'sk-SK',
        name: 'Slovak',
        direction: 'ltr',
      },
      {
        code: 'sl-SI',
        name: 'Slovenian',
        direction: 'ltr',
      },
      {
        code: 'so-SO',
        name: 'Somali',
        direction: 'ltr',
      },
      {
        code: 'es-ES',
        name: 'Spanish',
        direction: 'ltr',
      },
      {
        code: 'es-US',
        name: 'Spanish United States',
        direction: 'ltr',
      },
      {
        code: 'es-AR',
        name: 'Spanish Argentina',
        direction: 'ltr',
      },
      {
        code: 'es-MX',
        name: 'Spanish Mexico',
        direction: 'ltr',
      },
      {
        code: 'es-419',
        name: 'Spanish Latin America',
        direction: 'ltr',
      },
      {
        code: 'es-CO',
        name: 'Spanish Colombia',
        direction: 'ltr',
      },
      {
        code: 'su-ID',
        name: 'Sundanese',
        direction: 'ltr',
      },
      {
        code: 'sw-KE',
        name: 'Swahili',
        direction: 'ltr',
      },
      {
        code: 'sv-SE',
        name: 'Swedish',
        direction: 'ltr',
      },
      {
        code: 'de-CH',
        name: 'Swiss German',
        direction: 'ltr',
      },
      {
        code: 'syc-TR',
        name: 'Syriac (Aramaic)',
        direction: 'rtl',
      },
      {
        code: 'tl-PH',
        name: 'Tagalog',
        direction: 'ltr',
      },
      {
        code: 'tg-TJ',
        name: 'Tajik',
        direction: 'ltr',
      },
      {
        code: 'tmh-DZ',
        name: 'Tamashek (Tuareg)',
        direction: 'rtl',
      },
      {
        code: 'ta-IN',
        name: 'Tamil India',
        direction: 'ltr',
      },
      {
        code: 'ta-LK',
        name: 'Tamil Sri Lanka',
        direction: 'ltr',
      },
      {
        code: 'te-IN',
        name: 'Telugu',
        direction: 'ltr',
      },
      {
        code: 'tt-RU',
        name: 'Tatar',
        direction: 'ltr',
      },
      {
        code: 'th-TH',
        name: 'Thai',
        direction: 'ltr',
      },
      {
        code: 'ty-PF',
        name: 'Tahitian',
        direction: 'ltr',
      },
      {
        code: 'bod-CN',
        name: 'Tibetan',
        direction: 'ltr',
      },
      {
        code: 'ti-ET',
        name: 'Tigrinya',
        direction: 'ltr',
      },
      {
        code: 'tpi-PG',
        name: 'Tok Pisin',
        direction: 'ltr',
      },
      {
        code: 'ton-TO',
        name: 'Tongan',
        direction: 'ltr',
      },
      {
        code: 'ts-ZA',
        name: 'Tsonga',
        direction: 'ltr',
      },
      {
        code: 'tsc-MZ',
        name: 'Tswa',
        direction: 'ltr',
      },
      {
        code: 'tn-BW',
        name: 'Tswana',
        direction: 'ltr',
      },
      {
        code: 'tr-TR',
        name: 'Turkish',
        direction: 'ltr',
      },
      {
        code: 'tk-TM',
        name: 'Turkmen',
        direction: 'ltr',
      },
      {
        code: 'udm-RU',
        name: 'Udmurt',
        direction: 'ltr',
      },
      {
        code: 'uig-CN',
        name: 'Uyghur',
        direction: 'ltr',
      },
      {
        code: 'uk-UA',
        name: 'Ukrainian',
        direction: 'ltr',
      },
      {
        code: 'ur-PK',
        name: 'Urdu',
        direction: 'rtl',
      },
      {
        code: 'uz-UZ',
        name: 'Uzbek',
        direction: 'ltr',
      },
      {
        code: 'vi-VN',
        name: 'Vietnamese',
        direction: 'ltr',
      },
      {
        code: 'svc-VC',
        name: 'Vincentian Creole English',
        direction: 'ltr',
      },
      {
        code: 'vic-US',
        name: 'Virgin Islands Creole English',
        direction: 'ltr',
      },
      {
        code: 'cy-GB',
        name: 'Welsh',
        direction: 'ltr',
      },
      {
        code: 'wo-SN',
        name: 'Wolof',
        direction: 'ltr',
      },
      {
        code: 'xh-ZA',
        name: 'Xhosa',
        direction: 'ltr',
      },
      {
        code: 'yi-YD',
        name: 'Yiddish',
        direction: 'rtl',
      },
      {
        code: 'yo-NG',
        name: 'Yoruba',
        direction: 'ltr',
      },
      {
        code: 'zu-ZA',
        name: 'Zulu',
        direction: 'ltr',
      },
    ],
  }

  {
    const buttonQR = document.createElement('div')
    buttonQR.id = 'quality-report-button'
    document.body.appendChild(buttonQR)

    const headerBars = document.createElement('div')
    headerBars.id = 'header-bars-wrapper'
    document.body.appendChild(headerBars)

    const footerStats = document.createElement('footer')
    footerStats.classList = 'stats-foo'
    document.body.appendChild(footerStats)

    const outer = document.createElement('div')
    outer.id = 'outer'
    document.body.appendChild(outer)
  }

  await import('./es6/components')
  await import('../common')
  await import('./ui.core')
  // await import('./ui.segment')
  await import('./ui.init')
  // await import('./ui.events')
  await import('./ui.header')
  await import('./es6/ajax_utils/segmentAjax')
  await import('./es6/ajax_utils/jobAjax')
  await import('../tm')

  global.UI.start()

  expect(document.getElementById('outer')).toHaveClass('loading')

  screen.debug()
})
