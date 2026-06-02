import React from 'react'
import {render, screen, fireEvent, waitFor} from '@testing-library/react'

import {ApplicationWrapperContext} from '../components/common/ApplicationWrapper/ApplicationWrapperContext'
import ModalsActions from '../actions/ModalsActions'
import useDeviceCompatibility from '../hooks/useDeviceCompatibility'
import useProjectTemplates from '../hooks/useProjectTemplates'
import useTemplates from '../hooks/useTemplates'
import {getSupportedFiles} from '../api/getSupportedFiles'
import {getSupportedLanguages} from '../api/getSupportedLanguages'
import {getTmKeysUser} from '../api/getTmKeysUser'
import {getMTEngines} from '../api/getMTEngines'
import NewProject from './NewProject'

jest.mock('./mountPage', () => {
  // jest.mock factories are hoisted before ES6 imports, so this runs before
  // NewProject.js module-level code that reads config.*
  global.config = Object.assign(global.config ?? {}, {
    subject_array: [{key: 'general', display: 'General'}],
    conversionEnabled: true,
    formats_number: 10,
    googleDriveEnabled: true,
    isLoggedIn: false,
  })
  return {mountPage: jest.fn()}
})

jest.mock('../hooks/usePortal', () =>
  jest.fn(
    () =>
      ({children}) =>
        children,
  ),
)
jest.mock('../hooks/useDeviceCompatibility', () => jest.fn())
jest.mock('../hooks/useProjectTemplates', () => ({
  __esModule: true,
  default: jest.fn(),
  SCHEMA_KEYS: {
    tm: 'tm',
    getPublicMatches: 'getPublicMatches',
    publicTmPenalty: 'publicTmPenalty',
    pretranslate100: 'pretranslate100',
    pretranslate75: 'pretranslate75',
    pretranslate50: 'pretranslate50',
    pretranslateUnmatched: 'pretranslateUnmatched',
    mt: 'mt',
    lightbulbThreshold: 'lightbulbThreshold',
    qaModelTemplateId: 'qaModelTemplateId',
    payableRateTemplateId: 'payableRateTemplateId',
    filtersTemplateId: 'filtersTemplateId',
    XliffConfigTemplateId: 'XliffConfigTemplateId',
  },
}))
jest.mock('../hooks/useTemplates', () => jest.fn())

jest.mock('../components/header/Header', () => () => <div>header</div>)
jest.mock('../components/common/Select', () => ({
  Select: ({label}) => <div>{label}</div>,
}))
jest.mock('../components/modals/AlertModal', () => () => <div>alert-modal</div>)
jest.mock('../components/modals/SupportedFilesModal', () => () => (
  <div>supported-files-modal</div>
))
jest.mock('../components/footer/Footer', () => () => <div>footer</div>)
jest.mock('../components/languageSelector/LanguageSelector', () => {
  const MockLanguageSelector = () => <div>language-selector</div>
  return {
    __esModule: true,
    default: MockLanguageSelector,
    setRecentlyUsedLanguages: jest.fn(),
  }
})
jest.mock('../components/createProject/TargetLanguagesSelect', () => ({
  TargetLanguagesSelect: () => <div>target-languages-select</div>,
}))
jest.mock('../components/createProject/TmGlossarySelect', () => ({
  TmGlossarySelect: () => <div>tm-glossary-select</div>,
}))
jest.mock('../components/createProject/SourceLanguageSelect', () => ({
  SourceLanguageSelect: () => <div>source-language-select</div>,
}))
jest.mock('../components/settingsPanel', () => ({
  SettingsPanel: () => <div>settings-panel</div>,
}))
jest.mock('../components/settingsPanel/ProjectTemplate/TemplateSelect', () => ({
  TemplateSelect: ({label}) => <div>{label}</div>,
}))
jest.mock('../components/createProject/HomePageSection', () => ({
  HomePageSection: () => <div>home-page-section</div>,
}))
jest.mock('../components/header/OnboardingTooltips', () => ({
  ONBOARDING_PAGE: {HOME: 'home'},
  OnboardingTooltips: () => <div>onboarding-tooltips</div>,
}))
jest.mock('../components/createProject/UploadFile', () => ({
  UploadFile: () => <div>upload-file</div>,
}))
jest.mock('../components/common/Button/Button', () => ({
  BUTTON_SIZE: {BIG: 'big'},
  BUTTON_TYPE: {PRIMARY: 'primary'},
  Button: ({children, ...props}) => <button {...props}>{children}</button>,
}))

jest.mock('../actions/ModalsActions', () => ({
  showModalComponent: jest.fn(),
  openLoginModal: jest.fn(),
  openRegisterModal: jest.fn(),
}))
jest.mock('../actions/ApplicationActions', () => ({
  setLanguages: jest.fn(),
}))
jest.mock('../actions/UserActions', () => ({
  setTeamInStorage: jest.fn(),
}))
jest.mock('../actions/CreateProjectActions', () => ({
  updateProjectTemplates: jest.fn(),
  updateProjectParams: jest.fn(),
}))

jest.mock('../api/getTmKeysUser', () => ({
  getTmKeysUser: jest.fn(),
}))
jest.mock('../api/getMTEngines', () => ({
  getMTEngines: jest.fn(),
}))
jest.mock('../api/getSupportedFiles', () => ({
  getSupportedFiles: jest.fn(),
}))
jest.mock('../api/getSupportedLanguages', () => ({
  getSupportedLanguages: jest.fn(),
}))
jest.mock('../api/getMMTKeys/getMMTKeys', () => ({
  getMMTKeys: jest.fn(() => Promise.resolve([])),
}))
jest.mock('../api/getDeepLGlosssaries/getDeepLGlosssaries', () => ({
  getDeepLGlosssaries: jest.fn(() => Promise.resolve({glossaries: []})),
}))
jest.mock('../api/createProject', () => ({
  createProject: jest.fn(),
}))
jest.mock('../api/tmCreateRandUser', () => ({
  tmCreateRandUser: jest.fn(() => Promise.resolve({data: {key: 'tm-key'}})),
}))

jest.mock('../stores/CreateProjectStore', () => ({
  addListener: jest.fn(),
  removeListener: jest.fn(),
  getSourceLang: jest.fn(() => 'en-US'),
}))
jest.mock('../utils/commonUtils', () => ({
  getParameterByName: jest.fn(() => null),
  removeParam: jest.fn(),
}))
jest.mock('../sse/SocketListener', () => () => <div>socket-listener</div>)

global.config = {
  subject_array: [{key: 'general', display: 'General'}],
  conversionEnabled: true,
  formats_number: 10,
  googleDriveEnabled: true,
  isLoggedIn: false,
}

if (!document.querySelector('header.upload-page-header')) {
  const header = document.createElement('header')
  header.className = 'upload-page-header'
  document.body.appendChild(header)
}

const defaultTemplate = {
  subject: 'general',
  sourceLanguage: 'en-US',
  targetLanguage: ['fr-FR'],
  mt: {id: null, extra: {}},
  tm: [],
  pretranslate100: false,
  pretranslate101: false,
  segmentationRule: {id: '1'},
  idTeam: 1,
  getPublicMatches: 0,
  publicTmPenalty: 0,
  qaModelTemplateId: 1,
  payableRateTemplateId: 1,
  XliffConfigTemplateId: 1,
  tmPrioritization: false,
  characterCounterCountTags: false,
  characterCounterMode: 'source',
  dialectStrict: false,
  mtQualityValueInEditor: 0,
  subfilteringHandlers: [],
  icuEnabled: false,
}

const renderComponent = ({
  isUserLogged = false,
  isDeviceCompatible = true,
  currentProjectTemplate = defaultTemplate,
} = {}) => {
  useDeviceCompatibility.mockReturnValue(isDeviceCompatible)
  useProjectTemplates.mockReturnValue({
    projectTemplates: [{...defaultTemplate, isSelected: true}],
    currentProjectTemplate,
    setProjectTemplates: jest.fn(),
    modifyingCurrentTemplate: jest.fn(),
    checkSpecificTemplatePropsAreModified: jest.fn(),
    isLoadingTemplates: false,
  })
  useTemplates.mockReturnValue({templates: []})
  getSupportedFiles.mockResolvedValue([])
  getSupportedLanguages.mockResolvedValue([
    {code: 'en-US', name: 'English'},
    {code: 'fr-FR', name: 'French'},
    {code: 'it-IT', name: 'Italian'},
  ])
  getTmKeysUser.mockResolvedValue({tm_keys: []})
  getMTEngines.mockResolvedValue([])

  return render(
    <ApplicationWrapperContext.Provider
      value={{
        isUserLogged,
        userInfo: {
          user: {uid: 1},
          teams: [{id: 1, name: 'Personal'}],
        },
      }}
    >
      <NewProject />
    </ApplicationWrapperContext.Provider>,
  )
}

beforeEach(() => {
  jest.clearAllMocks()
})

describe('NewProject', () => {
  test('renders unsupported-device message when device is not compatible', () => {
    renderComponent({isDeviceCompatible: false})

    expect(
      screen.getByText('Use Matecat from your desktop'),
    ).toBeInTheDocument()
    expect(screen.getByText('Find out more about Matecat')).toBeInTheDocument()
  })

  test('renders main page with disabled analyze button by default', async () => {
    renderComponent({isUserLogged: false, isDeviceCompatible: true})

    expect(
      screen.getByRole('heading', {name: 'The CAT tool that works for you'}),
    ).toBeInTheDocument()
    expect(screen.getByRole('button', {name: /analyze/i})).toBeDisabled()

    await waitFor(() => {
      expect(getSupportedFiles).toHaveBeenCalled()
    })
  })

  test('shows warning modal when swapping with multiple target languages', async () => {
    renderComponent({
      isUserLogged: true,
      isDeviceCompatible: true,
      currentProjectTemplate: {
        ...defaultTemplate,
        targetLanguage: ['fr-FR', 'it-IT'],
      },
    })

    await waitFor(() => {
      expect(getSupportedLanguages).toHaveBeenCalled()
    })

    fireEvent.click(screen.getByTitle('Swap languages'))

    expect(ModalsActions.showModalComponent).toHaveBeenCalled()
  })
})
