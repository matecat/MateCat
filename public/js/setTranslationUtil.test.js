import {render} from '@testing-library/react'
import {HIDE_UNMODIFIED_FUZZY_MATCH_MODAL_STORAGE} from './components/modals/UnmodifiedFuzzyMatchModal'

jest.mock('./actions/SegmentActions', () => ({
  __esModule: true,
  default: {
    hideSegmentHeader: jest.fn(),
    modifiedTranslation: jest.fn(),
    addClassToSegment: jest.fn(),
    setSegmentSaving: jest.fn(),
    setChoosenSuggestion: jest.fn(),
    getSegmentVersionsIssues: jest.fn(),
    getTranslationMismatches: jest.fn(),
    setStatus: jest.fn(),
    removeClassToSegment: jest.fn(),
    propagateTranslation: jest.fn(),
    setSegmentPropagation: jest.fn(),
  },
}))

jest.mock('./utils/offlineUtils', () => ({
  __esModule: true,
  default: {
    offline: false,
    decrementOfflineCacheRemaining: jest.fn(),
    failedConnection: jest.fn(),
    changeStatusOffline: jest.fn(),
    checkConnection: jest.fn(),
    startOfflineMode: jest.fn(),
  },
}))

jest.mock('./api/setTranslation', () => ({
  setTranslation: jest.fn(),
}))

jest.mock('./actions/ModalsActions', () => ({
  __esModule: true,
  default: {
    showModalComponent: jest.fn(),
    onCloseModal: jest.fn(),
  },
}))

jest.mock('./actions/CatToolActions', () => ({
  __esModule: true,
  default: {
    reloadQualityReport: jest.fn(),
    checkWarnings: jest.fn(),
    setProgress: jest.fn(),
    removeAllNotifications: jest.fn(),
    addNotification: jest.fn(),
    processErrors: jest.fn(),
  },
}))

jest.mock('./utils/commonUtils', () => ({
  __esModule: true,
  default: {
    dispatchCustomEvent: jest.fn(),
  },
}))

jest.mock('./utils/segmentUtils', () => ({
  __esModule: true,
  default: {
    createSetTranslationRequest: jest.fn(() => ({fakeRequest: true})),
  },
}))

jest.mock('./stores/SegmentStore', () => ({
  __esModule: true,
  default: {
    setLastTranslatedSegmentId: jest.fn(),
  },
}))

const buildSegment = (overrides = {}) => ({
  sid: 1,
  propagable: false,
  status: 'DRAFT',
  modified: false,
  translation: 'hello',
  ...overrides,
})

describe('setTranslationUtil', () => {
  let SetTranslationUtil
  let SegmentActions
  let OfflineUtils
  let setTranslationApi
  let ModalsActions
  let CatToolActions
  let CommonUtils

  beforeEach(() => {
    jest.resetModules()
    localStorage.clear()
    global.config = {
      id_job: 2, // must match the id_job used at module-import time to compute
      // HIDE_UNMODIFIED_FUZZY_MATCH_MODAL_STORAGE (see setupFiles.jest.js)
      isReview: false,
      offlineModeEnabled: true,
      alternativesEnabled: false,
      status_labels: {NEW: 'NEW', DRAFT: 'DRAFT'},
    }

    SetTranslationUtil = require('./setTranslationUtil')
    SegmentActions = require('./actions/SegmentActions').default
    OfflineUtils = require('./utils/offlineUtils').default
    setTranslationApi = require('./api/setTranslation').setTranslation
    ModalsActions = require('./actions/ModalsActions').default
    CatToolActions = require('./actions/CatToolActions').default
    CommonUtils = require('./utils/commonUtils').default

    // Safe default: a pending promise so any test that reaches the network call
    // without caring about its resolution doesn't blow up on `undefined.then`.
    setTranslationApi.mockImplementation(() => new Promise(() => {}))
  })

  describe('segmentTranslation', () => {
    test('returns early when segment is falsy', () => {
      expect(
        SetTranslationUtil.segmentTranslation(null, 'DRAFT', jest.fn()),
      ).toBeUndefined()
      expect(SegmentActions.hideSegmentHeader).not.toHaveBeenCalled()
    })

    test('proceeds directly (no modal) for an unmodified draft segment', () => {
      const segment = buildSegment({status: 'DRAFT', modified: false})
      const callback = jest.fn()

      SetTranslationUtil.segmentTranslation(segment, 'DRAFT', callback, false)

      expect(SegmentActions.hideSegmentHeader).toHaveBeenCalledWith(1)
      // status is DRAFT so the modified class should be kept (not cleared)
      expect(SegmentActions.modifiedTranslation).not.toHaveBeenCalled()
      expect(ModalsActions.showModalComponent).not.toHaveBeenCalled()
    })

    test('clears modified class when confirming status is not draft/new', () => {
      const segment = buildSegment({status: 'TRANSLATED', modified: false})

      SetTranslationUtil.segmentTranslation(
        segment,
        'APPROVED2',
        jest.fn(),
        false,
      )

      expect(SegmentActions.modifiedTranslation).toHaveBeenCalledWith(1, false)
    })

    test('shows the unmodified-fuzzy-match modal and proceeds on confirmation', () => {
      const segment = buildSegment({
        status: 'DRAFT',
        modified: false,
        translation: 'fuzzy text',
        contributions: {matches: [{match: '85%', translation: 'fuzzy text'}]},
        choosenSuggestionIndex: 0,
      })

      SetTranslationUtil.segmentTranslation(
        segment,
        'TRANSLATED',
        jest.fn(),
        false,
      )

      expect(ModalsActions.showModalComponent).toHaveBeenCalledTimes(1)
      const [, props, title] = ModalsActions.showModalComponent.mock.calls[0]
      expect(title).toBe('Confirm fuzzy match')
      expect(SegmentActions.hideSegmentHeader).not.toHaveBeenCalled()

      // Simulate the user confirming through the modal.
      props.successCallback()

      expect(SegmentActions.hideSegmentHeader).toHaveBeenCalledWith(1)
    })

    test('skips the fuzzy-match warning when the dismissal flag is stored', () => {
      localStorage.setItem(HIDE_UNMODIFIED_FUZZY_MATCH_MODAL_STORAGE, '1')
      const segment = buildSegment({
        status: 'DRAFT',
        modified: false,
        translation: 'fuzzy text',
        contributions: {matches: [{match: '85%', translation: 'fuzzy text'}]},
        choosenSuggestionIndex: 0,
      })

      SetTranslationUtil.segmentTranslation(
        segment,
        'TRANSLATED',
        jest.fn(),
        false,
      )

      expect(ModalsActions.showModalComponent).not.toHaveBeenCalled()
      expect(SegmentActions.hideSegmentHeader).toHaveBeenCalledWith(1)
    })

    test('does not warn about fuzzy match when the suggestion was edited', () => {
      const segment = buildSegment({
        status: 'DRAFT',
        modified: false,
        translation: 'edited text',
        contributions: {matches: [{match: '85%', translation: 'fuzzy text'}]},
        choosenSuggestionIndex: 0,
      })

      SetTranslationUtil.segmentTranslation(
        segment,
        'TRANSLATED',
        jest.fn(),
        false,
      )

      expect(ModalsActions.showModalComponent).not.toHaveBeenCalled()
      expect(SegmentActions.hideSegmentHeader).toHaveBeenCalledWith(1)
    })

    test('shows propagation confirmation modal, "only this segment" branch keeps propagate false', () => {
      const segment = buildSegment({
        propagable: true,
        status: 'translated',
        modified: true,
      })

      SetTranslationUtil.segmentTranslation(segment, 'APPROVED', jest.fn())

      expect(ModalsActions.showModalComponent).toHaveBeenCalledTimes(1)
      const [component, props, title] =
        ModalsActions.showModalComponent.mock.calls[0]
      expect(title).toBe('Confirmation required ')
      expect(props.successText).toBe('Only this segment')
      expect(props.cancelText).toBe('Propagate to All')
      expect(component).toBeDefined()

      props.successCallback()

      expect(SegmentActions.hideSegmentHeader).toHaveBeenCalledWith(1)
      expect(ModalsActions.onCloseModal).toHaveBeenCalled()
    })

    test('shows propagation confirmation modal, "propagate to all" branch clears modal', () => {
      const segment = buildSegment({
        propagable: true,
        status: 'translated',
        modified: true,
      })

      SetTranslationUtil.segmentTranslation(segment, 'APPROVED', jest.fn())

      const [, props] = ModalsActions.showModalComponent.mock.calls[0]
      props.cancelCallback()

      expect(SegmentActions.hideSegmentHeader).toHaveBeenCalledWith(1)
      expect(ModalsActions.onCloseModal).toHaveBeenCalled()
    })
  })

  describe('setTranslationTail offline branch (via segmentTranslation)', () => {
    test('queues the translation and defers to offline handling while offline', () => {
      OfflineUtils.offline = true
      const callback = jest.fn()
      const segment = buildSegment()

      SetTranslationUtil.segmentTranslation(segment, 'DRAFT', callback, false)

      expect(SegmentActions.addClassToSegment).toHaveBeenCalledWith(
        1,
        'setTranslationPending',
      )
      expect(SegmentActions.setSegmentSaving).toHaveBeenCalledWith(1, true)
      expect(OfflineUtils.decrementOfflineCacheRemaining).toHaveBeenCalled()
      expect(OfflineUtils.failedConnection).toHaveBeenCalled()
      expect(OfflineUtils.changeStatusOffline).toHaveBeenCalledWith(1)
      expect(OfflineUtils.checkConnection).toHaveBeenCalled()
      expect(callback).toHaveBeenCalled()
      expect(setTranslationApi).not.toHaveBeenCalled()
      expect(SetTranslationUtil.isTranslationTailEmpty()).toBe(false)
    })

    test('does not decrement offline cache when translation is not newly saved (empty translation)', () => {
      OfflineUtils.offline = true
      const segment = buildSegment({translation: ''})

      SetTranslationUtil.segmentTranslation(segment, 'DRAFT', jest.fn(), false)

      // translationIsToSave is false (empty translation), so decrement/failedConnection are skipped
      expect(OfflineUtils.decrementOfflineCacheRemaining).not.toHaveBeenCalled()
      expect(OfflineUtils.failedConnection).not.toHaveBeenCalled()
      expect(OfflineUtils.changeStatusOffline).toHaveBeenCalledWith(1)
    })
  })

  describe('execSetTranslationTail online branch (via segmentTranslation)', () => {
    test('returns immediately when the queue is empty', () => {
      expect(SetTranslationUtil.execSetTranslationTail()).toBeUndefined()
      expect(setTranslationApi).not.toHaveBeenCalled()
    })

    test('processes the online success path, sets status/progress, and empties the tail', async () => {
      setTranslationApi.mockResolvedValue({data: 'OK', propagation: null})
      const segment = buildSegment()

      SetTranslationUtil.segmentTranslation(segment, 'DRAFT', jest.fn(), false)
      await Promise.resolve()
      await Promise.resolve()
      await Promise.resolve()

      expect(setTranslationApi).toHaveBeenCalledWith({fakeRequest: true})
      expect(SegmentActions.setChoosenSuggestion).toHaveBeenCalledWith(1, null)
      expect(SegmentActions.setSegmentSaving).toHaveBeenCalledWith(1, false)
      expect(SegmentActions.setStatus).toHaveBeenCalledWith(1, null, 'DRAFT')
      expect(CatToolActions.setProgress).toHaveBeenCalledWith({
        data: 'OK',
        propagation: null,
      })
      expect(SegmentActions.removeClassToSegment).toHaveBeenCalledWith(
        1,
        'setTranslationPending',
      )
      expect(CatToolActions.checkWarnings).toHaveBeenCalledWith(false)
      expect(CommonUtils.dispatchCustomEvent).toHaveBeenCalledWith(
        'setTranslation:success',
        {segment},
      )
      // no propagation data -> falls into the "else" branch of checkSegmentsPropagation
      expect(SegmentActions.setSegmentPropagation).toHaveBeenCalledWith(
        1,
        null,
        false,
      )
      expect(CatToolActions.reloadQualityReport).not.toHaveBeenCalled()
      expect(SegmentActions.getTranslationMismatches).not.toHaveBeenCalled()
      expect(SetTranslationUtil.isTranslationTailEmpty()).toBe(true)
    })

    test('does nothing further when the response data is not OK', async () => {
      setTranslationApi.mockResolvedValue({data: 'KO'})
      const segment = buildSegment()

      SetTranslationUtil.segmentTranslation(segment, 'DRAFT', jest.fn(), false)
      await Promise.resolve()
      await Promise.resolve()
      await Promise.resolve()

      expect(SegmentActions.setStatus).not.toHaveBeenCalled()
      expect(CatToolActions.setProgress).not.toHaveBeenCalled()
      expect(SegmentActions.setSegmentPropagation).not.toHaveBeenCalled()
    })

    test('triggers review-specific and alternatives side effects when enabled', async () => {
      global.config.isReview = true
      global.config.alternativesEnabled = true
      setTranslationApi.mockResolvedValue({data: 'OK', propagation: null})
      const segment = buildSegment()

      SetTranslationUtil.segmentTranslation(segment, 'DRAFT', jest.fn(), false)
      await Promise.resolve()
      await Promise.resolve()
      await Promise.resolve()

      expect(SegmentActions.getSegmentVersionsIssues).toHaveBeenCalledWith(1)
      expect(CatToolActions.reloadQualityReport).toHaveBeenCalled()
      expect(SegmentActions.getTranslationMismatches).toHaveBeenCalledWith(1)
    })

    test('propagates translation and notifies about excluded locked-segment repetitions', async () => {
      setTranslationApi.mockResolvedValue({
        data: 'OK',
        propagation: {
          propagated_ids: [2, 3],
          segments_for_propagation: {
            not_propagated: {ice: {id: [4]}, not_ice: {id: []}},
          },
        },
      })
      // status "translated" + modified -> goes through the confirmation modal, which is the
      // only path that can produce autoPropagate:false (needed for the notification branch).
      const segment = buildSegment({
        propagable: true,
        status: 'translated',
        modified: true,
      })

      SetTranslationUtil.segmentTranslation(segment, 'APPROVED', jest.fn())
      const [, props] = ModalsActions.showModalComponent.mock.calls[0]
      props.cancelCallback() // "Propagate to All" -> propagate:true, autoPropagate:false

      await Promise.resolve()
      await Promise.resolve()
      await Promise.resolve()

      expect(SegmentActions.propagateTranslation).toHaveBeenCalledWith(
        1,
        [2, 3],
        'APPROVED',
      )
      expect(CatToolActions.removeAllNotifications).toHaveBeenCalled()
      expect(CatToolActions.addNotification).toHaveBeenCalledWith(
        expect.objectContaining({title: 'Segment propagated', type: 'info'}),
      )
      const {container} = render(
        CatToolActions.addNotification.mock.calls[0][0].text,
      )
      expect(container.textContent).toMatch(
        /locked segments have been excluded/,
      )
      expect(container.textContent).not.toMatch(
        /non-locked segments have been excluded/,
      )
    })

    test('notifies with the non-ice-locked message when non-ice repetitions are excluded', async () => {
      setTranslationApi.mockResolvedValue({
        data: 'OK',
        propagation: {
          propagated_ids: [2],
          segments_for_propagation: {
            not_propagated: {ice: {id: []}, not_ice: {id: [5]}},
          },
        },
      })
      const segment = buildSegment({
        propagable: true,
        status: 'translated',
        modified: true,
      })

      SetTranslationUtil.segmentTranslation(segment, 'APPROVED', jest.fn())
      const [, props] = ModalsActions.showModalComponent.mock.calls[0]
      props.cancelCallback()

      await Promise.resolve()
      await Promise.resolve()
      await Promise.resolve()

      expect(CatToolActions.addNotification).toHaveBeenCalled()
      const {container} = render(
        CatToolActions.addNotification.mock.calls[0][0].text,
      )
      expect(container.textContent).toMatch(
        /non-locked segments have been excluded/,
      )
    })

    test('does not notify when autoPropagate is true even though propagated', async () => {
      setTranslationApi.mockResolvedValue({
        data: 'OK',
        propagation: {
          propagated_ids: [2],
          segments_for_propagation: {not_propagated: {}},
        },
      })
      // status/modified combo takes the direct (no-modal) path, which always sets autoPropagate:true
      const segment = buildSegment({
        propagable: true,
        status: 'draft',
        modified: false,
      })

      SetTranslationUtil.segmentTranslation(segment, 'DRAFT', jest.fn())
      await Promise.resolve()
      await Promise.resolve()
      await Promise.resolve()

      expect(ModalsActions.showModalComponent).not.toHaveBeenCalled()
      expect(SegmentActions.propagateTranslation).toHaveBeenCalledWith(
        1,
        [2],
        'DRAFT',
      )
      expect(CatToolActions.addNotification).not.toHaveBeenCalled()
    })

    test('re-queues the item and switches to offline mode on a network error', async () => {
      setTranslationApi.mockRejectedValue({errors: []})
      const segment = buildSegment()

      SetTranslationUtil.segmentTranslation(segment, 'DRAFT', jest.fn(), false)
      await Promise.resolve()
      await Promise.resolve()
      await Promise.resolve()

      expect(OfflineUtils.changeStatusOffline).toHaveBeenCalledWith(1)
      expect(OfflineUtils.startOfflineMode).toHaveBeenCalled()
      expect(SegmentActions.setSegmentSaving).toHaveBeenCalledWith(1, true)
      expect(CatToolActions.processErrors).not.toHaveBeenCalled()
      expect(SetTranslationUtil.isTranslationTailEmpty()).toBe(false)
    })

    test('reports errors through CatToolActions and does not re-queue when errors are present', async () => {
      setTranslationApi.mockRejectedValue({errors: [{message: 'boom'}]})
      const segment = buildSegment()

      SetTranslationUtil.segmentTranslation(segment, 'DRAFT', jest.fn(), false)
      await Promise.resolve()
      await Promise.resolve()
      await Promise.resolve()

      expect(CatToolActions.processErrors).toHaveBeenCalledWith(
        [{message: 'boom'}],
        'setTranslation',
      )
      expect(OfflineUtils.startOfflineMode).not.toHaveBeenCalled()
      expect(SetTranslationUtil.isTranslationTailEmpty()).toBe(true)
    })
  })

  describe('translationIsToSaveBeforeClose', () => {
    test('is true for a modified, non-empty, new/draft segment not already queued', () => {
      const segment = buildSegment({
        status: 'NEW',
        modified: true,
        translation: 'x',
      })
      expect(SetTranslationUtil.translationIsToSaveBeforeClose(segment)).toBe(
        true,
      )
    })

    test('is false when the translation is empty', () => {
      const segment = buildSegment({
        status: 'NEW',
        modified: true,
        translation: '',
      })
      expect(SetTranslationUtil.translationIsToSaveBeforeClose(segment)).toBe(
        false,
      )
    })

    test('is false when the segment was not modified', () => {
      const segment = buildSegment({
        status: 'NEW',
        modified: false,
        translation: 'x',
      })
      expect(SetTranslationUtil.translationIsToSaveBeforeClose(segment)).toBe(
        false,
      )
    })

    test('is false when the status is neither new nor draft', () => {
      const segment = buildSegment({
        status: 'TRANSLATED',
        modified: true,
        translation: 'x',
      })
      expect(SetTranslationUtil.translationIsToSaveBeforeClose(segment)).toBe(
        false,
      )
    })
  })

  describe('isTranslationTailEmpty', () => {
    test('is true when nothing has been queued', () => {
      expect(SetTranslationUtil.isTranslationTailEmpty()).toBe(true)
    })
  })
})
