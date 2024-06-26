import React from 'react'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {AdvancedOptionsTab} from './AdvancedOptionsTab'
import {render, screen, within} from '@testing-library/react'
import projectTemplatesMock from '../../../../../../../mocks/projectTemplateMock'
import {SCHEMA_KEYS} from '../../../../hooks/useProjectTemplates'
import mockLanguages from '../../../../../../../mocks/languagesMock'

global.config = {
  basepath: 'http://localhost/',
  enableMultiDomainApi: false,
  ajaxDomainsNumber: 20,
  isLoggedIn: 1,
  ownerIsMe: true,
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
    'en-bg': 'English - Bulgarian',
    'en-bs': 'English - Bosnian',
    'en-ar': 'English - Arabic',
    'en-ca': 'English - Catalan',
    'en-zh': 'English - Chinese',
    'en-he': 'English - Hebrew',
    'en-hr': 'English - Croatian',
    'en-id': 'English - Indonesian',
    'en-is': 'English - Icelandic',
    'en-ko': 'English - Korean',
    'en-lv': 'English - Latvian',
    'en-mk': 'English - Macedonian',
    'en-ms': 'English - Malay',
    'en-mt': 'English - Maltese',
    'en-nb': 'English - Norwegian Bokmål',
    'en-nn': 'English - Norwegian Nynorsk',
    'en-ro': 'English - Romanian',
    'en-sl': 'English - Slovenian',
    'en-sq': 'English - Albanian',
    'en-sr': 'English - Montenegrin',
    'en-th': 'English - Thai',
    'en-tr': 'English - Turkish',
    'en-uk': 'English - Ukrainian',
    'en-vi': 'English - Vietnamese',
    'de-it': 'German - Italian',
    'de-fr': 'German - French',
    'de-cs': 'German - Czech',
    'fr-it': 'French - Italian',
    'fr-nl': 'French - Dutch',
    'it-es': 'Italian - Spanish',
    'da-sv': 'Danish - Swedish',
    'nl-pt': 'Dutch - Portuguese',
    'nl-fi': 'Dutch - Finnish',
    'zh-en': 'Chinese - English',
    'sv-da': 'Swedish - Danish',
    'cs-de': 'Czech - German',
  },
  lexiqa_languages: [
    'af-ZA',
    'sq-AL',
    'ar-SA',
    'hy-AM',
    'as-IN',
    'az-AZ',
    'fr-BE',
    'bn-IN',
    'be-BY',
    'bs-BA',
    'bg-BG',
    'my-MM',
    'ca-ES',
    'zh-CN',
    'zh-TW',
    'zh-HK',
    'hr-HR',
    'cs-CZ',
    'da-DK',
    'nl-NL',
    'en-GB',
    'en-US',
    'en-AU',
    'en-CA',
    'en-IN',
    'en-IE',
    'en-NZ',
    'en-SG',
    'et-EE',
    'fi-FI',
    'fr-FR',
    'fr-CA',
    'fr-CH',
    'de-DE',
    'ka-GE',
    'el-GR',
    'gu-IN',
    'hi-IN',
    'hu-HU',
    'id-ID',
    'it-IT',
    'ja-JP',
    'jv-ID',
    'ha-NG',
    'he-IL',
    'ht-HT',
    'kk-KZ',
    'rn-BI',
    'ko-KR',
    'ky-KG',
    'lv-LV',
    'lt-LT',
    'mk-MK',
    'ms-MY',
    'mr-IN',
    'ne-NP',
    'nb-NO',
    'fa-IR',
    'pl-PL',
    'pt-PT',
    'pt-BR',
    'ro-RO',
    'ru-RU',
    'sr-Latn-RS',
    'sr-Cyrl-RS',
    'si-LK',
    'sk-SK',
    'sl-SI',
    'es-ES',
    'es-CO',
    'es-MX',
    'es-US',
    'es-419',
    'sw-KE',
    'sv-SE',
    'de-CH',
    'tl-PH',
    'ta-LK',
    'ta-IN',
    'th-TH',
    'tr-TR',
    'uk-UA',
    'ur-PK',
    'uz-UZ',
    'vi-VN',
  ],
  lxq_license: 'P0A3Ol5Me44PMBC5skeA56BcMDkRrapomJF5pEu5',
}

jest.mock('../../../../stores/ApplicationStore', () => ({
  getLanguages: () => mockLanguages,
  getLanguageNameFromLocale: () => '',
}))

const originalUI = global.UI
global.UI = {
  currentSegmentId: '',
}

beforeEach(() => {
  config.show_tag_projection = 1
})

afterAll(() => (global.UI = originalUI))

const projectTemplatesMockProxy = projectTemplatesMock.items.map(
  (template) =>
    new Proxy(template, {get: (target, prop) => target[SCHEMA_KEYS[prop]]}),
)
const contextValues = {
  modifyingCurrentTemplate: () => {},
  currentProjectTemplate: projectTemplatesMockProxy[1],
  projectTemplates: projectTemplatesMockProxy,
  sourceLang: {
    code: 'en-GB',
    name: 'English',
    direction: 'ltr',
    id: 'en-GB',
  },
  targetLangs: [
    {
      code: 'it-IT',
      name: 'Italian',
      direction: 'ltr',
      id: 'it-IT',
    },
  ],
}

test('Render properly', () => {
  render(
    <SettingsPanelContext.Provider
      value={{
        ...contextValues,
        currentProjectTemplate: {
          ...contextValues.currentProjectTemplate,
          speech2text: false,
          tagProjection: true,
          lexica: true,
          crossLanguageMatches: {
            primary: 'fr-FR',
            secondary: 'el-GR',
          },
        },
      }}
    >
      <AdvancedOptionsTab />
    </SettingsPanelContext.Provider>,
  )

  expect(screen.getByTestId('switch-speechtotext')).not.toBeChecked()
  expect(screen.getByTestId('switch-guesstag')).toBeChecked()
  expect(screen.getByTestId('switch-lexiqa')).toBeChecked()

  const crossLanguagesMatches = within(
    screen.getByTestId('container-crosslanguagesmatches'),
  )
  expect(crossLanguagesMatches.getByText('French')).toBeInTheDocument()
  expect(crossLanguagesMatches.getByText('Greek')).toBeInTheDocument()
})

test('Not showing guess tag', () => {
  config.show_tag_projection = 0
  render(
    <SettingsPanelContext.Provider value={contextValues}>
      <AdvancedOptionsTab />
    </SettingsPanelContext.Provider>,
  )

  expect(screen.queryByTestId('switch-guesstag')).not.toBeInTheDocument()
})

test('Guess tag not available for...', () => {
  render(
    <SettingsPanelContext.Provider
      value={{
        ...contextValues,
        sourceLang: {
          code: 'af-ZA',
          name: 'Afrikaans',
          direction: 'ltr',
          id: 'af-ZA',
        },
      }}
    >
      <AdvancedOptionsTab />
    </SettingsPanelContext.Provider>,
  )

  expect(screen.getByTestId('switch-guesstag')).not.toBeChecked()
})

test('Lexiqa not available for...', () => {
  render(
    <SettingsPanelContext.Provider
      value={{
        ...contextValues,
        sourceLang: {
          code: 'ace-ID',
          name: 'Acehnese',
          direction: 'ltr',
          id: 'ace-ID',
        },
      }}
    >
      <AdvancedOptionsTab />
    </SettingsPanelContext.Provider>,
  )

  expect(screen.getByTestId('switch-lexiqa')).not.toBeChecked()
})

test('Lexiqa not available for... (target lang)', () => {
  render(
    <SettingsPanelContext.Provider
      value={{
        ...contextValues,
        targetLangs: [
          {
            code: 'ln-LIN',
            name: 'Lingala',
            direction: 'ltr',
            id: 'ln-LIN',
          },
        ],
      }}
    >
      <AdvancedOptionsTab />
    </SettingsPanelContext.Provider>,
  )

  expect(screen.getByTestId('switch-lexiqa')).not.toBeChecked()
})

test('Cattool page', () => {
  config.is_cattool = true
  config.isOpenAiEnabled = true

  render(
    <SettingsPanelContext.Provider
      value={{
        ...contextValues,
      }}
    >
      <AdvancedOptionsTab />
    </SettingsPanelContext.Provider>,
  )

  expect(screen.getByTestId('switch-chars-counter')).toBeInTheDocument()
  expect(screen.getByTestId('switch-ai-assistant')).toBeInTheDocument()
  expect(screen.queryByTestId('container-team')).not.toBeInTheDocument()
})
