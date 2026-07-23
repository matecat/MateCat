jest.mock('../stores/AppDispatcher', () => ({
  dispatch: jest.fn(),
  register: jest.fn(),
}))

jest.mock('../stores/SegmentStore', () => ({
  getCurrentSegmentId: jest.fn(),
  getSegmentByIdToJS: jest.fn(),
  getCurrentSegment: jest.fn(),
  getNextSegment: jest.fn(),
  getSelectedSegmentId: jest.fn(),
  getSegmentChoosenContribution: jest.fn(),
  getNextUntranslatedSegmentId: jest.fn(),
  consecutiveCopySourceNum: [],
  consecutiveUnlockSegments: [],
  nextUntranslatedFromServer: null,
  isSearchingGlossaryInTarget: false,
  _segments: {toJS: jest.fn(() => [])},
}))

jest.mock('../stores/CatToolStore', () => ({
  getJobMetadata: jest.fn(),
  haveKeysGlossary: true,
}))

jest.mock('../utils/segmentUtils', () => ({
  isIceSegment: jest.fn(() => false),
  isReadonlySegment: jest.fn(),
  removeUnlockedSegment: jest.fn(),
}))

jest.mock('./CatToolActions', () => ({
  addNotification: jest.fn(),
  onRender: jest.fn(),
  processErrors: jest.fn(),
  reloadQualityReport: jest.fn(),
  updateFooterStatistics: jest.fn(),
  reloadSegmentFilter: jest.fn(),
}))

jest.mock('./ModalsActions', () => ({
  showModalComponent: jest.fn(),
  onCloseModal: jest.fn(),
}))

jest.mock('../components/modals/AlertModal', () => 'AlertModal')
jest.mock('../components/modals/CopySourceModal', () => ({
  COPY_SOURCE_COOKIE: 'copy_source',
  __esModule: true,
  default: 'CopySourceModal',
}))
jest.mock(
  '../components/modals/ConfirmMessageModal',
  () => 'ConfirmMessageModal',
)

jest.mock('../utils/offlineUtils', () => ({
  startOfflineMode: jest.fn(),
  failedConnection: jest.fn(),
}))

jest.mock('../utils/textUtils', () => ({
  __esModule: true,
  default: {justSelecting: jest.fn(() => false)},
}))

jest.mock('../utils/commonUtils', () => ({
  __esModule: true,
  default: {dispatchCustomEvent: jest.fn()},
}))

jest.mock('../components/segments/utils/translationMatches', () => ({
  __esModule: true,
  default: {
    getContributionsWithPrefetch: jest.fn(),
    getContribution: jest.fn(),
    processContributions: jest.fn(),
    setDeleteSuggestion: jest.fn(() => Promise.resolve()),
  },
}))

jest.mock('../components/segments/utils/DraftMatecatUtils', () => ({
  __esModule: true,
  default: {
    removePlaceholdersForGlossary: jest.fn((t) => t),
    removeTagsFromText: jest.fn((t) => t),
  },
}))

jest.mock('../components/header/cattol/segment_filter/segment_filter', () => ({
  __esModule: true,
  default: {
    enabled: jest.fn(() => false),
    filtering: jest.fn(() => false),
    open: false,
    gotoNextSegment: jest.fn(),
    gotoNextTranslatedSegment: jest.fn(),
  },
}))

jest.mock('../constants/SegmentConstants', () => ({}))
jest.mock('../constants/EditAreaConstants', () => ({}))
jest.mock('../constants/CatToolConstants', () => ({}))
jest.mock('../constants/Constants', () => ({
  REVISE_STEP_NUMBER: {},
  SEGMENTS_STATUS: {},
}))

jest.mock('../components/segments/SegmentFooter', () => ({
  TAB: {},
}))

jest.mock('../api/getGlossaryForSegment', () => ({
  getGlossaryForSegment: jest.fn(),
}))
jest.mock('../api/getGlossaryMatch', () => ({getGlossaryMatch: jest.fn()}))
jest.mock('../api/deleteGlossaryItem', () => ({deleteGlossaryItem: jest.fn()}))
jest.mock('../api/addGlossaryItem', () => ({addGlossaryItem: jest.fn()}))
jest.mock('../api/updateGlossaryItem', () => ({updateGlossaryItem: jest.fn()}))
jest.mock('../api/approveSegments', () => ({approveSegments: jest.fn()}))
jest.mock('../api/translateSegments', () => ({translateSegments: jest.fn()}))
jest.mock('../api/splitSegment', () => ({splitSegment: jest.fn()}))
jest.mock('../api/copyAllSourceToTarget', () => ({
  copyAllSourceToTarget: jest.fn(),
}))
jest.mock('../api/getLocalWarnings', () => ({getLocalWarnings: jest.fn()}))
jest.mock('../api/getGlossaryCheck', () => ({getGlossaryCheck: jest.fn()}))
jest.mock('../api/deleteSegmentIssue', () => ({
  deleteSegmentIssue: jest.fn(),
}))
jest.mock('../api/getSegmentsIssues', () => ({getSegmentsIssues: jest.fn()}))
jest.mock('../api/getSegmentVersionsIssues', () => ({
  getSegmentVersionsIssues: jest.fn(),
}))
jest.mock('../api/sendSegmentVersionIssueComment', () => ({
  sendSegmentVersionIssueComment: jest.fn(),
}))
jest.mock('../api/getTagProjection', () => ({getTagProjection: jest.fn()}))
jest.mock('../api/setCurrentSegment', () => ({setCurrentSegment: jest.fn()}))
jest.mock('../api/getTranslationMismatches', () => ({
  getTranslationMismatches: jest.fn(),
}))

jest.mock('react', () => ({createElement: jest.fn()}))
jest.mock('jquery', () => {
  const $ = jest.fn(() => ({find: jest.fn()}))
  $.each = (arr, cb) =>
    (arr || []).forEach((item, index) => cb.call(item, index, item))
  return $
})
jest.mock('lodash', () => {
  const actual = jest.requireActual('lodash')
  return {
    each: actual.each,
    forEach: actual.forEach,
    isUndefined: (v) => typeof v === 'undefined',
  }
})
jest.mock('lodash/function', () => ({
  debounce: (fn) => fn,
}))
jest.mock('immutable', () => ({fromJS: jest.fn()}))
jest.mock('lodash/array', () => ({union: jest.fn()}))

jest.mock('../utils/speech2text', () => ({
  __esModule: true,
  default: {enabled: jest.fn(() => false)},
}))

jest.mock('./segmentClassActions', () => ({
  addClassToSegment: jest.fn(),
  removeClassToSegment: jest.fn(),
}))

jest.mock('../setTranslationUtil', () => ({
  segmentTranslation: jest.fn(),
  translationIsToSaveBeforeClose: jest.fn(() => false),
}))

jest.mock('./notificationActions', () => ({addNotification: jest.fn()}))
jest.mock('./warningActions', () => ({updateGlobalWarnings: jest.fn()}))
jest.mock('./segmentQaActions', () => ({
  getSegmentsQa: jest.fn(),
  startSegmentQACheck: jest.fn(),
}))
jest.mock('./tagProjectionActions', () => ({disableTPOnSegment: jest.fn()}))
jest.mock('../utils/segmentLocalStorage', () => ({
  setLastSegmentFromLocalStorage: jest.fn(),
}))

import SegmentActions from './SegmentActions'
import SegmentUtils from '../utils/segmentUtils'
import ModalsActions from './ModalsActions'
import CatToolStore from '../stores/CatToolStore'
import AppDispatcher from '../stores/AppDispatcher'
import SegmentStore from '../stores/SegmentStore'
import CatToolActions from './CatToolActions'
import TranslationMatches from '../components/segments/utils/translationMatches'
import OfflineUtils from '../utils/offlineUtils'
import {addNotification} from './notificationActions'
import {getSegmentsQa, startSegmentQACheck} from './segmentQaActions'
import {disableTPOnSegment} from './tagProjectionActions'
import {setLastSegmentFromLocalStorage} from '../utils/segmentLocalStorage'
import {splitSegment} from '../api/splitSegment'
import {copyAllSourceToTarget} from '../api/copyAllSourceToTarget'
import {approveSegments} from '../api/approveSegments'
import {translateSegments} from '../api/translateSegments'
import {deleteGlossaryItem} from '../api/deleteGlossaryItem'
import {addGlossaryItem} from '../api/addGlossaryItem'
import {updateGlossaryItem} from '../api/updateGlossaryItem'
import {getGlossaryForSegment} from '../api/getGlossaryForSegment'
import {getGlossaryMatch} from '../api/getGlossaryMatch'
import {deleteSegmentIssue} from '../api/deleteSegmentIssue'
import {getSegmentsIssues} from '../api/getSegmentsIssues'
import {getSegmentVersionsIssues} from '../api/getSegmentVersionsIssues'
import {sendSegmentVersionIssueComment} from '../api/sendSegmentVersionIssueComment'
import {getTagProjection} from '../api/getTagProjection'
import {setCurrentSegment} from '../api/setCurrentSegment'
import {getTranslationMismatches} from '../api/getTranslationMismatches'
import * as SetTranslationUtil from '../setTranslationUtil'
import SegmentFilterUtil from '../components/header/cattol/segment_filter/segment_filter'

const flushDynamicImports = () =>
  new Promise((resolve) => setTimeout(resolve, 0))

describe('SegmentActions.handleClickOnReadOnly', () => {
  beforeEach(() => {
    global.config = {
      id_job: 2,
      project_completion_feature_enabled: false,
      isReview: false,
      job_completion_current_phase: 'translate',
    }

    jest.clearAllMocks()
    SegmentUtils.isIceSegment.mockReturnValue(false)
  })

  test('shows "Segment disabled" AlertModal when metadata has translation_disabled=true', () => {
    const segment = {
      unlocked: true,
      metadata: [{meta_key: 'translation_disabled', meta_value: true}],
    }

    SegmentActions.handleClickOnReadOnly(segment)

    expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
      'ConfirmMessage',
      {
        text: 'This segment was disabled by the project owner and cannot be edited.',
        successText: 'Got it',
      },
      'Segment disabled',
    )
  })

  test('shows generic readonly AlertModal when segment is not disabled and not ICE-locked', () => {
    const segment = {
      unlocked: true,
      metadata: [],
    }

    SegmentActions.handleClickOnReadOnly(segment)

    expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
      'Alert',
      expect.objectContaining({
        text: expect.any(String),
      }),
    )
    expect(ModalsActions.showModalComponent).not.toHaveBeenCalledWith(
      'ConfirmMessage',
      expect.objectContaining({
        text: 'This segment was disabled by the project owner and cannot be edited.',
        successText: 'Got it',
      }),
      'Segment disabled',
    )
  })

  test('shows ICE match modal when segment is ICE-locked', () => {
    SegmentUtils.isIceSegment.mockReturnValue(true)

    const segment = {
      unlocked: false,
      metadata: [{meta_key: 'translation_disabled', meta_value: true}],
    }

    SegmentActions.handleClickOnReadOnly(segment)

    expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
      'Alert',
      expect.objectContaining({
        text: expect.stringContaining('Segment is locked'),
      }),
      'Ice Matches',
    )
  })

  test('does not show disabled modal when translation_disabled is false', () => {
    const segment = {
      unlocked: true,
      metadata: [{meta_key: 'translation_disabled', meta_value: false}],
    }

    SegmentActions.handleClickOnReadOnly(segment)

    expect(ModalsActions.showModalComponent).not.toHaveBeenCalledWith(
      'ConfirmMessage',
      expect.objectContaining({
        text: 'This segment was disabled by the project owner and cannot be edited.',
        successText: 'Got it',
      }),
      'Segment disabled',
    )
  })
})

describe('SegmentActions.clickOnApprovedButton — mandatory issues gate', () => {
  let openIssuesSpy
  let showIssuesMessageSpy

  beforeAll(async () => {
    await Promise.resolve()
  })

  beforeEach(() => {
    global.config = {
      isReview: true,
      revisionNumber: 1,
    }
    jest.useFakeTimers()
    jest.clearAllMocks()
    openIssuesSpy = jest
      .spyOn(SegmentActions, 'openIssuesPanel')
      .mockReturnValue()
    showIssuesMessageSpy = jest
      .spyOn(SegmentActions, 'showIssuesMessage')
      .mockReturnValue()
  })

  afterEach(() => {
    openIssuesSpy.mockRestore()
    showIssuesMessageSpy.mockRestore()
    jest.useRealTimers()
  })

  const makeSegment = () => ({
    sid: '1-1',
    modified: true,
    splitted: false,
    ice_locked: false,
    versions: [],
  })

  test('opens issues panel when mandatory_issues is undefined (non-array defaults to required)', () => {
    CatToolStore.getJobMetadata.mockReturnValue({
      project: {mandatory_issues: undefined},
    })
    SegmentActions.clickOnApprovedButton(makeSegment(), false)
    expect(openIssuesSpy).toHaveBeenCalledWith({sid: '1-1'}, true)
  })

  test('opens issues panel when current revision is in mandatory_issues array', () => {
    CatToolStore.getJobMetadata.mockReturnValue({
      project: {mandatory_issues: ['r1', 'r2']},
    })
    SegmentActions.clickOnApprovedButton(makeSegment(), false)
    expect(openIssuesSpy).toHaveBeenCalledWith({sid: '1-1'}, true)
  })

  test('skips issues panel when current revision is absent from mandatory_issues', () => {
    CatToolStore.getJobMetadata.mockReturnValue({
      project: {mandatory_issues: ['r2']},
    })
    SegmentActions.clickOnApprovedButton(makeSegment(), false)
    expect(openIssuesSpy).not.toHaveBeenCalled()
  })

  test('skips issues panel when mandatory_issues is empty (none required)', () => {
    CatToolStore.getJobMetadata.mockReturnValue({
      project: {mandatory_issues: []},
    })
    SegmentActions.clickOnApprovedButton(makeSegment(), false)
    expect(openIssuesSpy).not.toHaveBeenCalled()
  })

  test('opens issues panel for revision 2 when r2 is in mandatory_issues', () => {
    global.config.revisionNumber = 2
    CatToolStore.getJobMetadata.mockReturnValue({
      project: {mandatory_issues: ['r1', 'r2']},
    })
    SegmentActions.clickOnApprovedButton(makeSegment(), false)
    expect(openIssuesSpy).toHaveBeenCalledWith({sid: '1-1'}, true)
  })

  test('skips issues panel for revision 2 when only r1 is in mandatory_issues', () => {
    global.config.revisionNumber = 2
    CatToolStore.getJobMetadata.mockReturnValue({
      project: {mandatory_issues: ['r1']},
    })
    SegmentActions.clickOnApprovedButton(makeSegment(), false)
    expect(openIssuesSpy).not.toHaveBeenCalled()
  })
})

describe('SegmentActions.updateSegmentDisabledState', () => {
  beforeEach(() => {
    jest.clearAllMocks()
  })

  test('dispatches SET_SEGMENT_DISABLED with disabled=true', () => {
    SegmentActions.updateSegmentDisabledState(42, true)

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({id: 42, disabled: true}),
    )
  })

  test('dispatches SET_SEGMENT_DISABLED with disabled=false', () => {
    SegmentActions.updateSegmentDisabledState(42, false)

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({id: 42, disabled: false}),
    )
  })

  test('does not dispatch when sid is falsy', () => {
    SegmentActions.updateSegmentDisabledState(null, true)

    expect(AppDispatcher.dispatch).not.toHaveBeenCalled()
  })
})

// Ensure the dynamically-imported utils (setTranslationUtil, segment_filter)
// have resolved before the suites that depend on them run.
beforeAll(async () => {
  await flushDynamicImports()
})

// Restore any jest.spyOn spies between tests so they don't leak across suites
// (jest.clearAllMocks only clears call data, it does not restore spies).
afterEach(() => {
  jest.restoreAllMocks()
})

const baseConfig = () => ({
  id_job: 2,
  password: 'pwd',
  isReview: false,
  revisionNumber: 1,
  translation_matches_enabled: true,
  alternativesEnabled: true,
  source_rfc: 'en-US',
  target_rfc: 'it-IT',
})

describe('SegmentActions — simple dispatch actions', () => {
  beforeEach(() => {
    global.config = baseConfig()
    jest.clearAllMocks()
  })

  test('renderSegments dispatches segments and idToOpen', () => {
    SegmentActions.renderSegments([{sid: '1'}], '1')
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({segments: [{sid: '1'}], idToOpen: '1'}),
    )
  })

  test('splitSegments dispatches split payload', () => {
    SegmentActions.splitSegments('1', [{}], 'g', 2)
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({oldSid: '1', splitGroup: 'g', fid: 2}),
    )
  })

  test('addSegments dispatches segments and where', () => {
    SegmentActions.addSegments([{}], 'after')
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({segments: [{}], where: 'after'}),
    )
  })

  test('updateAllSegments dispatches', () => {
    SegmentActions.updateAllSegments()
    expect(AppDispatcher.dispatch).toHaveBeenCalledTimes(1)
  })

  test('changeCurrentSearchSegment dispatches currentIndex', () => {
    SegmentActions.changeCurrentSearchSegment(3)
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({currentIndex: 3}),
    )
  })

  test('replaceCurrentSearch dispatches text', () => {
    SegmentActions.replaceCurrentSearch('foo')
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({text: 'foo'}),
    )
  })

  test('setOpenSegment dispatches sid and fid', () => {
    SegmentActions.setOpenSegment('1', 2)
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({sid: '1', fid: 2}),
    )
  })

  test('updateTranslation dispatches full payload', () => {
    SegmentActions.updateTranslation('1', 't', 'd', {}, [], 'l')
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({id: '1', translation: 't'}),
    )
  })

  test('updateOriginalTranslation dispatches', () => {
    SegmentActions.updateOriginalTranslation('1', 'orig')
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({id: '1', originalTranslation: 'orig'}),
    )
  })

  test('updateSource dispatches', () => {
    SegmentActions.updateSource('1', 's', 'ds', {}, 'lx')
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({id: '1', source: 's'}),
    )
  })

  test('lockEditArea / unlockEditArea / setFocusOnEditArea / autoFillTagsInTarget', () => {
    SegmentActions.lockEditArea('1', 2)
    SegmentActions.unlockEditArea('1', 2)
    SegmentActions.setFocusOnEditArea()
    SegmentActions.autoFillTagsInTarget('1')
    expect(AppDispatcher.dispatch).toHaveBeenCalledTimes(4)
  })

  test('changeTagProjectionStatus dispatches enabled', () => {
    SegmentActions.changeTagProjectionStatus(true)
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({enabled: true}),
    )
  })

  test('addQaCheck dispatches sid and data', () => {
    SegmentActions.addQaCheck('1', {a: 1})
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({sid: '1', data: {a: 1}}),
    )
  })

  test('selectNextSegment / selectPrevSegment dispatch direction', () => {
    SegmentActions.selectNextSegment('1')
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({sid: '1', direction: 'next'}),
    )
    SegmentActions.selectPrevSegment('1')
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({sid: '1', direction: 'prev'}),
    )
  })

  test('debounced select helpers invoke underlying actions', () => {
    SegmentActions.selectNextSegmentDebounced()
    SegmentActions.selectPrevSegmentDebounced()
    expect(AppDispatcher.dispatch).toHaveBeenCalledTimes(2)
  })

  test('closeSplitSegment dispatches', () => {
    SegmentActions.closeSplitSegment()
    expect(AppDispatcher.dispatch).toHaveBeenCalledTimes(1)
  })

  test('registerTab dispatches tab/visible/open', () => {
    SegmentActions.registerTab('t', true, false)
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({tab: 't', visible: true, open: false}),
    )
  })

  test('setSegmentCrossLanguageContributions dispatches matches', () => {
    SegmentActions.setSegmentCrossLanguageContributions('1', 2, [{}], null)
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({sid: '1', fid: 2, matches: [{}]}),
    )
  })

  test('setAlternatives dispatches', () => {
    SegmentActions.setAlternatives('1', {a: 1})
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({sid: '1', alternatives: {a: 1}}),
    )
  })

  test('closeTabs / setTabOpen dispatch', () => {
    SegmentActions.closeTabs('1')
    SegmentActions.setTabOpen('1', 'matches')
    expect(AppDispatcher.dispatch).toHaveBeenCalledTimes(2)
  })

  test('setGlossaryForSegment / setGlossaryForSegmentBySearch dispatch', () => {
    SegmentActions.setGlossaryForSegment('1', [{}])
    SegmentActions.setGlossaryForSegmentBySearch('1', [{}])
    expect(SegmentStore.isSearchingGlossaryInTarget).toBe(false)
    expect(AppDispatcher.dispatch).toHaveBeenCalledTimes(2)
  })

  test('glossary cache dispatchers', () => {
    SegmentActions.deleteGlossaryFromCache('1', 't')
    SegmentActions.errorDeleteGlossaryFromCache('1', {message: 'e'})
    SegmentActions.errorDeleteGlossaryFromCache('1', {code: 5})
    SegmentActions.addGlossaryItemToCache('1', {})
    SegmentActions.errorAddGlossaryItemToCache('1', {message: 'e'})
    SegmentActions.errorAddGlossaryItemToCache('1', {code: 5})
    SegmentActions.updateglossaryCache('1', {})
    SegmentActions.errorUpdateglossaryCache('1', {message: 'e'})
    SegmentActions.errorUpdateglossaryCache('1', {code: 5})
    SegmentActions.copyGlossaryItemInEditarea('g', {})
    expect(AppDispatcher.dispatch).toHaveBeenCalled()
  })

  test('setTabIndex dispatches', () => {
    SegmentActions.setTabIndex('1', 'matches', 5)
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({sid: '1', tab: 'matches', data: 5}),
    )
  })

  test('findConcordance / setConcordanceResult / modifyTabVisibility dispatch', () => {
    SegmentActions.findConcordance('1', {text: 'x'})
    SegmentActions.setConcordanceResult('1', {matches: []})
    SegmentActions.modifyTabVisibility('matches', true)
    expect(AppDispatcher.dispatch).toHaveBeenCalledTimes(3)
  })

  test('showSelection / issueAdded / openIssueComments / confirmDeletedIssue / addTranslationIssuesToSegment / showIssuesMessage', () => {
    SegmentActions.showSelection('1', {})
    SegmentActions.issueAdded('1', 9)
    SegmentActions.openIssueComments('1', 9)
    SegmentActions.confirmDeletedIssue('1', 9)
    SegmentActions.addTranslationIssuesToSegment('1', [])
    SegmentActions.showIssuesMessage('1', 1)
    expect(AppDispatcher.dispatch).toHaveBeenCalledTimes(6)
  })

  test('bulk dispatchers', () => {
    SegmentActions.toggleSegmentOnBulk('1', 2)
    SegmentActions.removeSegmentsOnBulk()
    SegmentActions.setBulkSelectionInterval(1, 5, 2)
    SegmentActions.setBulkSelectionSegments([1, 2])
    expect(AppDispatcher.dispatch).toHaveBeenCalledTimes(4)
  })

  test('side / comment panel dispatchers write localStorage', () => {
    SegmentActions.openSideSegments()
    SegmentActions.closeSideSegments()
    SegmentActions.openSegmentComment('1')
    SegmentActions.closeSegmentComment('1')
    expect(AppDispatcher.dispatch).toHaveBeenCalledTimes(4)
  })

  test('editarea / misc dispatchers', () => {
    SegmentActions.copyFragmentToClipboard('f', 'p')
    SegmentActions.editAreaChanged('1', true)
    SegmentActions.highlightTags('t', 'p', 'k', true)
    SegmentActions.hideAiAssistant()
    SegmentActions.characterCounter({
      sid: '1',
      counter: 1,
      segmentCharacters: 2,
      limit: 3,
    })
    SegmentActions.getMoreSegments('after')
    SegmentActions.freezingSegments(true)
    SegmentActions.aiSuggestion({
      sid: '1',
      suggestion: 's',
      isCompleted: true,
      hasError: false,
    })
    SegmentActions.setIsCurrentSearchOccurrenceTag(true)
    SegmentActions.focusTags(['a'])
    SegmentActions.aiAlternativeSuggestion({sid: '1', data: {}})
    SegmentActions.aiFeedbackSuggestion({sid: '1', data: {}})
    SegmentActions.changeCharactersCounterRules()
    SegmentActions.setCurrentSegmentId('1')
    expect(AppDispatcher.dispatch).toHaveBeenCalled()
  })

  test('openGlossaryFormPrefill activates tab and dispatches', () => {
    SegmentActions.openGlossaryFormPrefill({sid: '1', term: 'x'})
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({sid: '1', term: 'x'}),
    )
  })
})

describe('SegmentActions.openSegment', () => {
  beforeEach(() => {
    global.config = baseConfig()
    jest.clearAllMocks()
  })

  test('dispatches scroll + open when segment exists and not splitted', () => {
    SegmentStore.getSegmentByIdToJS.mockReturnValue({sid: '1', splitted: false})
    SegmentActions.openSegment('1')
    expect(AppDispatcher.dispatch).toHaveBeenCalledTimes(2)
  })

  test('scrolls to first sub-segment when splitted and sid has no dash', () => {
    SegmentStore.getSegmentByIdToJS.mockReturnValue({sid: '1', splitted: true})
    const spy = jest.spyOn(SegmentActions, 'scrollToSegment').mockReturnValue()
    SegmentActions.openSegment('1')
    expect(spy).toHaveBeenCalledWith('1-1', SegmentActions.openSegment)
    spy.mockRestore()
  })

  test('re-renders via CatToolActions when segment not found', () => {
    SegmentStore.getSegmentByIdToJS.mockReturnValue(undefined)
    SegmentActions.openSegment('99')
    expect(CatToolActions.onRender).toHaveBeenCalledWith(
      expect.objectContaining({segmentToOpen: '99', firstLoad: false}),
    )
  })
})

describe('SegmentActions.closeSegment / scroll helpers', () => {
  beforeEach(() => {
    global.config = baseConfig()
    jest.clearAllMocks()
  })

  test('closeSegment dispatches and closes issues panel', () => {
    SegmentActions.closeSegment()
    expect(AppDispatcher.dispatch).toHaveBeenCalledTimes(2)
  })

  test('scrollToCurrentSegment scrolls when a current segment exists', () => {
    SegmentStore.getCurrentSegment.mockReturnValue({sid: '1'})
    const spy = jest.spyOn(SegmentActions, 'scrollToSegment').mockReturnValue()
    SegmentActions.scrollToCurrentSegment()
    expect(spy).toHaveBeenCalledWith('1')
    spy.mockRestore()
  })

  test('scrollToCurrentSegment does nothing when no current segment', () => {
    SegmentStore.getCurrentSegment.mockReturnValue(undefined)
    const spy = jest.spyOn(SegmentActions, 'scrollToSegment').mockReturnValue()
    SegmentActions.scrollToCurrentSegment()
    expect(spy).not.toHaveBeenCalled()
    spy.mockRestore()
  })

  test('scrollToSegment dispatches and runs callback when segment exists', () => {
    jest.useFakeTimers()
    SegmentStore.getSegmentByIdToJS.mockReturnValue({sid: '1'})
    const cb = jest.fn()
    SegmentActions.scrollToSegment('1', cb)
    jest.runAllTimers()
    expect(AppDispatcher.dispatch).toHaveBeenCalled()
    expect(cb).toHaveBeenCalledWith('1')
    jest.useRealTimers()
  })

  test('scrollToSegment re-renders when segment not found', () => {
    SegmentStore.getSegmentByIdToJS.mockReturnValue(undefined)
    SegmentActions.scrollToSegment('99')
    expect(CatToolActions.onRender).toHaveBeenCalled()
  })

  test('openSelectedSegment opens selected id when present', () => {
    SegmentStore.getSelectedSegmentId.mockReturnValue('5')
    const spy = jest.spyOn(SegmentActions, 'openSegment').mockReturnValue()
    SegmentActions.openSelectedSegment()
    expect(spy).toHaveBeenCalledWith('5')
    spy.mockRestore()
  })

  test('openSelectedSegment does nothing when no selected id', () => {
    SegmentStore.getSelectedSegmentId.mockReturnValue(undefined)
    const spy = jest.spyOn(SegmentActions, 'openSegment').mockReturnValue()
    SegmentActions.openSelectedSegment()
    expect(spy).not.toHaveBeenCalled()
    spy.mockRestore()
  })
})

describe('SegmentActions.saveSegmentBeforeClose', () => {
  beforeEach(() => {
    global.config = baseConfig()
    jest.clearAllMocks()
  })

  test('delegates to segmentTranslation when translation must be saved', () => {
    SetTranslationUtil.translationIsToSaveBeforeClose.mockReturnValue(true)
    SegmentActions.saveSegmentBeforeClose({sid: '1'})
    expect(SetTranslationUtil.segmentTranslation).toHaveBeenCalled()
  })

  test('resolves without saving when nothing to save', async () => {
    SetTranslationUtil.translationIsToSaveBeforeClose.mockReturnValue(false)
    await expect(
      SegmentActions.saveSegmentBeforeClose({sid: '1'}),
    ).resolves.toBeUndefined()
    expect(SetTranslationUtil.segmentTranslation).not.toHaveBeenCalled()
  })
})

describe('SegmentActions.splitSegment (async)', () => {
  beforeEach(() => {
    global.config = baseConfig()
    jest.clearAllMocks()
  })

  test('on success removes segments and re-renders', async () => {
    splitSegment.mockResolvedValue()
    const removeSpy = jest
      .spyOn(SegmentActions, 'removeAllSegments')
      .mockReturnValue()
    SegmentActions.splitSegment('3-1', 'text')
    await flushDynamicImports()
    expect(removeSpy).toHaveBeenCalled()
    expect(CatToolActions.onRender).toHaveBeenCalledWith({segmentToOpen: '3'})
    removeSpy.mockRestore()
  })

  test('on failure notifies and unfreezes', async () => {
    splitSegment.mockRejectedValue([{message: 'boom'}])
    const freezeSpy = jest
      .spyOn(SegmentActions, 'freezingSegments')
      .mockReturnValue()
    SegmentActions.splitSegment('3-1', 'text')
    await flushDynamicImports()
    expect(addNotification).toHaveBeenCalledWith(
      expect.objectContaining({text: 'boom', type: 'error'}),
    )
    freezeSpy.mockRestore()
  })

  test('on failure with no messages uses fallback text', async () => {
    splitSegment.mockRejectedValue([])
    const freezeSpy = jest
      .spyOn(SegmentActions, 'freezingSegments')
      .mockReturnValue()
    SegmentActions.splitSegment('3-1', 'text')
    await flushDynamicImports()
    expect(addNotification).toHaveBeenCalledWith(
      expect.objectContaining({
        text: 'We got an error, please contact support',
      }),
    )
    freezeSpy.mockRestore()
  })
})

describe('SegmentActions.clickOnApprovedButton — translation path', () => {
  beforeEach(() => {
    global.config = baseConfig()
    global.config.isReview = true
    jest.clearAllMocks()
    CatToolStore.getJobMetadata.mockReturnValue({
      project: {mandatory_issues: []},
    })
  })

  test('runs approve and goes to next segment (no goToNextUnapproved)', () => {
    const gotoSpy = jest
      .spyOn(SegmentActions, 'gotoNextSegment')
      .mockReturnValue()
    const segment = {sid: '1-1', modified: false, splitted: false, versions: []}
    SegmentActions.clickOnApprovedButton(segment, false)
    expect(SetTranslationUtil.segmentTranslation).toHaveBeenCalled()
    const afterFn = SetTranslationUtil.segmentTranslation.mock.calls[0][2]
    afterFn()
    expect(gotoSpy).toHaveBeenCalledWith('1-1')
    gotoSpy.mockRestore()
  })

  test('goToNextUnapproved with revision_number>1 opens next approved', () => {
    global.config.revisionNumber = 2
    const openNextSpy = jest
      .spyOn(SegmentActions, 'openNextApproved')
      .mockReturnValue()
    const segment = {
      sid: '1-1',
      modified: false,
      splitted: false,
      versions: [],
      revision_number: 2,
    }
    SegmentActions.clickOnApprovedButton(segment, true)
    const afterFn = SetTranslationUtil.segmentTranslation.mock.calls[0][2]
    afterFn()
    expect(openNextSpy).toHaveBeenCalled()
    expect(SegmentUtils.removeUnlockedSegment).toHaveBeenCalledWith('1-1')
    openNextSpy.mockRestore()
  })

  test('goToNextUnapproved with revision_number<=1 goes to next translated', () => {
    const gotoTranslatedSpy = jest
      .spyOn(SegmentActions, 'gotoNextTranslatedSegment')
      .mockReturnValue()
    const segment = {
      sid: '1-1',
      modified: false,
      splitted: false,
      versions: [],
      revision_number: 1,
    }
    SegmentActions.clickOnApprovedButton(segment, true)
    const afterFn = SetTranslationUtil.segmentTranslation.mock.calls[0][2]
    afterFn()
    expect(gotoTranslatedSpy).toHaveBeenCalled()
    gotoTranslatedSpy.mockRestore()
  })
})

describe('SegmentActions.clickOnTranslatedButton', () => {
  beforeEach(() => {
    global.config = baseConfig()
    jest.clearAllMocks()
  })

  test('goes to next segment when not goToNextUntranslated', () => {
    const gotoSpy = jest
      .spyOn(SegmentActions, 'gotoNextSegment')
      .mockReturnValue()
    SegmentActions.clickOnTranslatedButton({sid: '1'}, false)
    const afterFn = SetTranslationUtil.segmentTranslation.mock.calls[0][2]
    afterFn()
    expect(gotoSpy).toHaveBeenCalled()
    gotoSpy.mockRestore()
  })

  test('goes to next untranslated when goToNextUntranslated', () => {
    const gotoSpy = jest
      .spyOn(SegmentActions, 'gotoNextUntranslatedSegment')
      .mockReturnValue()
    SegmentActions.clickOnTranslatedButton({sid: '1'}, true)
    const afterFn = SetTranslationUtil.segmentTranslation.mock.calls[0][2]
    afterFn()
    expect(gotoSpy).toHaveBeenCalled()
    gotoSpy.mockRestore()
  })
})

describe('SegmentActions.openNextApproved', () => {
  beforeEach(() => {
    global.config = baseConfig()
    jest.clearAllMocks()
    SegmentStore.nextUntranslatedFromServer = null
  })

  test('opens the next approved segment when found', () => {
    SegmentStore.getNextSegment
      .mockReturnValueOnce({sid: '5'})
      .mockReturnValueOnce(null)
    const spy = jest.spyOn(SegmentActions, 'openSegment').mockReturnValue()
    SegmentActions.openNextApproved('1')
    expect(spy).toHaveBeenCalledWith('5')
    spy.mockRestore()
  })

  test('falls back to server next untranslated when no next approved', () => {
    SegmentStore.getNextSegment.mockReturnValue(null)
    SegmentStore.nextUntranslatedFromServer = '9'
    const spy = jest.spyOn(SegmentActions, 'openSegment').mockReturnValue()
    SegmentActions.openNextApproved('1')
    expect(spy).toHaveBeenCalledWith('9')
    spy.mockRestore()
  })

  test('falls back to previous approved when present and no server next', () => {
    SegmentStore.getNextSegment
      .mockReturnValueOnce(null)
      .mockReturnValueOnce({sid: '3'})
    SegmentStore.nextUntranslatedFromServer = null
    const spy = jest.spyOn(SegmentActions, 'openSegment').mockReturnValue()
    SegmentActions.openNextApproved()
    expect(spy).toHaveBeenCalledWith('3')
    spy.mockRestore()
  })
})

describe('SegmentActions.propagateTranslation', () => {
  beforeEach(() => {
    global.config = baseConfig()
    jest.clearAllMocks()
  })

  test('returns early when source segment not found', () => {
    SegmentStore.getSegmentByIdToJS.mockReturnValue(undefined)
    expect(
      SegmentActions.propagateTranslation('1', ['2'], 'TRANSLATED'),
    ).toBeUndefined()
  })

  test('returns false when segment is split more than twice', () => {
    SegmentStore.getSegmentByIdToJS.mockReturnValue({sid: '1', splitted: 3})
    expect(SegmentActions.propagateTranslation('1', ['2'], 'TRANSLATED')).toBe(
      false,
    )
  })

  test('propagates translation to other segments', () => {
    const source = {sid: '1', splitted: 0, translation: 'hola'}
    SegmentStore.getSegmentByIdToJS.mockImplementation((sid) =>
      sid === '1' ? source : {sid, splitted: 0},
    )
    const updateOrigSpy = jest
      .spyOn(SegmentActions, 'updateOriginalTranslation')
      .mockReturnValue()
    const setStatusSpy = jest
      .spyOn(SegmentActions, 'setStatus')
      .mockReturnValue()
    const setAltSpy = jest
      .spyOn(SegmentActions, 'setAlternatives')
      .mockReturnValue()
    SegmentActions.propagateTranslation('1', ['2', '3'], 'TRANSLATED')
    expect(updateOrigSpy).toHaveBeenCalledWith('2', 'hola')
    expect(setStatusSpy).toHaveBeenCalledWith('2', null, 'TRANSLATED')
    expect(setAltSpy).toHaveBeenCalledWith('1', undefined)
    updateOrigSpy.mockRestore()
    setStatusSpy.mockRestore()
    setAltSpy.mockRestore()
  })
})

describe('SegmentActions Tag Projection', () => {
  beforeEach(() => {
    global.config = baseConfig()
    jest.clearAllMocks()
  })

  test('getSegmentTagsProjection sends suggestion when high non-MT match exists', () => {
    SegmentStore.getSegmentByIdToJS.mockReturnValue({
      segment: 'src',
      translation: 'tgt',
    })
    SegmentStore.getSegmentChoosenContribution.mockReturnValue({
      match: '95%',
      translation: 'sugg',
    })
    getTagProjection.mockReturnValue(Promise.resolve({data: {}}))
    SegmentActions.getSegmentTagsProjection('1')
    expect(getTagProjection).toHaveBeenCalledWith(
      expect.objectContaining({
        source: 'src',
        target: 'tgt',
        suggestion: 'sugg',
      }),
    )
  })

  test('getSegmentTagsProjection omits suggestion for MT match', () => {
    SegmentStore.getSegmentByIdToJS.mockReturnValue({
      segment: 'src',
      translation: 'tgt',
    })
    SegmentStore.getSegmentChoosenContribution.mockReturnValue({
      match: 'MT',
      translation: 'sugg',
    })
    SegmentActions.getSegmentTagsProjection('1')
    expect(getTagProjection).toHaveBeenCalledWith(
      expect.objectContaining({suggestion: undefined}),
    )
  })

  test('startSegmentTagProjection success path sets tagged and copies translation', async () => {
    const getSpy = jest
      .spyOn(SegmentActions, 'getSegmentTagsProjection')
      .mockResolvedValue({data: {translation: 'done'}})
    const copySpy = jest
      .spyOn(SegmentActions, 'copyTagProjectionInCurrentSegment')
      .mockReturnValue()
    const taggedSpy = jest
      .spyOn(SegmentActions, 'setSegmentAsTagged')
      .mockReturnValue()
    await SegmentActions.startSegmentTagProjection('1')
    await flushDynamicImports()
    expect(copySpy).toHaveBeenCalledWith('1', 'done')
    expect(taggedSpy).toHaveBeenCalledWith('1')
    expect(startSegmentQACheck).toHaveBeenCalled()
    getSpy.mockRestore()
    copySpy.mockRestore()
    taggedSpy.mockRestore()
  })

  test('startSegmentTagProjection error path with codes processes errors', async () => {
    const getSpy = jest
      .spyOn(SegmentActions, 'getSegmentTagsProjection')
      .mockRejectedValue([{message: 'err'}])
    const autofillSpy = jest
      .spyOn(SegmentActions, 'autoFillTagsInTarget')
      .mockReturnValue()
    jest.spyOn(SegmentActions, 'setSegmentAsTagged').mockReturnValue()
    await SegmentActions.startSegmentTagProjection('1')
    await flushDynamicImports()
    expect(CatToolActions.processErrors).toHaveBeenCalled()
    expect(autofillSpy).toHaveBeenCalledWith('1')
    getSpy.mockRestore()
  })

  test('startSegmentTagProjection error path without codes just autofills', async () => {
    const getSpy = jest
      .spyOn(SegmentActions, 'getSegmentTagsProjection')
      .mockRejectedValue([])
    const autofillSpy = jest
      .spyOn(SegmentActions, 'autoFillTagsInTarget')
      .mockReturnValue()
    jest.spyOn(SegmentActions, 'setSegmentAsTagged').mockReturnValue()
    await SegmentActions.startSegmentTagProjection('1')
    await flushDynamicImports()
    expect(CatToolActions.processErrors).not.toHaveBeenCalled()
    expect(autofillSpy).toHaveBeenCalledWith('1')
    getSpy.mockRestore()
  })

  test('copyTagProjectionInCurrentSegment replaces content only for non-empty text', () => {
    const spy = jest
      .spyOn(SegmentActions, 'replaceEditAreaTextContent')
      .mockReturnValue()
    SegmentActions.copyTagProjectionInCurrentSegment('1', 'abc')
    SegmentActions.copyTagProjectionInCurrentSegment('1', '')
    expect(spy).toHaveBeenCalledTimes(1)
    spy.mockRestore()
  })
})

describe('SegmentActions.chooseContributionOnCurrentSegment', () => {
  beforeEach(() => {
    global.config = baseConfig()
    jest.clearAllMocks()
  })

  test('dispatches when current segment has contributions', () => {
    SegmentStore.getCurrentSegment.mockReturnValue({
      sid: '1',
      contributions: [{}],
    })
    SegmentActions.chooseContributionOnCurrentSegment(0)
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({sid: '1', index: 0}),
    )
  })

  test('does not dispatch when no contributions', () => {
    SegmentStore.getCurrentSegment.mockReturnValue({sid: '1'})
    SegmentActions.chooseContributionOnCurrentSegment(0)
    expect(AppDispatcher.dispatch).not.toHaveBeenCalled()
  })
})

describe('SegmentActions.deleteContribution', () => {
  beforeEach(() => {
    global.config = baseConfig()
    jest.clearAllMocks()
  })

  test('dispatches DELETE_CONTRIBUTION after setDeleteSuggestion resolves', async () => {
    TranslationMatches.setDeleteSuggestion.mockReturnValue(Promise.resolve())
    SegmentActions.deleteContribution('s', 't', 'm', '1')
    await flushDynamicImports()
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({sid: '1', matchId: 'm'}),
    )
  })
})

describe('SegmentActions contribution delegators', () => {
  beforeEach(() => {
    jest.clearAllMocks()
  })

  test('getContributions / getContribution / getContributionsSuccess delegate to TranslationMatches', () => {
    SegmentActions.getContributions('1', {}, true)
    SegmentActions.getContribution('1', {}, false)
    SegmentActions.getContributionsSuccess({a: 1}, '1')
    expect(TranslationMatches.getContributionsWithPrefetch).toHaveBeenCalled()
    expect(TranslationMatches.getContribution).toHaveBeenCalled()
    expect(TranslationMatches.processContributions).toHaveBeenCalledWith(
      {a: 1},
      '1',
    )
  })
})

describe('SegmentActions.openConcordance', () => {
  beforeEach(() => {
    jest.clearAllMocks()
  })

  test('activates concordance tab and finds concordance', () => {
    const activateSpy = jest
      .spyOn(SegmentActions, 'activateTab')
      .mockReturnValue()
    const findSpy = jest
      .spyOn(SegmentActions, 'findConcordance')
      .mockReturnValue()
    SegmentActions.openConcordance('1', 'txt', true)
    expect(activateSpy).toHaveBeenCalledWith('1', 'concordances')
    expect(findSpy).toHaveBeenCalledWith('1', {text: 'txt', inTarget: true})
    activateSpy.mockRestore()
    findSpy.mockRestore()
  })
})

describe('SegmentActions copySource flows', () => {
  beforeEach(() => {
    global.config = baseConfig()
    jest.clearAllMocks()
    SegmentStore.consecutiveCopySourceNum = []
    sessionStorage.clear()
  })

  test('copySourceToTarget copies, resets suggestion and tracks consecutive copies', () => {
    SegmentStore.getCurrentSegment.mockReturnValue({sid: '1', segment: 'src'})
    const replaceSpy = jest
      .spyOn(SegmentActions, 'replaceEditAreaTextContent')
      .mockReturnValue()
    const chooseSpy = jest
      .spyOn(SegmentActions, 'setChoosenSuggestion')
      .mockReturnValue()
    SegmentActions.copySourceToTarget()
    expect(replaceSpy).toHaveBeenCalledWith('1', 'src')
    expect(chooseSpy).toHaveBeenCalledWith('1', null)
    expect(getSegmentsQa).toHaveBeenCalled()
    replaceSpy.mockRestore()
    chooseSpy.mockRestore()
  })

  test('copySourceToTarget triggers copyAllSources after 3 consecutive copies', () => {
    SegmentStore.getCurrentSegment.mockReturnValue({sid: '1', segment: 'src'})
    SegmentStore.consecutiveCopySourceNum = ['a', 'b', 'c']
    jest.spyOn(SegmentActions, 'replaceEditAreaTextContent').mockReturnValue()
    const copyAllSpy = jest
      .spyOn(SegmentActions, 'copyAllSources')
      .mockReturnValue()
    SegmentActions.copySourceToTarget()
    expect(copyAllSpy).toHaveBeenCalled()
    copyAllSpy.mockRestore()
  })

  test('copySourceToTarget does nothing when no current segment', () => {
    SegmentStore.getCurrentSegment.mockReturnValue(undefined)
    SegmentActions.copySourceToTarget()
    expect(getSegmentsQa).not.toHaveBeenCalled()
  })

  test('copyAllSources shows modal when cookie is not set', () => {
    SegmentActions.copyAllSources()
    expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
      'CopySource',
      expect.any(Object),
      'Copy source to ALL segments',
    )
  })

  test('copyAllSources resets counter when cookie is set', () => {
    sessionStorage.setItem('source_copied_to_target', '1')
    SegmentStore.consecutiveCopySourceNum = ['a']
    SegmentActions.copyAllSources()
    expect(SegmentStore.consecutiveCopySourceNum).toEqual([])
  })

  test('abortCopyAllSources resets counter', () => {
    SegmentStore.consecutiveCopySourceNum = ['a']
    SegmentActions.abortCopyAllSources()
    expect(SegmentStore.consecutiveCopySourceNum).toEqual([])
  })

  test('continueCopyAllSources success re-renders', async () => {
    document.body.innerHTML = '<div id="outer"></div>'
    SegmentStore.getCurrentSegmentId.mockReturnValue('1')
    copyAllSourceToTarget.mockResolvedValue()
    jest.spyOn(SegmentActions, 'removeAllSegments').mockReturnValue()
    SegmentActions.continueCopyAllSources()
    await flushDynamicImports()
    expect(CatToolActions.onRender).toHaveBeenCalled()
  })

  test('continueCopyAllSources failure notifies with error message', async () => {
    document.body.innerHTML = '<div id="outer"></div>'
    copyAllSourceToTarget.mockRejectedValue([{message: 'nope'}])
    jest.spyOn(SegmentActions, 'removeAllSegments').mockReturnValue()
    SegmentActions.continueCopyAllSources()
    await flushDynamicImports()
    expect(addNotification).toHaveBeenCalledWith(
      expect.objectContaining({text: 'nope'}),
    )
  })
})

describe('SegmentActions.handleClickOnReadOnly extra branches', () => {
  beforeEach(() => {
    global.config = baseConfig()
    jest.clearAllMocks()
    SegmentUtils.isIceSegment.mockReturnValue(false)
  })

  test('shows read-only warning notification during project completion review phase', () => {
    global.config.project_completion_feature_enabled = true
    global.config.isReview = false
    global.config.job_completion_current_phase = 'revise'
    global.config.chunk_completion_undoable = true
    global.config.last_completion_event_id = 7
    SegmentActions.handleClickOnReadOnly({unlocked: true, metadata: []})
    expect(addNotification).toHaveBeenCalledWith(
      expect.objectContaining({uid: 'translate-warning'}),
    )
  })

  test('messageForClickOnReadonly returns review message during completion phase', () => {
    global.config.project_completion_feature_enabled = true
    global.config.isReview = false
    global.config.job_completion_current_phase = 'revise'
    expect(SegmentActions.messageForClickOnReadonly()).toContain('under review')
  })

  test('messageForClickOnReadonly returns default message otherwise', () => {
    expect(SegmentActions.messageForClickOnReadonly()).toContain(
      'not been assigned',
    )
  })
})

describe('SegmentActions.translateAndGoToNext', () => {
  beforeEach(() => {
    global.config = baseConfig()
    jest.clearAllMocks()
    jest.useFakeTimers()
    SegmentUtils.isReadonlySegment.mockReturnValue(false)
  })
  afterEach(() => jest.useRealTimers())

  test('does nothing without a current segment', () => {
    SegmentStore.getCurrentSegment.mockReturnValue(undefined)
    SegmentActions.translateAndGoToNext()
    jest.runAllTimers()
    expect(SetTranslationUtil.segmentTranslation).not.toHaveBeenCalled()
  })

  test('in review mode approves the segment', () => {
    global.config.isReview = true
    SegmentStore.getCurrentSegment.mockReturnValue({sid: '1'})
    const spy = jest
      .spyOn(SegmentActions, 'clickOnApprovedButton')
      .mockReturnValue()
    SegmentActions.translateAndGoToNext()
    jest.runAllTimers()
    expect(spy).toHaveBeenCalled()
    spy.mockRestore()
  })

  test('starts tag projection when segment is not tagged', () => {
    SegmentStore.getCurrentSegment.mockReturnValue({sid: '1', tagged: false})
    const spy = jest
      .spyOn(SegmentActions, 'startSegmentTagProjection')
      .mockReturnValue()
    SegmentActions.translateAndGoToNext()
    jest.runAllTimers()
    expect(spy).toHaveBeenCalledWith('1')
    spy.mockRestore()
  })

  test('clicks translated when tagged and translation is present', () => {
    SegmentStore.getCurrentSegment.mockReturnValue({
      sid: '1',
      tagged: true,
      translation: 'hi',
    })
    const spy = jest
      .spyOn(SegmentActions, 'clickOnTranslatedButton')
      .mockReturnValue()
    SegmentActions.translateAndGoToNext()
    jest.runAllTimers()
    expect(spy).toHaveBeenCalled()
    spy.mockRestore()
  })
})

describe('SegmentActions.openSplitSegment', () => {
  beforeEach(() => {
    global.config = baseConfig()
    jest.clearAllMocks()
    OfflineUtils.offline = false
  })

  test('dispatches when online', () => {
    SegmentActions.openSplitSegment('1')
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({sid: '1'}),
    )
  })

  test('shows modal and returns when offline', () => {
    OfflineUtils.offline = true
    SegmentActions.openSplitSegment('1')
    expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
      'Alert',
      expect.any(Object),
      'Split disabled',
    )
    expect(AppDispatcher.dispatch).not.toHaveBeenCalled()
  })
})

describe('SegmentActions glossary flows', () => {
  beforeEach(() => {
    global.config = baseConfig()
    jest.clearAllMocks()
    CatToolStore.haveKeysGlossary = true
  })

  test('getGlossaryForSegment returns early without glossary keys', () => {
    CatToolStore.haveKeysGlossary = false
    SegmentActions.getGlossaryForSegment({sid: '1', fid: 2, text: 'hi'})
    expect(getGlossaryForSegment).not.toHaveBeenCalled()
  })

  test('getGlossaryForSegment refresh path calls api for current segment', () => {
    getGlossaryForSegment.mockReturnValue(Promise.resolve())
    SegmentActions.getGlossaryForSegment({
      sid: '1',
      fid: 2,
      text: 'hello',
      shouldRefresh: true,
    })
    expect(getGlossaryForSegment).toHaveBeenCalledWith(
      expect.objectContaining({idSegment: '1', source: 'hello'}),
    )
  })

  test('getGlossaryForSegment default path requests missing glossaries', () => {
    getGlossaryForSegment.mockReturnValue(Promise.resolve())
    SegmentStore.getNextSegment.mockReturnValue(null)
    SegmentStore.getSegmentByIdToJS.mockReturnValue({glossary: undefined})
    SegmentActions.getGlossaryForSegment({sid: '1', fid: 2, text: 'hi'})
    expect(getGlossaryForSegment).toHaveBeenCalled()
  })

  test('searchGlossary calls api and tracks target search flag', () => {
    getGlossaryMatch.mockReturnValue(Promise.resolve())
    SegmentActions.searchGlossary({
      idSegment: '1',
      sentence: 's',
      sourceLanguage: 'en',
      targetLanguage: 'it',
      isSearchingInTarget: true,
    })
    expect(SegmentStore.isSearchingGlossaryInTarget).toBe(true)
    expect(getGlossaryMatch).toHaveBeenCalled()
  })

  test('deleteGlossaryItem 403 notifies', async () => {
    const err = {status: 403}
    deleteGlossaryItem.mockRejectedValue(err)
    SegmentActions.deleteGlossaryItem({})
    await flushDynamicImports()
    expect(addNotification).toHaveBeenCalled()
    expect(AppDispatcher.dispatch).toHaveBeenCalled()
  })

  test('deleteGlossaryItem non-403 uses offline handler', async () => {
    deleteGlossaryItem.mockRejectedValue({status: 500})
    SegmentActions.deleteGlossaryItem({})
    await flushDynamicImports()
    expect(OfflineUtils.failedConnection).toHaveBeenCalled()
  })

  test('addGlossaryItem length>0 dispatches footer message', async () => {
    const err = [{message: 'bad'}]
    err.status = undefined
    addGlossaryItem.mockRejectedValue(err)
    SegmentActions.addGlossaryItem({id_segment: '1'})
    await flushDynamicImports()
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({message: 'bad'}),
    )
  })

  test('addGlossaryItem 403 notifies', async () => {
    addGlossaryItem.mockRejectedValue({status: 403})
    SegmentActions.addGlossaryItem({id_segment: '1'})
    await flushDynamicImports()
    expect(addNotification).toHaveBeenCalled()
  })

  test('addGlossaryItem empty error uses offline handler', async () => {
    const err = []
    addGlossaryItem.mockRejectedValue(err)
    SegmentActions.addGlossaryItem({id_segment: '1'})
    await flushDynamicImports()
    expect(OfflineUtils.failedConnection).toHaveBeenCalled()
  })

  test('updateGlossaryItem 403 notifies and non-403 offline', async () => {
    updateGlossaryItem.mockRejectedValueOnce({status: 403})
    SegmentActions.updateGlossaryItem({})
    await flushDynamicImports()
    expect(addNotification).toHaveBeenCalled()

    updateGlossaryItem.mockRejectedValueOnce({status: 500})
    SegmentActions.updateGlossaryItem({})
    await flushDynamicImports()
    expect(OfflineUtils.failedConnection).toHaveBeenCalled()
  })
})

describe('SegmentActions issues panel', () => {
  beforeEach(() => {
    global.config = baseConfig()
    jest.clearAllMocks()
  })

  test('openIssuesPanel returns false when segment cannot be opened', () => {
    SegmentStore.getSegmentByIdToJS.mockReturnValue({status: 'NEW'})
    expect(SegmentActions.openIssuesPanel({sid: '1'}, false)).toBe(false)
  })

  test('openIssuesPanel opens segment and dispatches when allowed', () => {
    jest.useFakeTimers()
    SegmentStore.getSegmentByIdToJS.mockReturnValue({status: 'TRANSLATED'})
    const openSpy = jest.spyOn(SegmentActions, 'openSegment').mockReturnValue()
    const scrollSpy = jest
      .spyOn(SegmentActions, 'scrollToSegment')
      .mockReturnValue()
    SegmentActions.openIssuesPanel({sid: '1'}, true)
    jest.runAllTimers()
    expect(openSpy).toHaveBeenCalledWith('1')
    expect(scrollSpy).toHaveBeenCalled()
    expect(AppDispatcher.dispatch).toHaveBeenCalled()
    openSpy.mockRestore()
    scrollSpy.mockRestore()
    jest.useRealTimers()
  })

  test('closeIssuesPanel dispatches and writes localStorage', () => {
    SegmentActions.closeIssuesPanel()
    expect(AppDispatcher.dispatch).toHaveBeenCalled()
  })

  test('closeSegmentIssuePanel dispatches and scrolls', () => {
    const scrollSpy = jest
      .spyOn(SegmentActions, 'scrollToSegment')
      .mockReturnValue()
    SegmentActions.closeSegmentIssuePanel('1')
    expect(scrollSpy).toHaveBeenCalledWith('1')
    scrollSpy.mockRestore()
  })
})

describe('SegmentActions issues api flows', () => {
  beforeEach(() => {
    global.config = baseConfig()
    jest.clearAllMocks()
  })

  test('getSegmentVersionsIssues adds issues on success when segment exists', async () => {
    SegmentStore.getSegmentByIdToJS.mockReturnValue({sid: '1'})
    getSegmentVersionsIssues.mockResolvedValue({versions: [{}]})
    const addSpy = jest
      .spyOn(SegmentActions, 'addTranslationIssuesToSegment')
      .mockReturnValue()
    SegmentActions.getSegmentVersionsIssues('1')
    await flushDynamicImports()
    expect(addSpy).toHaveBeenCalledWith('1', [{}])
    addSpy.mockRestore()
  })

  test('getSegmentVersionsIssues does nothing when segment missing', () => {
    SegmentStore.getSegmentByIdToJS.mockReturnValue(undefined)
    SegmentActions.getSegmentVersionsIssues('1')
    expect(getSegmentVersionsIssues).not.toHaveBeenCalled()
  })

  test('addPreloadedIssuesToSegment groups issues by segment and dispatches', async () => {
    getSegmentsIssues.mockResolvedValue({
      issues: [{id_segment: '1'}, {id_segment: '1'}, {id_segment: '2'}],
    })
    SegmentActions.addPreloadedIssuesToSegment()
    await flushDynamicImports()
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({
        versionsIssues: expect.objectContaining({
          1: [{id_segment: '1'}, {id_segment: '1'}],
          2: [{id_segment: '2'}],
        }),
      }),
    )
  })

  test('deleteIssue confirms deletion and reloads report', async () => {
    deleteSegmentIssue.mockResolvedValue()
    const confirmSpy = jest
      .spyOn(SegmentActions, 'confirmDeletedIssue')
      .mockReturnValue()
    const versionsSpy = jest
      .spyOn(SegmentActions, 'getSegmentVersionsIssues')
      .mockReturnValue()
    SegmentActions.deleteIssue({id: 9}, '1')
    await flushDynamicImports()
    expect(confirmSpy).toHaveBeenCalledWith('1', 9)
    expect(versionsSpy).toHaveBeenCalledWith('1')
    expect(CatToolActions.reloadQualityReport).toHaveBeenCalled()
    confirmSpy.mockRestore()
    versionsSpy.mockRestore()
  })

  test('submitIssueComment posts and refreshes versions', async () => {
    sendSegmentVersionIssueComment.mockResolvedValue()
    const versionsSpy = jest
      .spyOn(SegmentActions, 'getSegmentVersionsIssues')
      .mockReturnValue()
    await SegmentActions.submitIssueComment('1', 9, {})
    expect(versionsSpy).toHaveBeenCalledWith('1')
    versionsSpy.mockRestore()
  })
})

describe('SegmentActions bulk approve/translate', () => {
  beforeEach(() => {
    global.config = baseConfig()
    jest.clearAllMocks()
  })

  test('approveFilteredSegments approves small batches', async () => {
    approveSegments.mockResolvedValue({unchangeble_segments: []})
    const promise = SegmentActions.approveFilteredSegments([1, 2])
    await promise
    expect(approveSegments).toHaveBeenCalledWith([1, 2])
  })

  test('approveFilteredSegments recurses for large batches', async () => {
    approveSegments.mockResolvedValue({unchangeble_segments: []})
    const arr = Array.from({length: 150}, (_, i) => i)
    const promise = SegmentActions.approveFilteredSegments(arr)
    await promise
    expect(approveSegments).toHaveBeenCalled()
  })

  test('translateFilteredSegments translates small batches', async () => {
    translateSegments.mockResolvedValue({unchangeble_segments: []})
    await SegmentActions.translateFilteredSegments([1, 2])
    expect(translateSegments).toHaveBeenCalledWith([1, 2])
  })

  test('translateFilteredSegments recurses for large batches', async () => {
    translateSegments.mockResolvedValue({unchangeble_segments: []})
    const arr = Array.from({length: 150}, (_, i) => i)
    await SegmentActions.translateFilteredSegments(arr)
    expect(translateSegments).toHaveBeenCalled()
  })

  test('checkUnchangebleSegments shows translate warning when not review', () => {
    const spy = jest
      .spyOn(SegmentActions, 'showTranslateAllModalWarnirng')
      .mockReturnValue()
    SegmentActions.checkUnchangebleSegments({unchangeble_segments: [1]})
    expect(spy).toHaveBeenCalled()
    spy.mockRestore()
  })

  test('checkUnchangebleSegments shows approve warning when review', () => {
    global.config.isReview = true
    const spy = jest
      .spyOn(SegmentActions, 'showApproveAllModalWarnirng')
      .mockReturnValue()
    SegmentActions.checkUnchangebleSegments({unchangeble_segments: [1]})
    expect(spy).toHaveBeenCalled()
    spy.mockRestore()
  })

  test('showApproveAllModalWarnirng and showTranslateAllModalWarnirng show modals with callbacks', () => {
    SegmentActions.showApproveAllModalWarnirng()
    SegmentActions.showTranslateAllModalWarnirng()
    const calls = ModalsActions.showModalComponent.mock.calls
    calls.forEach((c) => c[1].successCallback && c[1].successCallback())
    expect(ModalsActions.showModalComponent).toHaveBeenCalledTimes(2)
  })

  test('bulkChangeStatusCallback updates present segments', () => {
    SegmentStore.getSegmentByIdToJS.mockReturnValue({id_file: 7})
    const statusSpy = jest.spyOn(SegmentActions, 'setStatus').mockReturnValue()
    SegmentActions.bulkChangeStatusCallback(['1'], 'TRANSLATED')
    expect(statusSpy).toHaveBeenCalledWith('1', 7, 'TRANSLATED')
    statusSpy.mockRestore()
  })

  test('bulkChangeStatusCallback ignores empty array', () => {
    const statusSpy = jest.spyOn(SegmentActions, 'setStatus').mockReturnValue()
    SegmentActions.bulkChangeStatusCallback([], 'TRANSLATED')
    expect(statusSpy).not.toHaveBeenCalled()
    statusSpy.mockRestore()
  })
})

describe('SegmentActions lock/unlock', () => {
  beforeEach(() => {
    global.config = baseConfig()
    jest.clearAllMocks()
    SegmentStore.consecutiveUnlockSegments = []
    localStorage.clear()
    SegmentUtils.isSecondPassLockedSegment = jest.fn(() => false)
    SegmentUtils.addUnlockedSegment = jest.fn()
  })

  test('setSegmentLocked when locking removes unlocked and toggles bulk', () => {
    SegmentActions.setSegmentLocked({sid: '1', inBulk: true}, 2, false)
    expect(SegmentUtils.removeUnlockedSegment).toHaveBeenCalledWith('1')
    expect(AppDispatcher.dispatch).toHaveBeenCalled()
  })

  test('setSegmentLocked when unlocking adds unlocked and opens segment', () => {
    const checkSpy = jest
      .spyOn(SegmentActions, 'checkUnlockAllSegmentsModal')
      .mockReturnValue()
    const openSpy = jest.spyOn(SegmentActions, 'openSegment').mockReturnValue()
    SegmentActions.setSegmentLocked({sid: '1'}, 2, true)
    expect(SegmentUtils.addUnlockedSegment).toHaveBeenCalledWith('1')
    expect(checkSpy).toHaveBeenCalled()
    expect(openSpy).toHaveBeenCalledWith('1')
    checkSpy.mockRestore()
    openSpy.mockRestore()
  })

  test('checkUnlockAllSegmentsModal shows modal after 3 consecutive unlocks', () => {
    SegmentStore.consecutiveUnlockSegments = ['a', 'b']
    SegmentActions.checkUnlockAllSegmentsModal({sid: 'c'})
    expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
      'UnlockAllSegments',
      {},
      'Unlock all 101% segments',
    )
  })

  test('checkUnlockAllSegmentsModal does nothing for second-pass locked segments', () => {
    SegmentUtils.isSecondPassLockedSegment = jest.fn(() => true)
    SegmentActions.checkUnlockAllSegmentsModal({sid: 'c'})
    expect(ModalsActions.showModalComponent).not.toHaveBeenCalled()
  })

  test('unlockSegments dispatches and registers each segment', () => {
    SegmentUtils.addUnlockedSegment = jest.fn()
    SegmentActions.unlockSegments(['1', '2'])
    expect(SegmentUtils.addUnlockedSegment).toHaveBeenCalledTimes(2)
    expect(AppDispatcher.dispatch).toHaveBeenCalled()
  })
})

describe('SegmentActions navigation', () => {
  beforeEach(() => {
    global.config = baseConfig()
    jest.clearAllMocks()
    SegmentFilterUtil.enabled.mockReturnValue(false)
    SegmentFilterUtil.filtering.mockReturnValue(false)
    SegmentFilterUtil.open = false
    SegmentStore.nextUntranslatedFromServer = null
  })

  test('gotoNextSegment opens next when available', () => {
    SegmentStore.getNextSegment.mockReturnValue({sid: '2'})
    const spy = jest.spyOn(SegmentActions, 'openSegment').mockReturnValue()
    SegmentActions.gotoNextSegment()
    expect(spy).toHaveBeenCalledWith('2')
    spy.mockRestore()
  })

  test('gotoNextSegment closes segment when no next', () => {
    SegmentStore.getNextSegment.mockReturnValue(null)
    const spy = jest.spyOn(SegmentActions, 'closeSegment').mockReturnValue()
    SegmentActions.gotoNextSegment()
    expect(spy).toHaveBeenCalled()
    spy.mockRestore()
  })

  test('gotoNextSegment delegates to filter util when filtering', () => {
    SegmentFilterUtil.enabled.mockReturnValue(true)
    SegmentFilterUtil.filtering.mockReturnValue(true)
    SegmentFilterUtil.open = true
    SegmentActions.gotoNextSegment()
    expect(SegmentFilterUtil.gotoNextSegment).toHaveBeenCalled()
  })

  test('gotoNextTranslatedSegment opens next translated', () => {
    SegmentStore.getNextSegment
      .mockReturnValueOnce({sid: '4'})
      .mockReturnValueOnce(null)
    const spy = jest.spyOn(SegmentActions, 'openSegment').mockReturnValue()
    SegmentActions.gotoNextTranslatedSegment('1')
    expect(spy).toHaveBeenCalledWith('4')
    spy.mockRestore()
  })

  test('gotoNextTranslatedSegment falls back to server next', () => {
    SegmentStore.getNextSegment.mockReturnValue(null)
    SegmentStore.nextUntranslatedFromServer = '8'
    const spy = jest.spyOn(SegmentActions, 'openSegment').mockReturnValue()
    SegmentActions.gotoNextTranslatedSegment('1')
    expect(spy).toHaveBeenCalledWith('8')
    spy.mockRestore()
  })

  test('gotoNextTranslatedSegment delegates to filter util when filtering', () => {
    SegmentFilterUtil.enabled.mockReturnValue(true)
    SegmentFilterUtil.filtering.mockReturnValue(true)
    SegmentFilterUtil.open = true
    SegmentActions.gotoNextTranslatedSegment('1')
    expect(SegmentFilterUtil.gotoNextTranslatedSegment).toHaveBeenCalledWith(
      '1',
    )
  })

  test('gotoNextUntranslatedSegment opens when a next id exists', () => {
    SegmentStore.getNextUntranslatedSegmentId.mockReturnValue('7')
    const spy = jest.spyOn(SegmentActions, 'openSegment').mockReturnValue()
    SegmentActions.gotoNextUntranslatedSegment()
    expect(spy).toHaveBeenCalledWith('7')
    spy.mockRestore()
  })

  test('setNextUntranslatedSegmentFromServer stores the sid', () => {
    SegmentActions.setNextUntranslatedSegmentFromServer('42')
    expect(SegmentStore.nextUntranslatedFromServer).toBe('42')
  })

  test('removeAllSegments resets current id and dispatches', () => {
    SegmentActions.removeAllSegments()
    expect(AppDispatcher.dispatch).toHaveBeenCalled()
  })
})

describe('SegmentActions AI tabs', () => {
  beforeEach(() => {
    global.config = baseConfig()
    jest.clearAllMocks()
    jest.useFakeTimers()
  })
  afterEach(() => jest.useRealTimers())

  test('helpAiAssistant activates tab and dispatches after timeout', () => {
    const visSpy = jest
      .spyOn(SegmentActions, 'modifyTabVisibility')
      .mockReturnValue()
    const tabSpy = jest.spyOn(SegmentActions, 'activateTab').mockReturnValue()
    SegmentActions.helpAiAssistant({sid: '1', value: 'v'})
    jest.runAllTimers()
    expect(visSpy).toHaveBeenCalledWith('AiAssistant', true)
    expect(tabSpy).toHaveBeenCalledWith('1', 'AiAssistant')
    expect(AppDispatcher.dispatch).toHaveBeenCalled()
    visSpy.mockRestore()
    tabSpy.mockRestore()
  })

  test('laraStylesTab / aiAlternativeTab / aiFeedbackTab activate their tabs', () => {
    jest.spyOn(SegmentActions, 'modifyTabVisibility').mockReturnValue()
    jest.spyOn(SegmentActions, 'activateTab').mockReturnValue()
    SegmentActions.laraStylesTab({sid: '1', styles: {}})
    SegmentActions.aiAlternativeTab({sid: '1', text: 't'})
    SegmentActions.aiFeedbackTab({sid: '1'})
    jest.runAllTimers()
    expect(AppDispatcher.dispatch).toHaveBeenCalledTimes(3)
  })
})

describe('SegmentActions.setCurrentSegment / mismatches', () => {
  beforeEach(() => {
    global.config = baseConfig()
    jest.clearAllMocks()
  })

  test('setCurrentSegment stores next segment and fetches mismatches when enabled', async () => {
    setCurrentSegment.mockResolvedValue({nextSegmentId: '5'})
    SegmentStore.getSegmentByIdToJS.mockReturnValue({alternatives: null})
    const mismatchSpy = jest
      .spyOn(SegmentActions, 'getTranslationMismatches')
      .mockReturnValue()
    SegmentActions.setCurrentSegment(3)
    await flushDynamicImports()
    expect(setLastSegmentFromLocalStorage).toHaveBeenCalledWith('3')
    expect(SegmentStore.nextUntranslatedFromServer).toBe('5')
    expect(mismatchSpy).toHaveBeenCalledWith(3)
    mismatchSpy.mockRestore()
  })

  test('setCurrentSegment handles missing segment gracefully', async () => {
    setCurrentSegment.mockResolvedValue({nextSegmentId: '5'})
    SegmentStore.getSegmentByIdToJS.mockReturnValue(undefined)
    const mismatchSpy = jest
      .spyOn(SegmentActions, 'getTranslationMismatches')
      .mockReturnValue()
    SegmentActions.setCurrentSegment(3)
    await flushDynamicImports()
    expect(mismatchSpy).not.toHaveBeenCalled()
    mismatchSpy.mockRestore()
  })

  test('setCurrentSegment failure triggers offline handler', async () => {
    setCurrentSegment.mockRejectedValue(new Error('x'))
    SegmentActions.setCurrentSegment(3)
    await flushDynamicImports()
    expect(OfflineUtils.failedConnection).toHaveBeenCalled()
  })

  test('getTranslationMismatches success detects alternatives', async () => {
    getTranslationMismatches.mockResolvedValue({
      data: {editable: [], not_editable: []},
    })
    const detectSpy = jest
      .spyOn(SegmentActions, 'detectTranslationAlternatives')
      .mockReturnValue()
    SegmentActions.getTranslationMismatches(3)
    await flushDynamicImports()
    expect(detectSpy).toHaveBeenCalled()
    detectSpy.mockRestore()
  })

  test('getTranslationMismatches error with codes processes errors', async () => {
    getTranslationMismatches.mockRejectedValue([{code: 1}])
    SegmentActions.getTranslationMismatches(3)
    await flushDynamicImports()
    expect(CatToolActions.processErrors).toHaveBeenCalled()
  })

  test('getTranslationMismatches empty error uses offline handler', async () => {
    getTranslationMismatches.mockRejectedValue([])
    SegmentActions.getTranslationMismatches(3)
    await flushDynamicImports()
    expect(OfflineUtils.failedConnection).toHaveBeenCalled()
  })

  test('detectTranslationAlternatives sets alternatives when new content present', () => {
    SegmentStore.getSegmentByIdToJS.mockReturnValue({translation: 'current'})
    const setAltSpy = jest
      .spyOn(SegmentActions, 'setAlternatives')
      .mockReturnValue()
    const tabSpy = jest.spyOn(SegmentActions, 'activateTab').mockReturnValue()
    const idxSpy = jest.spyOn(SegmentActions, 'setTabIndex').mockReturnValue()
    const d = {
      data: {
        editable: [{translation: 'current'}, {translation: 'new'}],
        not_editable: [{translation: 'another'}],
      },
    }
    SegmentActions.detectTranslationAlternatives(d, 3)
    expect(setAltSpy).toHaveBeenCalled()
    expect(tabSpy).toHaveBeenCalledWith(3, 'alternatives')
    expect(idxSpy).toHaveBeenCalled()
    setAltSpy.mockRestore()
    tabSpy.mockRestore()
    idxSpy.mockRestore()
  })
})

describe('SegmentActions.refreshTagMap', () => {
  beforeEach(() => {
    global.config = baseConfig()
    jest.clearAllMocks()
  })

  test('re-renders segments and dispatches refresh', () => {
    SegmentStore._segments = {toJS: jest.fn(() => [{sid: '1'}])}
    SegmentStore.getCurrentSegment.mockReturnValue({sid: '1'})
    const renderSpy = jest
      .spyOn(SegmentActions, 'renderSegments')
      .mockReturnValue()
    SegmentActions.refreshTagMap()
    expect(renderSpy).toHaveBeenCalledWith([{sid: '1'}], '1')
    expect(AppDispatcher.dispatch).toHaveBeenCalled()
    renderSpy.mockRestore()
  })
})
