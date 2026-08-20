import React from 'react'
import {act, fireEvent, render, screen, waitFor} from '@testing-library/react'
import {ApplicationWrapperContext} from '../components/common/ApplicationWrapper/ApplicationWrapperContext'
import CatToolStore from '../stores/CatToolStore'
import SegmentStore from '../stores/SegmentStore'
import CatToolConstants from '../constants/CatToolConstants'
import SegmentConstants from '../constants/SegmentConstants'
import useProjectTemplates from '../hooks/useProjectTemplates'
import useSegmentsLoader from '../hooks/useSegmentsLoader'
import {useHotkeys} from 'react-hotkeys-hook'
import SegmentActions from '../actions/SegmentActions'
import CatToolActions from '../actions/CatToolActions'
import CommentsActions from '../actions/CommentsActions'
import ModalsActions from '../actions/ModalsActions'
import OfflineUtils from '../utils/offlineUtils'
import CommonUtils from '../utils/commonUtils'
import Speech2Text from '../utils/speech2text'
import LXQ from '../utils/lxq.main'
import ContextPreviewChannel from '../utils/contextPreviewChannel'
import {extractSegmentContextFields} from '../utils/contextPreviewUtils'
import {getTmKeysJob} from '../api/getTmKeysJob'
import {getTmKeysUser} from '../api/getTmKeysUser'
import {getMTEngines} from '../api/getMTEngines'
import {getSupportedLanguages} from '../api/getSupportedLanguages'

// Must be first: factory is hoisted before ES6 imports, so this sets global.config
// before any module-level code in CatTool.js is evaluated.
jest.mock('./mountPage', () => {
  global.config = {
    id_job: 42,
    id_project: 1,
    password: 'pass',
    review_password: '',
    source_code: 'en-US',
    target_code: 'it-IT',
    isReview: false,
    revisionNumber: 1,
    project_name: 'Test Project',
    project_completion_feature_enabled: false,
    secondRevisionsCount: 0,
    overall_quality_class: '',
    quality_report_href: '',
    allow_link_to_analysis: false,
    analysis_enabled: false,
    isGDriveProject: false,
    footer_show_revise_link: false,
    first_job_segment: '1',
    active_engine: null,
    ownerIsMe: false,
    get_public_matches: true,
    isCJK: false,
  }
  return {mountPage: jest.fn()}
})

jest.mock('./CatToolInterface', () => ({
  CatToolInterface: class {
    getCharacterCounterMode() {
      return undefined
    }
  },
}))

jest.mock('react-hotkeys-hook', () => ({useHotkeys: jest.fn()}))

jest.mock('../hooks/useProjectTemplates', () => ({
  __esModule: true,
  default: jest.fn(),
}))
jest.mock('../hooks/useSegmentsLoader', () => ({
  __esModule: true,
  default: jest.fn(),
}))
jest.mock('../hooks/useResizable', () => ({
  __esModule: true,
  default: jest.fn(() => ({
    height: 500,
    isDragging: false,
    handleMouseDown: jest.fn(),
  })),
}))
jest.mock('../hooks/usePortal', () =>
  jest.fn(
    () =>
      ({children}) =>
        children,
  ),
)

jest.mock('../constants/CatToolConstants', () => ({
  ON_RENDER: 'ON_RENDER',
  OPEN_SETTINGS_PANEL: 'OPEN_SETTINGS_PANEL',
  GET_JOB_METADATA: 'GET_JOB_METADATA',
  SET_PROGRESS: 'SET_PROGRESS',
}))
jest.mock('../constants/SegmentConstants', () => ({
  FREEZING_SEGMENTS: 'FREEZING_SEGMENTS',
  GET_MORE_SEGMENTS: 'GET_MORE_SEGMENTS',
  RENDER_SEGMENTS: 'RENDER_SEGMENTS',
}))

jest.mock('../stores/CatToolStore', () => {
  const {EventEmitter} = require('events')
  const store = new EventEmitter()
  store.setMaxListeners(0)
  store.getFirstLoad = jest.fn(() => false)
  store.setCurrentProjectTemplate = jest.fn()
  return store
})
jest.mock('../stores/SegmentStore', () => {
  const {EventEmitter} = require('events')
  const store = new EventEmitter()
  store.setMaxListeners(0)
  store.getLastSegmentId = jest.fn(() => null)
  store.getFirstSegmentId = jest.fn(() => null)
  store.getCurrentSegmentId = jest.fn(() => null)
  store.getCurrentSegment = jest.fn(() => null)
  store.getAllSegments = jest.fn(() => [])
  store.getSegmentById = jest.fn(() => null)
  return store
})
jest.mock('../stores/ApplicationStore', () => ({
  getLanguageNameFromLocale: jest.fn((code) => code),
  setLanguages: jest.fn(),
}))

jest.mock('../actions/CatToolActions', () => ({
  openSettingsPanel: jest.fn(),
  onRender: jest.fn(),
  getJobMetadata: jest.fn(),
  updateFooterStatistics: jest.fn(),
  setFirstLoad: jest.fn(),
  checkWarnings: jest.fn(),
  processErrors: jest.fn(),
  toggleQaIssues: jest.fn(),
}))
jest.mock('../actions/SegmentActions', () => ({
  openSegment: jest.fn(),
  addPreloadedIssuesToSegment: jest.fn(),
  changeCharactersCounterRules: jest.fn(),
}))
jest.mock('../actions/CommentsActions', () => ({
  openCommentsMenu: jest.fn(),
}))
jest.mock('../actions/ModalsActions', () => ({
  showModalComponent: jest.fn(),
}))

jest.mock('../api/getTmKeysUser', () => ({
  getTmKeysUser: jest.fn(() => Promise.resolve({tm_keys: []})),
}))
jest.mock('../api/getTmKeysJob', () => ({
  getTmKeysJob: jest.fn(() => Promise.resolve({tm_keys: []})),
}))
jest.mock('../api/getMTEngines', () => ({
  getMTEngines: jest.fn(() => Promise.resolve([])),
}))
jest.mock('../api/getSupportedLanguages', () => ({
  getSupportedLanguages: jest.fn(() => Promise.resolve([])),
}))

jest.mock('../utils/offlineUtils', () => ({
  failedConnection: jest.fn(),
}))
jest.mock('../utils/lxq.main', () => ({
  enabled: jest.fn(() => false),
  init: jest.fn(),
}))
jest.mock('../utils/speech2text', () => ({
  __esModule: true,
  default: {enabled: jest.fn(() => false), init: jest.fn()},
}))
jest.mock('../utils/commonUtils', () => ({
  __esModule: true,
  default: {
    getParameterByName: jest.fn(() => null),
    removeParam: jest.fn(),
    setBrowserHistoryBehavior: jest.fn(),
    dispatchCustomEvent: jest.fn(),
    goodbye: jest.fn(),
  },
}))
jest.mock('../utils/contextPreviewChannel', () => ({
  __esModule: true,
  default: {
    onMessage: jest.fn(() => jest.fn()),
    sendMessage: jest.fn(),
  },
}))
jest.mock('../utils/contextPreviewUtils', () => ({
  extractSegmentContextFields: jest.fn(() => ({})),
}))
jest.mock('../utils/charsSizeCounterUtil', () => ({
  CHARS_SIZE_COUNTER_TYPES: {},
  charsSizeCounter: {map: undefined},
}))
jest.mock('../utils/shortcuts', () => ({
  Shortcuts: {
    shortCutsKeyType: 'standard',
    cattol: {
      events: {
        openSettings: {keystrokes: {standard: 'ctrl+,'}},
        toggleContextPreview: {keystrokes: {standard: 'ctrl+shift+p'}},
      },
    },
  },
}))

jest.mock('../components/segments/utils/DraftMatecatUtils/tagModel', () => ({
  initTagSignature: jest.fn(),
}))

jest.mock('../components/header/cattol/Header', () => ({
  Header: (props) => {
    global.__headerProps = props
    return <div data-testid="header" />
  },
}))
jest.mock('../components/segments/SegmentsContainer', () => () => (
  <div data-testid="segments-container" />
))
jest.mock('../components/settingsPanel', () => ({
  SETTINGS_PANEL_TABS: {editorSettings: 'editorSettings'},
  SettingsPanel: () => <div data-testid="settings-panel" />,
}))
jest.mock('../components/settingsPanel/SettingsPanelConstants', () => ({
  DEFAULT_ENGINE_MEMORY: {id: 0, name: 'No engine'},
}))
jest.mock('../components/footer/CattoolFooter', () => ({
  CattoolFooter: () => <div data-testid="cattool-footer" />,
}))
jest.mock('../components/header/OnboardingTooltips', () => ({
  ONBOARDING_PAGE: {CATTOOL: 'cattool'},
  OnboardingTooltips: () => <div data-testid="onboarding-tooltips" />,
}))
jest.mock('../sse/SocketListener', () => () => (
  <div data-testid="socket-listener" />
))
jest.mock('../components/modals/FatalErrorModal', () => 'FatalErrorModal')
jest.mock('../../img/icons/IconRedirect', () => () => null)
jest.mock('../../img/icons/IconDown', () => () => null)
jest.mock('../components/common/Button/Button', () => ({
  BUTTON_MODE: {GHOST: 'ghost'},
  BUTTON_SIZE: {SMALL: 'small'},
  Button: ({children, onClick, title}) => (
    <button onClick={onClick} title={title}>
      {children}
    </button>
  ),
}))
jest.mock('jquery', () =>
  jest.fn(() => ({trigger: jest.fn(), addClass: jest.fn()})),
)

import CatTool from './CatTool'
import '../extensions/extensionManifest'
import {
  registerExtension,
  resetExtensionOverrides,
} from '../extensions/extensionPoints'
import {SEGMENT_FOOTER_TABS} from '../extensions/extensionPointNames'

const registerFooterTabsExtension = jest.fn()

// Fake temporary project template that makes isFakeCurrentTemplateReady truthy
const fakeTemporaryTemplate = {
  isTemporary: true,
  tm: [],
  mt: undefined,
  mandatoryIssues: [],
}

const mockModifyingCurrentTemplate = jest.fn()

const defaultContextValue = {
  isUserLogged: true,
  userInfo: {user: {uid: 1}, metadata: {}},
}

const renderCatTool = (contextOverrides = {}) =>
  render(
    <ApplicationWrapperContext.Provider
      value={{...defaultContextValue, ...contextOverrides}}
    >
      <CatTool />
    </ApplicationWrapperContext.Provider>,
  )

describe('CatTool', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    resetExtensionOverrides()
    registerExtension(SEGMENT_FOOTER_TABS, registerFooterTabsExtension)
    CatToolStore.removeAllListeners()
    SegmentStore.removeAllListeners()
    useProjectTemplates.mockReturnValue({
      projectTemplates: [fakeTemporaryTemplate],
      currentProjectTemplate: fakeTemporaryTemplate,
      modifyingCurrentTemplate: mockModifyingCurrentTemplate,
    })
    useSegmentsLoader.mockReturnValue({isLoading: false, result: null})
  })

  describe('structure', () => {
    test('renders Header', async () => {
      await act(async () => renderCatTool())
      expect(screen.getByTestId('header')).toBeInTheDocument()

      await act(async () => {
        global.__headerProps.openTmPanel()
      })
    })

    test('renders SocketListener', async () => {
      await act(async () => renderCatTool())
      expect(screen.getByTestId('socket-listener')).toBeInTheDocument()
    })

    test('renders main-container', async () => {
      const {container} = await act(async () => renderCatTool())
      expect(container.querySelector('.main-container')).toBeInTheDocument()
    })
  })

  describe('auth-dependent rendering', () => {
    test('renders article#file with SegmentsContainer when user is logged in', async () => {
      await act(async () => renderCatTool({isUserLogged: true}))
      expect(screen.getByTestId('segments-container')).toBeInTheDocument()
      expect(document.getElementById('file')).toBeInTheDocument()
    })

    test('renders signin-bg when user is not logged in and userInfo is undefined', async () => {
      const {container} = await act(async () =>
        renderCatTool({isUserLogged: false, userInfo: undefined}),
      )
      expect(container.querySelector('.signin-bg')).toBeInTheDocument()
      expect(screen.queryByTestId('segments-container')).not.toBeInTheDocument()
    })

    test('does not render signin-bg when user is logged in', async () => {
      const {container} = await act(async () =>
        renderCatTool({isUserLogged: true}),
      )
      expect(container.querySelector('.signin-bg')).not.toBeInTheDocument()
    })
  })

  describe('loading state', () => {
    test('#outer has loading class when isLoadingSegments is true', async () => {
      useSegmentsLoader.mockReturnValue({isLoading: true, result: null})
      await act(async () => renderCatTool())
      expect(document.getElementById('outer')).toHaveClass('loading')
    })

    test('#outer has no loading class when isLoadingSegments is false', async () => {
      useSegmentsLoader.mockReturnValue({isLoading: false, result: null})
      await act(async () => renderCatTool())
      expect(document.getElementById('outer')).not.toHaveClass('loading')
    })

    test('#outer has loadingBefore class when loading segments before', async () => {
      useSegmentsLoader.mockReturnValue({isLoading: true, result: null})
      await act(async () => renderCatTool())
      await act(async () => {
        SegmentStore.emit(SegmentConstants.GET_MORE_SEGMENTS, 'before')
      })
      expect(document.getElementById('outer')).toHaveClass('loadingBefore')
    })
  })

  describe('freezing overlay', () => {
    test('shows freezing-overlay when FREEZING_SEGMENTS emits true', async () => {
      const {container} = await act(async () => renderCatTool())
      await act(async () => {
        SegmentStore.emit(SegmentConstants.FREEZING_SEGMENTS, true)
      })
      expect(container.querySelector('.freezing-overlay')).toBeInTheDocument()
    })

    test('hides freezing-overlay when FREEZING_SEGMENTS emits false', async () => {
      const {container} = await act(async () => renderCatTool())
      await act(async () => {
        SegmentStore.emit(SegmentConstants.FREEZING_SEGMENTS, true)
      })
      await act(async () => {
        SegmentStore.emit(SegmentConstants.FREEZING_SEGMENTS, false)
      })
      expect(
        container.querySelector('.freezing-overlay'),
      ).not.toBeInTheDocument()
    })
  })

  describe('settings panel', () => {
    test('settings panel is closed by default', async () => {
      await act(async () => renderCatTool())
      expect(screen.queryByTestId('settings-panel')).not.toBeInTheDocument()
    })

    test('OPEN_SETTINGS_PANEL event opens settings panel', async () => {
      await act(async () => renderCatTool())
      await act(async () => {
        CatToolStore.emit(CatToolConstants.OPEN_SETTINGS_PANEL, {
          value: 'editorSettings',
        })
      })
      expect(screen.getByTestId('settings-panel')).toBeInTheDocument()
    })
  })

  describe('footer', () => {
    test('renders CattoolFooter when user is logged in and not loading', async () => {
      useSegmentsLoader.mockReturnValue({isLoading: false, result: null})
      await act(async () => renderCatTool({isUserLogged: true}))
      expect(screen.getByTestId('cattool-footer')).toBeInTheDocument()
    })

    test('does not render CattoolFooter when user is not logged in', async () => {
      await act(async () =>
        renderCatTool({isUserLogged: false, userInfo: undefined}),
      )
      expect(screen.queryByTestId('cattool-footer')).not.toBeInTheDocument()
    })

    test('renders CattoolFooter when segments are loading', async () => {
      useSegmentsLoader.mockReturnValue({isLoading: true, result: null})
      await act(async () => renderCatTool())
      expect(screen.getByTestId('cattool-footer')).toBeInTheDocument()
    })
  })

  describe('context preview', () => {
    test('shows collapsed context-preview tab by default', async () => {
      const {container} = await act(async () => renderCatTool())
      expect(
        container.querySelector('.context-preview__tab'),
      ).toBeInTheDocument()
      expect(
        container.querySelector('.context-preview-wrapper--collapsed'),
      ).toBeInTheDocument()
    })

    test('tab has "Visual context" label', async () => {
      await act(async () => renderCatTool())
      expect(screen.getAllByText('Visual context').length).toBeGreaterThan(0)
    })
  })

  describe('jobMetadata mandatory_issues mapping', () => {
    const makeJobMetadata = (mandatory_issues) => ({
      job: {
        tm_prioritization: false,
        character_counter_count_tags: false,
        character_counter_mode: null,
        subfiltering_handlers: [],
        mandatory_issues,
      },
      project: {
        mandatory_issues,
        mt_quality_value_in_editor: false,
        mt_extra: {},
        icu_enabled: 0,
      },
    })

    const emitJobMetadata = (metadata) =>
      CatToolStore.emit(CatToolConstants.GET_JOB_METADATA, {
        jobMetadata: metadata,
      })

    const getLastTemplateUpdater = () => {
      const calls = mockModifyingCurrentTemplate.mock.calls
      // Find the call whose updater result contains tmPrioritization (jobMetadata effect)
      for (let i = calls.length - 1; i >= 0; i--) {
        const result = calls[i][0]?.({})
        if (result && 'tmPrioritization' in result) return calls[i][0]
      }
      return null
    }

    test('spreads mandatory_issues array as mandatoryIssues in template update', async () => {
      await act(async () => renderCatTool())
      await act(async () => {
        emitJobMetadata(makeJobMetadata(['r1', 'r2']))
      })

      const updater = getLastTemplateUpdater()
      expect(updater).not.toBeNull()
      const result = updater({mandatoryIssues: []})
      expect(result.mandatoryIssues).toEqual(['r1', 'r2'])
    })

    test('defaults mandatoryIssues to r1/r2 when mandatory_issues is not an array', async () => {
      await act(async () => renderCatTool())
      await act(async () => {
        emitJobMetadata(makeJobMetadata(null))
      })

      const updater = getLastTemplateUpdater()
      expect(updater).not.toBeNull()
      const result = updater({})
      expect(result.mandatoryIssues).toEqual(['r1', 'r2'])
    })

    test('defaults mandatoryIssues to r1/r2 when mandatory_issues is undefined', async () => {
      await act(async () => renderCatTool())
      await act(async () => {
        emitJobMetadata(makeJobMetadata(undefined))
      })

      const updater = getLastTemplateUpdater()
      expect(updater).not.toBeNull()
      const result = updater({})
      expect(result.mandatoryIssues).toEqual(['r1', 'r2'])
    })

    test('spreads empty array when mandatory_issues is []', async () => {
      await act(async () => renderCatTool())
      await act(async () => {
        emitJobMetadata(makeJobMetadata([]))
      })

      const updater = getLastTemplateUpdater()
      expect(updater).not.toBeNull()
      const result = updater({})
      expect(result.mandatoryIssues).toEqual([])
    })

    test('includes other job metadata fields in the template update', async () => {
      await act(async () => renderCatTool())
      await act(async () => {
        emitJobMetadata(makeJobMetadata(['r1']))
      })

      const updater = getLastTemplateUpdater()
      const result = updater({})
      expect(result).toMatchObject({
        tmPrioritization: false,
        characterCounterCountTags: false,
        icuEnabled: false,
      })
    })
  })

  const flush = async () => {
    await act(async () => {
      await new Promise((resolve) => setTimeout(resolve, 0))
    })
  }

  describe('async data loading', () => {
    test('loads supported languages on mount', async () => {
      getSupportedLanguages.mockResolvedValueOnce([
        {code: 'en-US', name: 'English'},
      ])
      await act(async () => renderCatTool())
      await flush()
      expect(getSupportedLanguages).toHaveBeenCalled()
    })

    test('handles supported languages failure', async () => {
      getSupportedLanguages.mockRejectedValueOnce(new Error('nope'))
      await act(async () => renderCatTool())
      await flush()
      expect(getSupportedLanguages).toHaveBeenCalled()
    })

    test('merges and dedupes tm keys then applies template update', async () => {
      getTmKeysJob.mockResolvedValueOnce({
        tm_keys: [{key: 'k1', r: 1, w: 0, is_shared: false, name: 'Job TM'}],
      })
      getTmKeysUser.mockResolvedValueOnce({
        tm_keys: [
          {key: 'k1', r: 0, w: 1, is_shared: true, name: 'User TM'},
          {key: 'k2', r: 1, w: 1, is_shared: false, name: 'Second TM'},
        ],
      })
      await act(async () => renderCatTool())
      await flush()
      const updaterCall = mockModifyingCurrentTemplate.mock.calls.find((c) => {
        const r = c[0]?.({})
        return r && 'getPublicMatches' in r
      })
      expect(updaterCall).toBeTruthy()
      const result = updaterCall[0]({})
      expect(result.getPublicMatches).toBe(true)
    })

    test('fetches MT engines and applies active engine when owner', async () => {
      config.ownerIsMe = true
      config.active_engine = {id: 'mt-1', name: 'Engine'}
      getMTEngines.mockResolvedValueOnce([{id: 'mt-1', name: 'Engine'}])
      await act(async () => renderCatTool())
      await flush()
      expect(getMTEngines).toHaveBeenCalled()
      config.ownerIsMe = false
      config.active_engine = null
    })

    test('sets empty tm keys when tm keys request fails', async () => {
      getTmKeysJob.mockRejectedValueOnce(new Error('fail'))
      await act(async () => renderCatTool())
      await flush()
      expect(getTmKeysUser).toHaveBeenCalled()
    })
  })

  describe('query params', () => {
    test('opens comments menu when action is openComments', async () => {
      jest.useFakeTimers()
      CommonUtils.getParameterByName.mockReturnValueOnce('openComments')
      await act(async () => renderCatTool())
      act(() => {
        jest.advanceTimersByTime(600)
      })
      expect(CommentsActions.openCommentsMenu).toHaveBeenCalled()
      expect(CommonUtils.removeParam).toHaveBeenCalledWith('action')
      jest.useRealTimers()
    })

    test('toggles QA issues when action is warnings', async () => {
      jest.useFakeTimers()
      CommonUtils.getParameterByName.mockReturnValueOnce('warnings')
      await act(async () => renderCatTool())
      act(() => {
        jest.advanceTimersByTime(600)
      })
      expect(CatToolActions.toggleQaIssues).toHaveBeenCalled()
      jest.useRealTimers()
    })
  })

  describe('window unload', () => {
    test('onbeforeunload delegates to CommonUtils.goodbye', async () => {
      await act(async () => renderCatTool())
      const event = {}
      window.onbeforeunload(event)
      expect(CommonUtils.goodbye).toHaveBeenCalledWith(event)
    })
  })

  describe('metadata dependent init', () => {
    test('initializes Speech2Text and LXQ when enabled', async () => {
      Speech2Text.enabled.mockReturnValueOnce(true)
      LXQ.enabled.mockReturnValueOnce(true)
      await act(async () =>
        renderCatTool({
          userInfo: {user: {uid: 1}, metadata: {some: 'meta'}},
        }),
      )
      expect(Speech2Text.init).toHaveBeenCalled()
      expect(LXQ.init).toHaveBeenCalled()
    })
  })

  describe('character counter rules', () => {
    test('applies character counter rules from current template', async () => {
      const tmpl = {...fakeTemporaryTemplate, characterCounterMode: 'chars'}
      useProjectTemplates.mockReturnValue({
        projectTemplates: [tmpl],
        currentProjectTemplate: tmpl,
        modifyingCurrentTemplate: mockModifyingCurrentTemplate,
      })
      await act(async () => renderCatTool())
      expect(SegmentActions.changeCharactersCounterRules).toHaveBeenCalled()
    })
  })

  describe('context preview channel messages', () => {
    const getHandler = () => {
      const calls = ContextPreviewChannel.onMessage.mock.calls
      return calls[calls.length - 1][0]
    }

    test('segmentClicked opens the segment', async () => {
      await act(async () => renderCatTool())
      act(() => {
        getHandler()({type: 'segmentClicked', sid: 7})
      })
      expect(SegmentActions.openSegment).toHaveBeenCalledWith(7)
    })

    test('requestSegments sends segments and highlight', async () => {
      SegmentStore.getAllSegments.mockReturnValueOnce([
        {sid: 1, segment: 's', translation: 't', internal_id: 'i1'},
      ])
      SegmentStore.getCurrentSegmentId.mockReturnValueOnce(1)
      await act(async () => renderCatTool())
      act(() => {
        getHandler()({type: 'requestSegments'})
      })
      expect(ContextPreviewChannel.sendMessage).toHaveBeenCalledWith(
        expect.objectContaining({type: 'segments'}),
      )
      expect(ContextPreviewChannel.sendMessage).toHaveBeenCalledWith(
        expect.objectContaining({type: 'highlight'}),
      )
    })

    test('loadMoreSegments requests more segments', async () => {
      SegmentStore.getLastSegmentId.mockReturnValueOnce('99')
      await act(async () => renderCatTool())
      act(() => {
        getHandler()({type: 'loadMoreSegments', where: 'after'})
      })
      expect(SegmentStore.getLastSegmentId).toHaveBeenCalled()
    })
  })

  describe('segment store events', () => {
    test('RENDER_SEGMENTS without preview content hides preview', async () => {
      SegmentStore.getCurrentSegment.mockReturnValue({sid: 5})
      SegmentStore.getSegmentById.mockReturnValueOnce({toJS: () => ({sid: 5})})
      extractSegmentContextFields.mockReturnValueOnce({})
      await act(async () => renderCatTool())
      await act(async () => {
        SegmentStore.emit(SegmentConstants.RENDER_SEGMENTS)
      })
      expect(ContextPreviewChannel.sendMessage).toHaveBeenCalledWith(
        expect.objectContaining({type: 'highlight', sid: 5}),
      )
      SegmentStore.getCurrentSegment.mockReturnValue(null)
    })

    test('RENDER_SEGMENTS with preview content extracts context fields', async () => {
      SegmentStore.getCurrentSegment.mockReturnValue({sid: 8})
      SegmentStore.getSegmentById.mockReturnValueOnce(null)
      SegmentStore.getAllSegments.mockReturnValueOnce([{sid: 8}])
      extractSegmentContextFields.mockReturnValueOnce({context_url: 'http://x'})
      await act(async () => renderCatTool())
      await act(async () => {
        SegmentStore.emit(SegmentConstants.RENDER_SEGMENTS)
      })
      expect(extractSegmentContextFields).toHaveBeenCalled()
      SegmentStore.getCurrentSegment.mockReturnValue(null)
    })

    test('RENDER_SEGMENTS with no current segment returns early', async () => {
      SegmentStore.getCurrentSegment.mockReturnValue(null)
      await act(async () => renderCatTool())
      await act(async () => {
        SegmentStore.emit(SegmentConstants.RENDER_SEGMENTS)
      })
      expect(SegmentStore.getCurrentSegment).toHaveBeenCalled()
    })

    test('SET_PROGRESS with incomplete analysis shows modal', async () => {
      await act(async () => renderCatTool())
      await act(async () => {
        CatToolStore.emit(CatToolConstants.SET_PROGRESS, {
          analysis_complete: false,
        })
      })
      expect(ModalsActions.showModalComponent).toHaveBeenCalled()
    })

    test('SET_PROGRESS with complete analysis does not show modal', async () => {
      await act(async () => renderCatTool())
      await act(async () => {
        CatToolStore.emit(CatToolConstants.SET_PROGRESS, {
          analysis_complete: true,
        })
      })
      expect(ModalsActions.showModalComponent).not.toHaveBeenCalled()
    })
  })

  describe('hotkeys and preview interactions', () => {
    const getHotkeyCallback = (index) => useHotkeys.mock.calls[index][1]

    test('openSettings hotkey opens the settings panel', async () => {
      await act(async () => renderCatTool())
      act(() => {
        getHotkeyCallback(0)()
      })
      expect(CatToolActions.openSettingsPanel).toHaveBeenCalledWith(
        'editorSettings',
      )
    })

    test('toggleContextPreview hotkey expands the preview', async () => {
      const {container} = await act(async () => renderCatTool())
      act(() => {
        getHotkeyCallback(1)()
      })
      expect(
        container.querySelector('.context-preview-wrapper--collapsed'),
      ).not.toBeInTheDocument()
    })

    test('open in another tab opens a popup and toggling closes it', async () => {
      const popup = {closed: false, focus: jest.fn(), close: jest.fn()}
      const openSpy = jest.spyOn(window, 'open').mockReturnValue(popup)
      await act(async () => renderCatTool())
      act(() => {
        getHotkeyCallback(1)()
      })
      act(() => {
        fireEvent.click(screen.getByTitle('Open in another tab'))
      })
      expect(openSpy).toHaveBeenCalled()
      act(() => {
        getHotkeyCallback(1)()
      })
      expect(popup.close).toHaveBeenCalled()
      openSpy.mockRestore()
    })

    test('clicking the tab while popup is open reopens inline', async () => {
      const popup = {closed: false, focus: jest.fn(), close: jest.fn()}
      const openSpy = jest.spyOn(window, 'open').mockReturnValue(popup)
      const {container} = await act(async () => renderCatTool())
      act(() => {
        getHotkeyCallback(1)()
      })
      act(() => {
        fireEvent.click(screen.getByTitle('Open in another tab'))
      })
      act(() => {
        fireEvent.click(container.querySelector('.context-preview__tab'))
      })
      expect(popup.close).toHaveBeenCalled()
      openSpy.mockRestore()
    })

    test('popup polling detects a manually closed popup', async () => {
      jest.useFakeTimers()
      const popup = {closed: false, focus: jest.fn(), close: jest.fn()}
      const openSpy = jest.spyOn(window, 'open').mockReturnValue(popup)
      await act(async () => renderCatTool())
      act(() => {
        getHotkeyCallback(1)()
      })
      act(() => {
        fireEvent.click(screen.getByTitle('Open in another tab'))
      })
      popup.closed = true
      act(() => {
        jest.advanceTimersByTime(600)
      })
      openSpy.mockRestore()
      jest.useRealTimers()
    })
  })

  describe('segments result handling', () => {
    test('reports errors and offline state on error result', async () => {
      useSegmentsLoader.mockReturnValue({
        isLoading: false,
        result: {where: 'center', errors: true, length: 1},
      })
      await act(async () => renderCatTool())
      expect(OfflineUtils.failedConnection).toHaveBeenCalled()
      expect(CatToolActions.processErrors).toHaveBeenCalled()
    })

    test('processes center result and opens the segment', async () => {
      useSegmentsLoader.mockReturnValue({isLoading: false, result: null})
      await act(async () => renderCatTool())
      await act(async () => {
        CatToolStore.emit(CatToolConstants.ON_RENDER, {
          startSegmentId: '2-3',
          openCurrentSegmentAfter: true,
        })
      })
      useSegmentsLoader.mockReturnValue({
        isLoading: false,
        result: {
          where: 'center',
          data: {
            files: {
              f1: {
                segments: [
                  {sid: '2', segment: 's', translation: 't', internal_id: 1},
                ],
              },
            },
          },
        },
      })
      await act(async () => {
        CatToolStore.emit(CatToolConstants.ON_RENDER, {trigger: 1})
      })
      expect(SegmentActions.openSegment).toHaveBeenCalled()
      expect(registerFooterTabsExtension).toHaveBeenCalled()
    })

    test('falls back to first file segment when start segment is missing', async () => {
      useSegmentsLoader.mockReturnValue({isLoading: false, result: null})
      await act(async () => renderCatTool())
      await act(async () => {
        CatToolStore.emit(CatToolConstants.ON_RENDER, {startSegmentId: '999'})
      })
      useSegmentsLoader.mockReturnValue({
        isLoading: false,
        result: {
          where: 'center',
          data: {
            files: {
              f1: {
                segments: [
                  {sid: '1', segment: 's', translation: 't', internal_id: 1},
                ],
              },
            },
          },
        },
      })
      await act(async () => {
        CatToolStore.emit(CatToolConstants.ON_RENDER, {trigger: 2})
      })
      expect(SegmentActions.openSegment).toHaveBeenCalled()
    })

    test('processes more-segments result and dispatches custom event', async () => {
      config.isReview = true
      useSegmentsLoader.mockReturnValue({isLoading: false, result: null})
      await act(async () => renderCatTool())
      useSegmentsLoader.mockReturnValue({
        isLoading: false,
        result: {
          where: 'after',
          data: {
            files: {
              f1: {
                segments: [
                  {sid: '5', segment: 's', translation: 't', internal_id: 2},
                ],
              },
            },
          },
        },
      })
      await act(async () => {
        CatToolStore.emit(CatToolConstants.ON_RENDER, {trigger: 3})
      })
      expect(CommonUtils.dispatchCustomEvent).toHaveBeenCalled()
      expect(SegmentActions.addPreloadedIssuesToSegment).toHaveBeenCalled()
      config.isReview = false
    })

    test('runs callbackAfterSegmentsResponse when segments arrive', async () => {
      const cb = jest.fn()
      useSegmentsLoader.mockReturnValue({isLoading: false, result: null})
      await act(async () => renderCatTool())
      await act(async () => {
        CatToolStore.emit(CatToolConstants.ON_RENDER, {
          callbackAfterSegmentsResponse: cb,
        })
      })
      useSegmentsLoader.mockReturnValue({
        isLoading: false,
        result: {where: 'after', data: {files: {}}},
      })
      await act(async () => {
        CatToolStore.emit(CatToolConstants.ON_RENDER, {trigger: 4})
      })
      expect(cb).toHaveBeenCalled()
    })
  })

  describe('more segments loader', () => {
    test('requests segments after the last one', async () => {
      SegmentStore.getLastSegmentId.mockReturnValueOnce('50')
      await act(async () => renderCatTool())
      await act(async () => {
        SegmentStore.emit(SegmentConstants.GET_MORE_SEGMENTS, 'after')
      })
      expect(SegmentStore.getLastSegmentId).toHaveBeenCalled()
    })
  })
})
