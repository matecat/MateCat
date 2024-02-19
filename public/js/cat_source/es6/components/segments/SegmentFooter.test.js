import {render, screen, waitFor, fireEvent, act} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import React from 'react'
import {SegmentContext} from './SegmentContext'
import SegmentFooter from './SegmentFooter'
import {http, HttpResponse} from 'msw'
import {mswServer} from '../../../../../mocks/mswServer'
window.React = React

window.config = {
  basepath: '/',
  password: '74ac64e1f60e',
  mt_enabled: true,
  id_job: '6',
  segmentFilterEnabled: true,
  translation_matches_enabled: 1,
  source_rfc: 'en-US',
  target_rfc: 'it-IT',
  tag_projection_languages:
    '{"en-de":"English - German","en-es":"English - Spanish","en-fr":"English - French","en-it":"English - Italian","en-pt":"English - Portuguese","en-ru":"English - Russian","en-cs":"English - Czech","en-nl":"English - Dutch","en-fi":"English - Finnish","en-pl":"English - Polish","en-da":"English - Danish","en-sv":"English - Swedish","en-el":"English - Greek","en-hu":"English - Hungarian","en-lt":"English - Lithuanian","en-ja":"English - Japanese","en-et":"English - Estonian","en-sk":"English - Slovak","en-bg":"English - Bulgarian","en-bs":"English - Bosnian","en-ar":"English - Arabic","en-ca":"English - Catalan","en-zh":"English - Chinese","en-he":"English - Hebrew","en-hr":"English - Croatian","en-id":"English - Indonesian","en-is":"English - Icelandic","en-ko":"English - Korean","en-lv":"English - Latvian","en-mk":"English - Macedonian","en-ms":"English - Malay","en-mt":"English - Maltese","en-nb":"English - Norwegian Bokmål","en-nn":"English - Norwegian Nynorsk","en-ro":"English - Romanian","en-sl":"English - Slovenian","en-sq":"English - Albanian","en-sr":"English - Montenegrin","en-th":"English - Thai","en-tr":"English - Turkish","en-uk":"English - Ukrainian","en-vi":"English - Vietnamese","de-it":"German - Italian","de-fr":"German - French","de-cs":"German - Czech","fr-it":"French - Italian","fr-nl":"French - Dutch","it-es":"Italian - Spanish","da-sv":"Danish - Swedish","nl-pt":"Dutch - Portuguese","nl-fi":"Dutch - Finnish","zh-en":"Chinese - English","sv-da":"Swedish - Danish","cs-de":"Czech - German"}',
  languages_array: [
    {code: 'af-ZA', name: 'Afrikaans', direction: 'ltr'},
    {code: 'sq-AL', name: 'Albanian', direction: 'ltr'},
    {code: 'am-ET', name: 'Amharic', direction: 'ltr'},
    {
      code: 'aig-AG',
      name: 'Antigua and Barbuda Creole English',
      direction: 'ltr',
    },
    {code: 'ar-SA', name: 'Arabic', direction: 'rtl'},
    {code: 'ar-EG', name: 'Arabic Egyptian', direction: 'rtl'},
    {code: 'an-ES', name: 'Aragonese', direction: 'ltr'},
    {code: 'hy-AM', name: 'Armenian', direction: 'ltr'},
    {code: 'as-IN', name: 'Assamese', direction: 'ltr'},
    {code: 'ast-ES', name: 'Asturian', direction: 'ltr'},
    {code: 'de-AT', name: 'Austrian German', direction: 'ltr'},
    {code: 'az-AZ', name: 'Azerbaijani', direction: 'ltr'},
    {code: 'bah-BS', name: 'Bahamas Creole English', direction: 'ltr'},
    {code: 'bjs-BB', name: 'Bajan', direction: 'ltr'},
    {code: 'rm-RO', name: 'Balkan Gipsy', direction: 'ltr'},
    {code: 'ba-RU', name: 'Bashkir', direction: 'ltr'},
    {code: 'eu-ES', name: 'Basque', direction: 'ltr'},
    {code: 'bem-ZM', name: 'Bemba', direction: 'ltr'},
    {code: 'bn-IN', name: 'Bengali', direction: 'ltr'},
    {code: 'be-BY', name: 'Belarusian', direction: 'ltr'},
    {code: 'fr-BE', name: 'Belgian French', direction: 'ltr'},
    {code: 'bh-IN', name: 'Bihari', direction: 'ltr'},
    {code: 'bi-VU', name: 'Bislama', direction: 'ltr'},
    {code: 'gax-KE', name: 'Borana', direction: 'ltr'},
    {code: 'bs-BA', name: 'Bosnian', direction: 'ltr'},
    {code: 'bs-Cyrl-BA', name: 'Bosnian (Cyrillic)', direction: 'ltr'},
    {code: 'br-FR', name: 'Breton', direction: 'ltr'},
    {code: 'bg-BG', name: 'Bulgarian', direction: 'ltr'},
    {code: 'my-MM', name: 'Burmese', direction: 'ltr'},
    {code: 'ca-ES', name: 'Catalan', direction: 'ltr'},
    {code: 'cav-ES', name: 'Catalan Valencian', direction: 'ltr'},
    {code: 'cb-PH', name: 'Cebuano', direction: 'ltr'},
    {code: 'shu-TD', name: 'Chadian Arabic', direction: 'rtl'},
    {code: 'ch-GU', name: 'Chamorro', direction: 'ltr'},
    {code: 'chr-US', name: 'Cherokee', direction: 'ltr'},
    {code: 'zh-CN', name: 'Chinese Simplified', direction: 'ltr'},
    {code: 'zh-TW', name: 'Chinese Traditional', direction: 'ltr'},
    {code: 'zh-HK', name: 'Chinese Trad. (Hong Kong)', direction: 'ltr'},
    {code: 'zh-MO', name: 'Chinese Traditional Macau', direction: 'ltr'},
    {code: 'ctg-BD', name: 'Chittagonian', direction: 'ltr'},
    {code: 'grc-GR', name: 'Classical Greek', direction: 'ltr'},
    {code: 'zdj-KM', name: 'Comorian Ngazidja', direction: 'ltr'},
    {code: 'cop-EG', name: 'Coptic', direction: 'ltr'},
    {code: 'pov-GW', name: 'Crioulo Upper Guinea', direction: 'ltr'},
    {code: 'hr-HR', name: 'Croatian', direction: 'ltr'},
    {code: 'cs-CZ', name: 'Czech', direction: 'ltr'},
    {code: 'da-DK', name: 'Danish', direction: 'ltr'},
    {code: 'fa-AF', name: 'Dari', direction: 'rtl'},
    {code: 'nl-NL', name: 'Dutch', direction: 'ltr'},
    {code: 'dz-BT', name: 'Dzongkha', direction: 'ltr'},
    {code: 'en-GB', name: 'English', direction: 'ltr'},
    {code: 'en-US', name: 'English US', direction: 'ltr'},
    {code: 'en-AU', name: 'English Australia', direction: 'ltr'},
    {code: 'en-CA', name: 'English Canada', direction: 'ltr'},
    {code: 'en-IN', name: 'English India', direction: 'ltr'},
    {code: 'en-IE', name: 'English Ireland', direction: 'ltr'},
    {code: 'en-NZ', name: 'English New Zealand', direction: 'ltr'},
    {code: 'en-SG', name: 'English Singapore', direction: 'ltr'},
    {code: 'en-ZA', name: 'English South Africa', direction: 'ltr'},
    {code: 'vmw-MZ', name: 'Emakhuwa', direction: 'ltr'},
    {code: 'eo-EU', name: 'Esperanto', direction: 'ltr'},
    {code: 'et-EE', name: 'Estonian', direction: 'ltr'},
    {code: 'fn-FNG', name: 'Fanagalo', direction: 'ltr'},
    {code: 'fo-FO', name: 'Faroese', direction: 'ltr'},
    {code: 'fj-FJ', name: 'Fijian', direction: 'ltr'},
    {code: 'ff-FUL', name: 'Fula', direction: 'ltr'},
    {code: 'fil-PH', name: 'Filipino', direction: 'ltr'},
    {code: 'fi-FI', name: 'Finnish', direction: 'ltr'},
    {code: 'nl-BE', name: 'Flemish', direction: 'ltr'},
    {code: 'fr-FR', name: 'French', direction: 'ltr'},
    {code: 'fr-CA', name: 'French Canada', direction: 'ltr'},
    {code: 'fr-CH', name: 'French Swiss', direction: 'ltr'},
    {code: 'gl-ES', name: 'Galician', direction: 'ltr'},
    {code: 'mfi-NG', name: 'Gamargu', direction: 'ltr'},
    {code: 'grt-IN', name: 'Garo', direction: 'ltr'},
    {code: 'ka-GE', name: 'Georgian', direction: 'ltr'},
    {code: 'de-DE', name: 'German', direction: 'ltr'},
    {code: 'gil-KI', name: 'Gilbertese', direction: 'ltr'},
    {code: 'glw-NG', name: 'Glavda', direction: 'ltr'},
    {code: 'el-GR', name: 'Greek', direction: 'ltr'},
    {code: 'gcl-GD', name: 'Grenadian Creole English', direction: 'ltr'},
    {code: 'gu-IN', name: 'Gujarati', direction: 'ltr'},
    {code: 'gyn-GY', name: 'Guyanese Creole English', direction: 'ltr'},
    {code: 'ht-HT', name: 'Haitian Creole French', direction: 'ltr'},
    {code: 'ha-NE', name: 'Hausa', direction: 'ltr'},
    {code: 'haw-US', name: 'Hawaiian', direction: 'ltr'},
    {code: 'he-IL', name: 'Hebrew', direction: 'rtl'},
    {code: 'hig-NG', name: 'Higi', direction: 'ltr'},
    {code: 'hil-PH', name: 'Hiligaynon', direction: 'ltr'},
    {code: 'mrj-RU', name: 'Hill Mari', direction: 'ltr'},
    {code: 'hi-IN', name: 'Hindi', direction: 'ltr'},
    {code: 'hmn-CN', name: 'Hmong', direction: 'ltr'},
    {code: 'hu-HU', name: 'Hungarian', direction: 'ltr'},
    {code: 'is-IS', name: 'Icelandic', direction: 'ltr'},
    {code: 'ibo-NG', name: 'Igbo', direction: 'ltr'},
    {code: 'ilo-PH', name: 'Ilocano', direction: 'ltr'},
    {code: 'id-ID', name: 'Indonesian', direction: 'ltr'},
    {code: 'kl-GL', name: 'Inuktitut Greenlandic', direction: 'ltr'},
    {code: 'ga-IE', name: 'Irish Gaelic', direction: 'ltr'},
    {code: 'it-IT', name: 'Italian', direction: 'ltr'},
    {code: 'it-CH', name: 'Italian Swiss', direction: 'ltr'},
    {code: 'jam-JM', name: 'Jamaican Creole English', direction: 'ltr'},
    {code: 'ja-JP', name: 'Japanese', direction: 'ltr'},
    {code: 'jv-ID', name: 'Javanese', direction: 'ltr'},
    {code: 'quc-GT', name: "K'iche'", direction: 'ltr'},
    {code: 'kea-CV', name: 'Kabuverdianu', direction: 'ltr'},
    {code: 'kab-DZ', name: 'Kabylian', direction: 'ltr'},
    {code: 'kln-KE', name: 'Kalenjin', direction: 'ltr'},
    {code: 'kam-KE', name: 'Kamba', direction: 'ltr'},
    {code: 'kn-IN', name: 'Kannada', direction: 'ltr'},
    {code: 'kr-KAU', name: 'Kanuri', direction: 'ltr'},
    {code: 'kar-MM', name: 'Karen', direction: 'ltr'},
    {code: 'ks-IN', name: 'Kashmiri', direction: 'ltr'},
    {code: 'kk-KZ', name: 'Kazakh', direction: 'ltr'},
    {code: 'kha-IN', name: 'Khasi', direction: 'ltr'},
    {code: 'km-KH', name: 'Khmer', direction: 'ltr'},
    {code: 'kik-KE', name: 'Kikuyu', direction: 'ltr'},
    {code: 'rw-RW', name: 'Kinyarwanda', direction: 'ltr'},
    {code: 'rn-BI', name: 'Kirundi', direction: 'ltr'},
    {code: 'guz-KE', name: 'Kisii', direction: 'ltr'},
    {code: 'kok-IN', name: 'Konkani', direction: 'ltr'},
    {code: 'ko-KR', name: 'Korean', direction: 'ltr'},
    {code: 'ku-KMR', name: 'Kurdish Kurmanji', direction: 'ltr'},
    {code: 'ckb-IQ', name: 'Kurdish Sorani', direction: 'rtl'},
    {code: 'ky-KG', name: 'Kyrgyz', direction: 'ltr'},
    {code: 'lo-LA', name: 'Lao', direction: 'ltr'},
    {code: 'la-XN', name: 'Latin', direction: 'ltr'},
    {code: 'lv-LV', name: 'Latvian', direction: 'ltr'},
    {code: 'ln-LIN', name: 'Lingala', direction: 'ltr'},
    {code: 'lt-LT', name: 'Lithuanian', direction: 'ltr'},
    {code: 'lua-CD', name: 'Luba-Kasai', direction: 'ltr'},
    {code: 'lg-UG', name: 'Luganda', direction: 'ltr'},
    {code: 'luo-KE', name: 'Luo', direction: 'ltr'},
    {code: 'luy-KE', name: 'Luhya', direction: 'ltr'},
    {code: 'lb-LU', name: 'Luxembourgish', direction: 'ltr'},
    {code: 'mas-KE', name: 'Maa', direction: 'ltr'},
    {code: 'mk-MK', name: 'Macedonian', direction: 'ltr'},
    {code: 'mg-MG', name: 'Malagasy', direction: 'ltr'},
    {code: 'ms-MY', name: 'Malay', direction: 'ltr'},
    {code: 'ml-IN', name: 'Malayalam', direction: 'ltr'},
    {code: 'dv-MV', name: 'Maldivian', direction: 'rtl'},
    {code: 'mt-MT', name: 'Maltese', direction: 'ltr'},
    {code: 'mfi-CM', name: 'Mandara', direction: 'ltr'},
    {code: 'mni-IN', name: 'Manipuri', direction: 'ltr'},
    {code: 'gv-IM', name: 'Manx Gaelic', direction: 'ltr'},
    {code: 'mrt-NG', name: 'Margi', direction: 'ltr'},
    {code: 'mhr-RU', name: 'Mari', direction: 'ltr'},
    {code: 'ar-MA', name: 'Moroccan Arabic', direction: 'rtl'},
    {code: 'mi-NZ', name: 'Maori', direction: 'ltr'},
    {code: 'mr-IN', name: 'Marathi', direction: 'ltr'},
    {code: 'mh-MH', name: 'Marshallese', direction: 'ltr'},
    {code: 'men-SL', name: 'Mende', direction: 'ltr'},
    {code: 'mer-KE', name: 'Meru', direction: 'ltr'},
    {code: 'nyf-KE', name: 'Mijikenda', direction: 'ltr'},
    {code: 'lus-IN', name: 'Mizo', direction: 'ltr'},
    {code: 'mn-MN', name: 'Mongolian', direction: 'ltr'},
    {code: 'sr-ME', name: 'Montenegrin', direction: 'ltr'},
    {code: 'mfe-MU', name: 'Morisyen', direction: 'ltr'},
    {code: 'ndc-MZ', name: 'Ndau', direction: 'ltr'},
    {code: 'nr-ZA', name: 'Ndebele', direction: 'ltr'},
    {code: 'ne-NP', name: 'Nepali', direction: 'ltr'},
    {code: 'niu-NU', name: 'Niuean', direction: 'ltr'},
    {code: 'nso-ZA', name: 'Sesotho', direction: 'ltr'},
    {code: 'nb-NO', name: 'Norwegian Bokmål', direction: 'ltr'},
    {code: 'nn-NO', name: 'Norwegian Nynorsk', direction: 'ltr'},
    {code: 'ny-MW', name: 'Nyanja', direction: 'ltr'},
    {code: 'oc-FR', name: 'Occitan', direction: 'ltr'},
    {code: 'oc-ES', name: 'Occitan Aran', direction: 'ltr'},
    {code: 'ory-IN', name: 'Odia', direction: 'ltr'},
    {code: 'pau-PW', name: 'Palauan', direction: 'ltr'},
    {code: 'pi-IN', name: 'Pali', direction: 'ltr'},
    {code: 'pa-IN', name: 'Punjabi', direction: 'ltr'},
    {code: 'pnb-PK', name: 'Punjabi (Pakistan)', direction: 'rtl'},
    {code: 'ur-PK', name: 'Urdu', direction: 'rtl'},
    {code: 'pap-CW', name: 'Papiamentu', direction: 'ltr'},
    {code: 'ps-PK', name: 'Pashto', direction: 'rtl'},
    {code: 'fa-IR', name: 'Persian', direction: 'rtl'},
    {code: 'pis-SB', name: 'Pijin', direction: 'ltr'},
    {code: 'pl-PL', name: 'Polish', direction: 'ltr'},
    {code: 'pt-PT', name: 'Portuguese', direction: 'ltr'},
    {code: 'pt-BR', name: 'Portuguese Brazil', direction: 'ltr'},
    {code: 'pot-US', name: 'Potawatomi', direction: 'ltr'},
    {code: 'qu-PE', name: 'Quechua', direction: 'ltr'},
    {code: 'rhg-MM', name: 'Rohingya', direction: 'rtl'},
    {code: 'rhl-MM', name: 'Rohingyalish', direction: 'ltr'},
    {code: 'ro-RO', name: 'Romanian', direction: 'ltr'},
    {code: 'roh-CH', name: 'Romansh', direction: 'ltr'},
    {code: 'run-BI', name: 'Rundi', direction: 'ltr'},
    {code: 'ru-RU', name: 'Russian', direction: 'ltr'},
    {code: 'acf-LC', name: 'Saint Lucian Creole French', direction: 'ltr'},
    {code: 'sm-WS', name: 'Samoan', direction: 'ltr'},
    {code: 'sg-CF', name: 'Sango', direction: 'ltr'},
    {code: 'sa-IN', name: 'Sanskrit', direction: 'ltr'},
    {code: 'gd-GB', name: 'Scots Gaelic', direction: 'ltr'},
    {code: 'seh-ZW', name: 'Sena', direction: 'ltr'},
    {code: 'sr-Latn-RS', name: 'Serbian Latin', direction: 'ltr'},
    {code: 'sr-Cyrl-RS', name: 'Serbian Cyrillic', direction: 'ltr'},
    {code: 'crs-SC', name: 'Seselwa Creole French', direction: 'ltr'},
    {code: 'tn-ZA', name: 'Setswana (South Africa)', direction: 'ltr'},
    {code: 'sn-ZW', name: 'Shona', direction: 'ltr'},
    {code: 'snd-PK', name: 'Sindhi', direction: 'rtl'},
    {code: 'si-LK', name: 'Sinhala', direction: 'ltr'},
    {code: 'sk-SK', name: 'Slovak', direction: 'ltr'},
    {code: 'sl-SI', name: 'Slovenian', direction: 'ltr'},
    {code: 'so-SO', name: 'Somali', direction: 'ltr'},
    {code: 'st-LS', name: 'Sotho Southern', direction: 'ltr'},
    {code: 'es-ES', name: 'Spanish', direction: 'ltr'},
    {code: 'es-US', name: 'Spanish United States', direction: 'ltr'},
    {code: 'es-AR', name: 'Spanish Argentina', direction: 'ltr'},
    {code: 'es-MX', name: 'Spanish Mexico', direction: 'ltr'},
    {code: 'es-419', name: 'Spanish Latin America', direction: 'ltr'},
    {code: 'es-CO', name: 'Spanish Colombia', direction: 'ltr'},
    {code: 'srn-SR', name: 'Sranan Tongo', direction: 'ltr'},
    {code: 'su-ID', name: 'Sundanese', direction: 'ltr'},
    {code: 'sw-KE', name: 'Swahili', direction: 'ltr'},
    {code: 'sv-SE', name: 'Swedish', direction: 'ltr'},
    {code: 'de-CH', name: 'Swiss German', direction: 'ltr'},
    {code: 'syc-TR', name: 'Syriac (Aramaic)', direction: 'rtl'},
    {code: 'tl-PH', name: 'Tagalog', direction: 'ltr'},
    {code: 'tg-TJ', name: 'Tajik', direction: 'ltr'},
    {code: 'tmh-DZ', name: 'Tamashek (Tuareg)', direction: 'rtl'},
    {code: 'ta-IN', name: 'Tamil India', direction: 'ltr'},
    {code: 'ta-LK', name: 'Tamil Sri Lanka', direction: 'ltr'},
    {code: 'te-IN', name: 'Telugu', direction: 'ltr'},
    {code: 'tt-RU', name: 'Tatar', direction: 'ltr'},
    {code: 'tet-TL', name: 'Tetum', direction: 'ltr'},
    {code: 'th-TH', name: 'Thai', direction: 'ltr'},
    {code: 'ty-PF', name: 'Tahitian', direction: 'ltr'},
    {code: 'bo-CN', name: 'Tibetan', direction: 'ltr'},
    {code: 'ti-ET', name: 'Tigrinya', direction: 'ltr'},
    {code: 'tkl-TK', name: 'Tokelauan', direction: 'ltr'},
    {code: 'tpi-PG', name: 'Tok Pisin', direction: 'ltr'},
    {code: 'to-TO', name: 'Tongan', direction: 'ltr'},
    {code: 'ts-ZA', name: 'Tsonga', direction: 'ltr'},
    {code: 'tsc-MZ', name: 'Tswa', direction: 'ltr'},
    {code: 'tn-BW', name: 'Tswana', direction: 'ltr'},
    {code: 'tr-TR', name: 'Turkish', direction: 'ltr'},
    {code: 'tk-TM', name: 'Turkmen', direction: 'ltr'},
    {code: 'tvl-TV', name: 'Tuvaluan', direction: 'ltr'},
    {code: 'udm-RU', name: 'Udmurt', direction: 'ltr'},
    {code: 'uig-CN', name: 'Uyghur', direction: 'ltr'},
    {code: 'uk-UA', name: 'Ukrainian', direction: 'ltr'},
    {code: 'ppk-ID', name: 'Uma', direction: 'ltr'},
    {code: 'uz-UZ', name: 'Uzbek', direction: 'ltr'},
    {code: 'vi-VN', name: 'Vietnamese', direction: 'ltr'},
    {code: 'svc-VC', name: 'Vincentian Creole English', direction: 'ltr'},
    {code: 'vic-US', name: 'Virgin Islands Creole English', direction: 'ltr'},
    {code: 'wls-WF', name: 'Wallisian', direction: 'ltr'},
    {code: 'cy-GB', name: 'Welsh', direction: 'ltr'},
    {code: 'wo-SN', name: 'Wolof', direction: 'ltr'},
    {code: 'xh-ZA', name: 'Xhosa', direction: 'ltr'},
    {code: 'yi-YD', name: 'Yiddish', direction: 'rtl'},
    {code: 'yo-NG', name: 'Yoruba', direction: 'ltr'},
    {code: 'zu-ZA', name: 'Zulu', direction: 'ltr'},
  ],
}

const superClassnamesFunction = window.classnames
const superScrollToElementFunction = window.HTMLElement.prototype.scrollTo

jest.mock('../../actions/CatToolActions', () => ({
  retrieveJobKeys: jest.fn(() => false),
}))

beforeAll(() => {
  window.classnames = () => {}
  window.HTMLElement.prototype.scrollTo = () => {}
})

afterAll(() => {
  window.classnames = superClassnamesFunction
  window.HTMLElement.prototype.scrollTo = superScrollToElementFunction
})

require('../../../ui.core')
require('../../../ui.segment')
UI.start = () => {}

const props = {
  segment: JSON.parse(
    '{"original_sid":"608","lxqDecodedTranslation":" La loro musica è stata variamente descritta come hard rock, rock blues, e metallo pesante,[2] ma la band stessa lo chiama semplicemente \\"rock and roll\\".[3]","id_file":"6","notes":null,"readonly":"false","original_translation":" La loro musica è stata variamente descritta come hard rock, rock blues, e metallo pesante,[2]##$_A0$##ma la band stessa lo chiama semplicemente \\"rock and roll\\".[3]","contributions":{"matches":[{"target_note":"","memory_key":"","prop":[],"last_updated_by":"MT!","match":"MT","ICE":false,"reference":"Machine Translation provided by Google, Microsoft, Worldlingo or MyMemory customized engine.","subject":false,"created_by":"MT","usage_count":1,"create_date":"2022-01-19 16:43:07","target":"it-IT","translation":" La loro musica è stata variamente descritta come &lt;g id=\\"8\\"&gt;hard rock&lt;/g&gt;, &lt;g id=\\"9\\"&gt;rock blues&lt;/g&gt;, e &lt;g id=\\"10\\"&gt;metallo pesante&lt;/g&gt;,&lt;g id=\\"11\\"&gt;[2]&lt;/g&gt;##$_A0$##ma la band stessa lo chiama semplicemente &quot;&lt;g id=\\"12\\"&gt;rock and roll&lt;/g&gt;&quot;.&lt;g id=\\"13\\"&gt;[3]&lt;/g&gt;","segment":"##$_A0$##Their music has been variously described as##$_A0$##&lt;g id=\\"8\\"&gt;hard rock&lt;/g&gt;,##$_A0$##&lt;g id=\\"9\\"&gt;blues rock&lt;/g&gt;, and##$_A0$##&lt;g id=\\"10\\"&gt;heavy metal&lt;/g&gt;,&lt;g id=\\"11\\"&gt;[2]&lt;/g&gt;##$_A0$##but the band themselves call it simply \\"&lt;g id=\\"12\\"&gt;rock and roll&lt;/g&gt;\\".&lt;g id=\\"13\\"&gt;[3]&lt;/g&gt;","source_note":"","tm_properties":null,"raw_translation":" La loro musica è stata variamente descritta come <g id=\\"8\\">hard rock</g>, <g id=\\"9\\">rock blues</g>, e <g id=\\"10\\">metallo pesante</g>,<g id=\\"11\\">[2]</g> ma la band stessa lo chiama semplicemente &quot;<g id=\\"12\\">rock and roll</g>&quot;.<g id=\\"13\\">[3]</g>","source":"en-US","id":0,"last_update_date":"2022-01-19","raw_segment":" Their music has been variously described as <g id=\\"8\\">hard rock</g>, <g id=\\"9\\">blues rock</g>, and <g id=\\"10\\">heavy metal</g>,<g id=\\"11\\">[2]</g> but the band themselves call it simply \\"<g id=\\"12\\">rock and roll</g>\\".<g id=\\"13\\">[3]</g>","quality":70}]},"unlocked":false,"propagable":false,"openIssues":false,"context_groups":null,"jid":"6","currentInSearch":false,"edit_area_locked":false,"filename":"ACDC.docx","tagMismatch":{},"opened":true,"modified":false,"parsed_time_to_edit":["00","00","00",96],"originalDecodedTranslation":" La loro musica è stata variamente descritta come hard rock, rock blues, e metallo pesante,[2]°ma la band stessa lo chiama semplicemente \\"rock and roll\\".[3]","tagged":false,"lxqDecodedSource":" Their music has been variously described as hard rock, blues rock, and heavy metal,[2] but the band themselves call it simply \\"rock and roll\\".[3]","revision_number":null,"target_chunk_lengths":{"len":[0],"statuses":["DRAFT"]},"cl_contributions":{"matches":[],"errors":[]},"inSearch":false,"sid":"608","searchParams":{},"occurrencesInSearch":null,"metadata":[],"repetitions_in_chunk":"1","version_number":"1","openSplit":false,"translation":" La loro musica è stata variamente descritta come hard rock, rock blues, e metallo pesante,[2]##$_A0$##ma la band stessa lo chiama semplicemente \\"rock and roll\\".[3]","decodedSource":"°Their music has been variously described as°hard rock,°blues rock, and°heavy metal,[2]°but the band themselves call it simply \\"rock and roll\\".[3]","status":"DRAFT","targetTagMap":[{"offset":94,"length":9,"type":"nbsp","mutability":"IMMUTABLE","data":{"id":"","name":"nbsp","encodedText":"##$_A0$##","decodedText":"°","openTagId":null,"closeTagId":null,"openTagKey":null,"closeTagKey":null,"placeholder":"°","originalOffset":-1}}],"segment":"##$_A0$##Their music has been variously described as##$_A0$##&lt;g id=\\"8\\"&gt;hard rock&lt;/g&gt;,##$_A0$##&lt;g id=\\"9\\"&gt;blues rock&lt;/g&gt;, and##$_A0$##&lt;g id=\\"10\\"&gt;heavy metal&lt;/g&gt;,&lt;g id=\\"11\\"&gt;[2]&lt;/g&gt;##$_A0$##but the band themselves call it simply \\"&lt;g id=\\"12\\"&gt;rock and roll&lt;/g&gt;\\".&lt;g id=\\"13\\"&gt;[3]&lt;/g&gt;","missingTagsInTarget":[],"updatedSource":"##$_A0$##Their music has been variously described as##$_A0$##hard rock,##$_A0$##blues rock, and##$_A0$##heavy metal,[2]##$_A0$##but the band themselves call it simply \\"rock and roll\\".[3]","warnings":{},"source_chunk_lengths":[],"splitted":false,"lexiqa":{"target":{"urls":[],"mspolicheck":[],"numbers":[],"spaces":[],"specialchardetect":[],"punctuation":[],"spelling":[{"insource":false,"msg":"and","start":143,"errorid":"matecat-6-2b14d6279fd8_608_143_146_d1g_t","color":"#563d7c","length":3,"module":"d1g","suggestions":["ad","rand","band"],"ignored":false,"end":146,"category":"spelling"}],"blacklist":[],"glossary":[]}},"segment_hash":"f4d5a08434b1909daa43b85eb9574701","data_ref_map":null,"decodedTranslation":" La loro musica è stata variamente descritta come hard rock, rock blues, e metallo pesante,[2]°ma la band stessa lo chiama semplicemente \\"rock and roll\\".[3]","selected":false,"versions":[{"diff":null,"created_at":"2022-01-13 10:57:29","propagated_from":0,"id_segment":608,"version_number":1,"translation":" La loro musica è stata variamente descritta come hard rock, rock blues, e metallo pesante,[2]##$_A0$##ma la band stessa lo chiama semplicemente \\"rock and roll\\".[3]","id_job":6,"issues":[],"id":0},{"diff":null,"created_at":"2022-01-13 09:57:29","propagated_from":0,"id_segment":608,"version_number":0,"translation":"La loro musica è stata variamente descritta come&lt;g id=\\"8\\"&gt; hard rock&lt;/g&gt; ,&lt;g id=\\"9\\"&gt; rock blues&lt;/g&gt; , e&lt;g id=\\"10\\"&gt; metallo pesante&lt;/g&gt; ,&lt;g id=\\"11\\"&gt; [2]&lt;/g&gt; ma la band stessa lo chiama semplicemente &quot;&lt;g id=\\"12\\"&gt; rock and roll&lt;/g&gt; &quot;.&lt;g id=\\"13\\"&gt; [3]&lt;/g&gt;","id_job":6,"issues":[],"id":11}],"time_to_edit":"96","warning":"0","sourceTagMap":[],"glossary":[],"openComments":false,"ice_locked":"0","autopropagated_from":"0"}',
  ),
}

const executeMswServer = () => {
  mswServer.use(
    ...[
      http.get('/api/app/tm-keys/6/74ac64e1f60e', () => {
        return HttpResponse.json({
          tm_keys: [
            {
              tm: true,
              glos: true,
              owner: true,
              uid_transl: null,
              uid_rev: null,
              name: 'Test',
              key: 'c52da4a03d6aea33f242',
              r: true,
              w: true,
              r_transl: null,
              w_transl: null,
              r_rev: null,
              w_rev: null,
              is_shared: false,
            },
          ],
        })
      }),
      http.post('/api/app/glossary/_keys', () => {
        return HttpResponse.json({})
      }),
      http.post('/api/app/glossary/_domains', () => {
        return HttpResponse.json({})
      }),
    ],
  )
}

beforeEach(() => {
  executeMswServer()
})

test('Rendering elements', () => {
  UI.registerFooterTabs()
  render(
    <SegmentContext.Provider value={{segment: props.segment}}>
      <SegmentFooter />
    </SegmentContext.Provider>,
  )

  expect(screen.getByTestId('matches')).toBeInTheDocument()
  expect(screen.getByTestId('concordances')).toBeInTheDocument()
  expect(screen.getByTestId('glossary')).toBeInTheDocument()
  expect(screen.getByTestId('alternatives')).toBeInTheDocument()
  expect(screen.getByTestId('alternatives')).toHaveClass('hide')
})

test('Add tab', () => {
  const multiMatchLangs = {primary: 'it-IT'}
  UI.registerFooterTabs()
  render(
    <SegmentContext.Provider value={{segment: props.segment, multiMatchLangs}}>
      <SegmentFooter />
    </SegmentContext.Provider>,
  )

  expect(screen.getByTestId('matches')).toBeInTheDocument()
  expect(screen.getByTestId('concordances')).toBeInTheDocument()
  expect(screen.getByTestId('glossary')).toBeInTheDocument()
  expect(screen.getByTestId('multiMatches')).toBeInTheDocument()
})

test('Remove tab', () => {
  const multiMatchLangs = undefined
  UI.registerFooterTabs()
  render(
    <SegmentContext.Provider value={{segment: props.segment, multiMatchLangs}}>
      <SegmentFooter />
    </SegmentContext.Provider>,
  )

  expect(screen.getByTestId('matches')).toBeInTheDocument()
  expect(screen.getByTestId('concordances')).toBeInTheDocument()
  expect(screen.getByTestId('glossary')).toBeInTheDocument()
  expect(screen.queryByTestId('multiMatches')).toBeNull()
})

xtest('Translation Matches count result', () => {
  UI.registerFooterTabs()
  config.id_client = 'xxx'
  render(
    <SegmentContext.Provider
      value={{
        segment: props.segment,
        clientConnected: true,
        clientId: config.id_client,
      }}
    >
      <SegmentFooter />
    </SegmentContext.Provider>,
  )

  expect(screen.getByText('(1)')).toBeInTheDocument()
})

xtest('Translation conflicts (alternatives)', () => {
  UI.registerFooterTabs()
  const modifiedProps = {
    ...props,
    segment: {
      ...props.segment,
      alternatives: JSON.parse(
        `{"editable":[{"translation":"L'expérience elle-même doit donner aux clients un accès privilégié à des lieux ou à des choses qu'ils ne pourraient pas trouver par eux-mêmes. test","TOT":"1","involved_id":["11450636"]}],"not_editable":[],"prop_available":1}`,
      ),
    },
  }
  render(
    <SegmentContext.Provider value={{segment: modifiedProps.segment}}>
      <SegmentFooter />
    </SegmentContext.Provider>,
  )

  expect(screen.getByTestId('matches')).toBeInTheDocument()
  expect(screen.getByTestId('concordances')).toBeInTheDocument()
  expect(screen.getByTestId('glossary')).toBeInTheDocument()
  expect(screen.getByTestId('alternatives')).toBeInTheDocument()
  expect(screen.getByTestId('alternatives')).toHaveClass('active')
})

xtest('Click tab', async () => {
  UI.registerFooterTabs()
  render(
    <SegmentContext.Provider value={{segment: props.segment}}>
      <SegmentFooter />
    </SegmentContext.Provider>,
  )

  await act(
    async () => await userEvent.click(screen.getByTestId('concordances')),
  )
  expect(screen.getByTestId('concordances')).toHaveClass('active')
})

xtest('Move to next tab with keyboard shortcut', async () => {
  UI.registerFooterTabs()
  render(
    <SegmentContext.Provider value={{segment: props.segment}}>
      <SegmentFooter />
    </SegmentContext.Provider>,
  )

  fireEvent.keyDown(document, {altKey: true, code: 'KeyS', keyCode: 83})
  fireEvent.keyDown(document, {altKey: true, code: 'KeyS', keyCode: 83})

  await waitFor(() => {
    expect(screen.getByTestId('glossary')).toHaveClass('active')
  })

  fireEvent.keyDown(document, {altKey: true, code: 'KeyS', keyCode: 83})

  await waitFor(() => {
    expect(screen.getByTestId('matches')).toHaveClass('active')
  })
})
