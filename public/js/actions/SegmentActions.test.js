jest.mock('../stores/AppDispatcher', () => ({
  dispatch: jest.fn(),
  register: jest.fn(),
}))

jest.mock('../stores/SegmentStore', () => ({
  getCurrentSegmentId: jest.fn(),
  getSegmentByIdToJS: jest.fn(),
  consecutiveCopySourceNum: [],
}))

jest.mock('../stores/CatToolStore', () => ({
  getJobMetadata: jest.fn(),
}))

jest.mock('../utils/segmentUtils', () => ({
  isIceSegment: jest.fn(() => false),
  isReadonlySegment: jest.fn(),
}))

jest.mock('./CatToolActions', () => ({
  addNotification: jest.fn(),
  onRender: jest.fn(),
}))

jest.mock('./ModalsActions', () => ({
  showModalComponent: jest.fn(),
}))

jest.mock('../components/modals/AlertModal', () => 'AlertModal')
jest.mock('../components/modals/CopySourceModal', () => ({
  COPY_SOURCE_COOKIE: 'copy_source',
  __esModule: true,
  default: 'CopySourceModal',
}))
jest.mock('../components/modals/ConfirmMessageModal', () => 'ConfirmMessageModal')

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
  default: {},
}))

jest.mock('../components/segments/utils/DraftMatecatUtils', () => ({
  __esModule: true,
  default: {},
}))

jest.mock('../components/header/cattol/segment_filter/segment_filter', () => ({
  __esModule: true,
  default: {},
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
jest.mock('jquery', () => jest.fn(() => ({find: jest.fn()})))
jest.mock('lodash', () => ({
  each: jest.fn(),
  forEach: jest.fn(),
  isUndefined: (v) => typeof v === 'undefined',
}))
jest.mock('lodash/function', () => ({
  debounce: (fn) => fn,
}))
jest.mock('immutable', () => ({fromJS: jest.fn()}))
jest.mock('lodash/array', () => ({union: jest.fn()}))

jest.mock('../utils/speech2text', () => ({
  __esModule: true,
  default: {enabled: jest.fn(() => false)},
}))

import SegmentActions from './SegmentActions'
import SegmentUtils from '../utils/segmentUtils'
import ModalsActions from './ModalsActions'

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

  test('shows "Segment disabled" AlertModal when metadata has translation_disabled=1', () => {
    const segment = {
      unlocked: true,
      metadata: [{meta_key: 'translation_disabled', meta_value: '1'}],
    }

    SegmentActions.handleClickOnReadOnly(segment)

    expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
      'Alert',
      {
        text: 'This segment has been disabled by the project owner, so it cannot be translated.',
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
      'Alert',
      expect.objectContaining({
        text: 'This segment has been disabled by the project owner, so it cannot be translated.',
      }),
      'Segment disabled',
    )
  })

  test('shows ICE match modal when segment is ICE-locked', () => {
    SegmentUtils.isIceSegment.mockReturnValue(true)

    const segment = {
      unlocked: false,
      metadata: [{meta_key: 'translation_disabled', meta_value: '1'}],
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

  test('does not show disabled modal when translation_disabled is 0', () => {
    const segment = {
      unlocked: true,
      metadata: [{meta_key: 'translation_disabled', meta_value: '0'}],
    }

    SegmentActions.handleClickOnReadOnly(segment)

    expect(ModalsActions.showModalComponent).not.toHaveBeenCalledWith(
      'Alert',
      expect.objectContaining({
        text: 'This segment has been disabled by the project owner, so it cannot be translated.',
      }),
      'Segment disabled',
    )
  })
})
