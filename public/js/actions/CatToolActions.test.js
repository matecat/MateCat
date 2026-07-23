jest.mock('../stores/AppDispatcher', () => ({
  dispatch: jest.fn(),
  register: jest.fn(),
}))

jest.mock('../stores/CatToolStore', () => ({
  getCurrentSegment: jest.fn(),
  getMatecatEventsEnabled: jest.fn(() => false),
  getFirstLoad: jest.fn(),
  getJobTmKeys: jest.fn(),
  getKeysDomains: jest.fn(),
  getHaveKeysGlossary: jest.fn(),
  isClientConnected: jest.fn(),
  jobMetadata: undefined,
}))

jest.mock('../stores/SegmentStore', () => ({
  getCurrentSegmentId: jest.fn(),
  getSegmentsArray: jest.fn(() => []),
  nextUntranslatedFromServer: null,
}))

jest.mock('./ModalsActions', () => ({
  showModalComponent: jest.fn(),
  onCloseModal: jest.fn(),
}))

jest.mock('./SegmentActions', () => ({}))

jest.mock('../utils/offlineUtils', () => ({
  failedConnection: jest.fn(),
  startOfflineMode: jest.fn(),
}))

// API imports (only those actually imported by CatToolActions.js)
jest.mock('../api/getJobStatistics', () => ({getJobStatistics: jest.fn()}))
jest.mock('../api/sendRevisionFeedback', () => ({
  sendRevisionFeedback: jest.fn(),
}))
jest.mock('../api/getTmKeysJob', () => ({getTmKeysJob: jest.fn()}))
jest.mock('../api/getDomainsList', () => ({getDomainsList: jest.fn()}))
jest.mock('../api/checkJobKeysHaveGlossary', () => ({
  checkJobKeysHaveGlossary: jest.fn(),
}))
jest.mock('../api/getJobMetadata', () => ({getJobMetadata: jest.fn()}))
jest.mock('../api/getGlobalWarnings', () => ({getGlobalWarnings: jest.fn()}))

jest.mock('../components/modals/AlertModal', () => 'AlertModal')
jest.mock(
  '../components/modals/RevisionFeedbackModal',
  () => 'RevisionFeedbackModal',
)
jest.mock(
  '../components/modals/ConfirmMessageModal',
  () => 'ConfirmMessageModal',
)

jest.mock('../constants/ModalKeys', () => ({
  MODAL_KEY: {
    ALERT: 'Alert',
    COPY_SOURCE: 'CopySource',
    REVISION_FEEDBACK: 'RevisionFeedback',
    CONFIRM_MESSAGE: 'ConfirmMessage',
  },
  COPY_SOURCE_COOKIE: 'copySourceCookie',
}))
jest.mock('../constants/CatToolConstants', () => ({
  SHOW_CONTAINER: 'SHOW_CONTAINER',
  TOGGLE_CONTAINER: 'TOGGLE_CONTAINER',
  CLOSE_SUBHEADER: 'CLOSE_SUBHEADER',
  SET_SEGMENT_FILTER: 'SET_SEGMENT_FILTER',
  CLOSE_SEARCH: 'CLOSE_SEARCH',
  RELOAD_SEGMENT_FILTER: 'RELOAD_SEGMENT_FILTER',
  STORE_FILES_INFO: 'STORE_FILES_INFO',
  SET_PROGRESS: 'SET_PROGRESS',
  UPDATE_QR: 'UPDATE_QR',
  RELOAD_QR: 'RELOAD_QR',
  STORE_SEARCH_RESULT: 'STORE_SEARCH_RESULT',
  CLIENT_CONNECT: 'CLIENT_CONNECT',
  CLIENT_RECONNECTION: 'CLIENT_RECONNECTION',
  ADD_NOTIFICATION: 'ADD_NOTIFICATION',
  REMOVE_NOTIFICATION: 'REMOVE_NOTIFICATION',
  REMOVE_ALL_NOTIFICATION: 'REMOVE_ALL_NOTIFICATION',
  ON_RENDER: 'ON_RENDER',
  UPDATE_TM_KEYS: 'UPDATE_TM_KEYS',
  UPDATE_DOMAINS: 'UPDATE_DOMAINS',
  HAVE_KEYS_GLOSSARY: 'HAVE_KEYS_GLOSSARY',
  OPEN_SETTINGS_PANEL: 'OPEN_SETTINGS_PANEL',
  GET_JOB_METADATA: 'GET_JOB_METADATA',
  SEGMENT_FILTER_ERROR: 'SEGMENT_FILTER_ERROR',
  SET_FIRST_LOAD: 'SET_FIRST_LOAD',
}))
jest.mock('lodash', () => ({
  isUndefined: (v) => typeof v === 'undefined',
}))
jest.mock('../utils/commonUtils', () => ({
  dispatchCustomEvent: jest.fn(),
  addInSessionStorage: jest.fn(),
  parsedHash: {},
  getLastSegmentFromLocalStorage: jest.fn(),
}))

import CatToolActions from './CatToolActions'
import ModalsActions from './ModalsActions'
import AppDispatcher from '../stores/AppDispatcher'
import CatToolStore from '../stores/CatToolStore'
import SegmentStore from '../stores/SegmentStore'
import CommonUtils from '../utils/commonUtils'
import OfflineUtils from '../utils/offlineUtils'
import {getJobStatistics} from '../api/getJobStatistics'
import {sendRevisionFeedback} from '../api/sendRevisionFeedback'
import {getTmKeysJob} from '../api/getTmKeysJob'
import {getDomainsList} from '../api/getDomainsList'
import {checkJobKeysHaveGlossary} from '../api/checkJobKeysHaveGlossary'
import {getJobMetadata} from '../api/getJobMetadata'
import {getGlobalWarnings} from '../api/getGlobalWarnings'

afterEach(() => {
  jest.restoreAllMocks()
})

describe('CatToolActions.processErrors', () => {
  beforeEach(() => {
    global.config = {id_job: 2}
    jest.clearAllMocks()
  })

  test('shows "Segment disabled" modal with refresh callback for -5 on setTranslation', () => {
    const onRenderSpy = jest
      .spyOn(CatToolActions, 'onRender')
      .mockImplementation(() => {})

    CatToolActions.processErrors([{code: '-5'}], 'setTranslation')

    expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
      'Alert',
      expect.objectContaining({
        text: 'This segment has been disabled by the project owner.<br />Refresh the page to update segment status.',
        buttonText: 'Refresh page',
        successCallback: expect.any(Function),
      }),
      'Segment disabled',
    )

    const modalProps = ModalsActions.showModalComponent.mock.calls[0][1]
    modalProps.successCallback()
    expect(onRenderSpy).toHaveBeenCalledTimes(1)
  })

  test('shows generic error modal when code is not -5 and not -10 on setTranslation', () => {
    CatToolActions.processErrors([{code: '-1'}], 'setTranslation')

    expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
      'Alert',
      expect.objectContaining({
        text: expect.stringContaining('Error in saving the translation'),
      }),
      'Error',
    )
  })

  test('does NOT show generic error modal when code is -10 on setTranslation', () => {
    CatToolActions.processErrors([{code: '-10'}], 'setTranslation')

    // Should not have been called with the generic error text
    const calls = ModalsActions.showModalComponent.mock.calls
    const genericErrorCall = calls.find(
      (call) =>
        call[2] === 'Error' &&
        call[1]?.text?.includes('Error in saving the translation'),
    )
    expect(genericErrorCall).toBeUndefined()
  })

  test('shows "Job canceled" modal when code is -10 and operation is not getSegments', () => {
    CatToolActions.processErrors([{code: '-10'}], 'setSuggestion')

    expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
      'Alert',
      expect.objectContaining({
        text: 'Job canceled or assigned to another translator',
        successCallback: expect.any(Function),
      }),
      'Error',
    )
  })

  test('starts offline mode for code -1000 and -101', () => {
    jest.spyOn(console, 'log').mockImplementation(() => {})

    CatToolActions.processErrors([{code: '-1000'}, {code: '-101'}], 'other')

    expect(OfflineUtils.startOfflineMode).toHaveBeenCalledTimes(2)
  })

  test('shows alert with error.message for code -2000', () => {
    CatToolActions.processErrors(
      [{code: '-2000', message: 'Cannot edit ICE segment'}],
      'other',
    )

    expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
      'Alert',
      {text: 'Cannot edit ICE segment'},
      'Error',
    )
  })

  test('does nothing when errors is not an array', () => {
    CatToolActions.processErrors(undefined, 'setTranslation')

    expect(ModalsActions.showModalComponent).not.toHaveBeenCalled()
  })
})

describe('CatToolActions simple dispatch methods', () => {
  beforeEach(() => {
    global.config = {id_job: 2}
    jest.clearAllMocks()
  })

  test('setFirstLoad dispatches SET_FIRST_LOAD', () => {
    CatToolActions.setFirstLoad(true)
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'SET_FIRST_LOAD',
      value: true,
    })
  })

  test('openSegmentFilter dispatches SHOW_CONTAINER', () => {
    CatToolActions.openSegmentFilter()
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'SHOW_CONTAINER',
      container: 'segmentFilter',
    })
  })

  test('setSegmentFilter dispatches SET_SEGMENT_FILTER', () => {
    CatToolActions.setSegmentFilter(['s1'], 'active')
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'SET_SEGMENT_FILTER',
      data: ['s1'],
      state: 'active',
    })
  })

  test('reloadSegmentFilter dispatches RELOAD_SEGMENT_FILTER', () => {
    CatToolActions.reloadSegmentFilter()
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'RELOAD_SEGMENT_FILTER',
    })
  })

  test('toggleQaIssues dispatches TOGGLE_CONTAINER for qaComponent', () => {
    CatToolActions.toggleQaIssues()
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'TOGGLE_CONTAINER',
      container: 'qaComponent',
    })
  })

  test('toggleSearch dispatches TOGGLE_CONTAINER for search', () => {
    CatToolActions.toggleSearch()
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'TOGGLE_CONTAINER',
      container: 'search',
    })
  })

  test('storeSearchResults dispatches STORE_SEARCH_RESULT', () => {
    CatToolActions.storeSearchResults({found: 1})
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'STORE_SEARCH_RESULT',
      data: {found: 1},
    })
  })

  test('closeSubHeader dispatches CLOSE_SUBHEADER', () => {
    CatToolActions.closeSubHeader()
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'CLOSE_SUBHEADER',
    })
  })

  test('closeSearch dispatches CLOSE_SEARCH and triggers a resize event', () => {
    jest.useFakeTimers()
    const resizeSpy = jest.fn()
    window.addEventListener('resize', resizeSpy)

    CatToolActions.closeSearch()
    jest.runAllTimers()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'CLOSE_SEARCH',
    })
    expect(resizeSpy).toHaveBeenCalled()
    window.removeEventListener('resize', resizeSpy)
    jest.useRealTimers()
  })

  test('clientConnected dispatches CLIENT_CONNECT', () => {
    CatToolActions.clientConnected('client-1')
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'CLIENT_CONNECT',
      clientId: 'client-1',
    })
  })

  test('clientReconnect dispatches CLIENT_RECONNECTION', () => {
    CatToolActions.clientReconnect()
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'CLIENT_RECONNECTION',
    })
  })

  test('storeFilesInfo dispatches STORE_FILES_INFO and updates config', () => {
    CatToolActions.storeFilesInfo(['f1'], 1, 10)
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'STORE_FILES_INFO',
      files: ['f1'],
    })
    expect(config.last_job_segment).toBe(10)
    expect(config.firstSegmentOfFiles).toEqual(['f1'])
  })

  test('setProgress dispatches SET_PROGRESS using data.stats when present', () => {
    CatToolActions.setProgress({stats: {done: 1}})
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'SET_PROGRESS',
      stats: {done: 1},
    })
  })

  test('setProgress dispatches SET_PROGRESS using data itself otherwise', () => {
    CatToolActions.setProgress({done: 2})
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'SET_PROGRESS',
      stats: {done: 2},
    })
  })

  test('updateFooterStatistics fetches stats and calls setProgress', async () => {
    getJobStatistics.mockResolvedValueOnce({stats: {done: 3}})
    const setProgressSpy = jest.spyOn(CatToolActions, 'setProgress')

    CatToolActions.updateFooterStatistics()
    await Promise.resolve()

    expect(getJobStatistics).toHaveBeenCalledWith(2, undefined)
    expect(setProgressSpy).toHaveBeenCalledWith({stats: {done: 3}})
  })

  test('updateFooterStatistics does nothing when there is no data', async () => {
    getJobStatistics.mockResolvedValueOnce(undefined)
    const setProgressSpy = jest.spyOn(CatToolActions, 'setProgress')

    CatToolActions.updateFooterStatistics()
    await Promise.resolve()

    expect(setProgressSpy).not.toHaveBeenCalled()
  })

  test('openFeedbackModal shows revision feedback modal', () => {
    CatToolActions.openFeedbackModal('great job', 2)

    expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
      'RevisionFeedback',
      expect.objectContaining({feedback: 'great job', revisionNumber: 2}),
      'Feedback submission',
    )

    const props = ModalsActions.showModalComponent.mock.calls[0][1]
    props.onCloseCallback()
    expect(CommonUtils.addInSessionStorage).toHaveBeenCalledWith(
      'feedback-modal',
      1,
      'feedback-modal',
    )
    props.successCallback()
  })

  test('sendRevisionFeedback calls the api with config values', () => {
    global.config = {
      id_job: 2,
      revisionNumber: 1,
      review_password: 'pwd',
    }
    CatToolActions.sendRevisionFeedback('nice')

    expect(sendRevisionFeedback).toHaveBeenCalledWith(2, 1, 'pwd', 'nice')
  })

  test('reloadQualityReport dispatches RELOAD_QR', () => {
    CatToolActions.reloadQualityReport()
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'RELOAD_QR',
    })
  })

  test('updateQualityReport dispatches UPDATE_QR', () => {
    CatToolActions.updateQualityReport({id: 1})
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'UPDATE_QR',
      qr: {id: 1},
    })
  })

  test('setDomains dispatches UPDATE_DOMAINS', () => {
    CatToolActions.setDomains({entries: ['d1'], sid: 5})
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'UPDATE_DOMAINS',
      sid: 5,
      entries: ['d1'],
    })
  })

  test('onTMKeysChangeStatus forces retrieveJobKeys', () => {
    const spy = jest
      .spyOn(CatToolActions, 'retrieveJobKeys')
      .mockImplementation(() => {})
    CatToolActions.onTMKeysChangeStatus()
    expect(spy).toHaveBeenCalledWith(true)
  })

  test('setHaveKeysGlossary dispatches HAVE_KEYS_GLOSSARY', () => {
    CatToolActions.setHaveKeysGlossary(true)
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'HAVE_KEYS_GLOSSARY',
      value: true,
    })
  })

  test('openSettingsPanel dispatches OPEN_SETTINGS_PANEL', () => {
    CatToolActions.openSettingsPanel(true)
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'OPEN_SETTINGS_PANEL',
      value: true,
    })
  })

  test('setSegmentFilterError dispatches SEGMENT_FILTER_ERROR', () => {
    CatToolActions.setSegmentFilterError()
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'SEGMENT_FILTER_ERROR',
    })
  })
})

describe('CatToolActions.retrieveJobKeys', () => {
  beforeEach(() => {
    global.config = {id_job: 2}
    jest.clearAllMocks()
  })

  test('fetches and dispatches tm keys when none are cached', async () => {
    CatToolStore.getJobTmKeys.mockReturnValueOnce(undefined)
    CatToolStore.getKeysDomains.mockReturnValueOnce(undefined)
    CatToolStore.getHaveKeysGlossary.mockReturnValueOnce(undefined)
    CatToolStore.isClientConnected.mockReturnValueOnce(true)
    getTmKeysJob.mockResolvedValueOnce({
      tm_keys: [
        {key: 'k1', is_private: false},
        {key: 'k2', is_private: true},
      ],
    })
    getDomainsList.mockResolvedValueOnce({})

    CatToolActions.retrieveJobKeys()
    await Promise.resolve()
    await Promise.resolve()

    expect(getDomainsList).toHaveBeenCalledWith({keys: ['k1']})
    expect(checkJobKeysHaveGlossary).toHaveBeenCalled()
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({actionType: 'UPDATE_TM_KEYS'}),
    )
  })

  test('dispatches cached tm keys, domains and glossary flag when already present', () => {
    CatToolStore.getJobTmKeys.mockReturnValueOnce(['cached'])
    CatToolStore.getKeysDomains.mockReturnValueOnce(['domain1'])
    CatToolStore.getHaveKeysGlossary.mockReturnValueOnce(true)
    CatToolStore.isClientConnected.mockReturnValueOnce(true)

    CatToolActions.retrieveJobKeys()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'UPDATE_TM_KEYS',
      keys: ['cached'],
    })
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'UPDATE_DOMAINS',
      entries: ['domain1'],
    })
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'HAVE_KEYS_GLOSSARY',
      value: true,
      wasAlreadyVerified: true,
    })
  })

  test('does nothing when client is not connected', () => {
    CatToolStore.isClientConnected.mockReturnValueOnce(false)

    CatToolActions.retrieveJobKeys()

    expect(AppDispatcher.dispatch).not.toHaveBeenCalled()
  })
})

describe('CatToolActions.onRender', () => {
  beforeEach(() => {
    global.config = {id_job: 2, first_job_segment: 1}
    jest.clearAllMocks()
    CommonUtils.parsedHash = {}
    CommonUtils.getLastSegmentFromLocalStorage.mockReturnValue(undefined)
  })

  test('uses props.segmentToOpen as startSegmentId when provided', () => {
    CatToolStore.getFirstLoad.mockReturnValueOnce(false)

    CatToolActions.onRender({segmentToOpen: 42})

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({
        actionType: 'ON_RENDER',
        startSegmentId: 42,
        openCurrentSegmentAfter: false,
        where: 'center',
      }),
    )
  })

  test('falls back to hash or last segment when no segmentToOpen is given', () => {
    CatToolStore.getFirstLoad.mockReturnValueOnce(true)
    CommonUtils.getLastSegmentFromLocalStorage.mockReturnValue(7)

    CatToolActions.onRender({where: 'right'})

    expect(config.last_opened_segment).toBe(7)
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({
        actionType: 'ON_RENDER',
        startSegmentId: 7,
        where: 'right',
      }),
    )
  })

  test('uses default props when called without arguments', () => {
    CatToolStore.getFirstLoad.mockReturnValueOnce(false)

    CatToolActions.onRender()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({actionType: 'ON_RENDER', where: 'center'}),
    )
  })
})

describe('CatToolActions.getJobMetadata', () => {
  beforeEach(() => {
    global.config = {id_job: 2}
    jest.clearAllMocks()
    CatToolStore.jobMetadata = undefined
  })

  test('fetches metadata when not already cached', async () => {
    getJobMetadata.mockResolvedValueOnce({title: 'meta'})

    CatToolActions.getJobMetadata({idJob: 1, password: 'pwd'})
    await Promise.resolve()

    expect(getJobMetadata).toHaveBeenCalledWith(1, 'pwd')
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'GET_JOB_METADATA',
      jobMetadata: {title: 'meta'},
    })
    expect(CatToolStore.jobMetadata).toEqual({title: 'meta'})
  })

  test('reuses cached metadata when already present', () => {
    CatToolStore.jobMetadata = {title: 'cached'}

    CatToolActions.getJobMetadata({idJob: 1, password: 'pwd'})

    expect(getJobMetadata).not.toHaveBeenCalled()
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'GET_JOB_METADATA',
      jobMetadata: {title: 'cached'},
    })
  })
})

describe('CatToolActions.showLaraQuotaExceeded', () => {
  beforeEach(() => {
    global.config = {id_job: 2}
    jest.clearAllMocks()
    sessionStorage.clear()
  })

  test('shows modal for owner with upgrade callback', () => {
    global.config.ownerIsMe = true
    window.open = jest.fn()

    CatToolActions.showLaraQuotaExceeded()

    expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
      'ConfirmMessage',
      expect.objectContaining({successText: 'Upgrade your plan'}),
      'Lara Free Plan Limit Reached',
    )
    const props = ModalsActions.showModalComponent.mock.calls[0][1]
    props.successCallback()
    expect(window.open).toHaveBeenCalledWith(
      'https://laratranslate.com/pricing',
      '_blank',
    )
    props.onCloseCallback()
    expect(sessionStorage.getItem('lara_quote_exceed2')).toBe('true')
  })

  test('shows modal for non-owner without upgrade callback', () => {
    global.config.ownerIsMe = false

    CatToolActions.showLaraQuotaExceeded()

    expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
      'ConfirmMessage',
      expect.objectContaining({successText: null, successCallback: null}),
      'Lara Free Plan Limit Reached',
    )
  })

  test('does not show modal again when already dismissed', () => {
    global.config.ownerIsMe = false
    sessionStorage.setItem('lara_quote_exceed2', 'true')

    CatToolActions.showLaraQuotaExceeded()

    expect(ModalsActions.showModalComponent).not.toHaveBeenCalled()
  })
})

describe('CatToolActions.startWarning / checkWarnings', () => {
  beforeEach(() => {
    global.config = {id_job: 2, password: 'pwd', warningPollingInterval: 1000}
    jest.clearAllMocks()
    jest.useFakeTimers()
  })

  afterEach(() => {
    jest.useRealTimers()
  })

  test('startWarning schedules checkWarnings when tab is visible', () => {
    Object.defineProperty(document, 'visibilityState', {
      value: 'visible',
      configurable: true,
    })
    const checkWarningsSpy = jest
      .spyOn(CatToolActions, 'checkWarnings')
      .mockImplementation(() => {})

    CatToolActions.startWarning()
    jest.advanceTimersByTime(1000)

    expect(checkWarningsSpy).toHaveBeenCalledWith(false)
  })

  test('startWarning reschedules itself when tab is hidden', () => {
    Object.defineProperty(document, 'visibilityState', {
      value: 'hidden',
      configurable: true,
    })
    const startWarningSpy = jest.spyOn(CatToolActions, 'startWarning')

    CatToolActions.startWarning()
    jest.advanceTimersByTime(1000)

    expect(startWarningSpy).toHaveBeenCalledTimes(2)
  })

  test('checkWarnings dispatches global warnings and success event on success', async () => {
    Object.defineProperty(document, 'visibilityState', {
      value: 'visible',
      configurable: true,
    })
    getGlobalWarnings.mockResolvedValueOnce({details: {tag: ['1']}})

    CatToolActions.checkWarnings()
    await Promise.resolve()
    await Promise.resolve()

    expect(getGlobalWarnings).toHaveBeenCalledWith({id_job: 2, password: 'pwd'})
    expect(CommonUtils.dispatchCustomEvent).toHaveBeenCalledWith(
      'getWarning:global:success',
    )
  })

  test('checkWarnings calls OfflineUtils on failure', async () => {
    getGlobalWarnings.mockRejectedValueOnce(new Error('fail'))

    CatToolActions.checkWarnings()
    await Promise.resolve()
    await Promise.resolve()

    expect(OfflineUtils.failedConnection).toHaveBeenCalled()
  })
})
