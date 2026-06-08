import React from 'react'
import {render} from '@testing-library/react'

const mockIsReadonlySegment = jest.fn()

jest.mock('../../stores/SegmentStore', () => ({
  getCurrentSegmentId: jest.fn(),
  getSegmentByIdToJS: jest.fn(),
  addListener: jest.fn(),
  removeListener: jest.fn(),
  segmentHasIssues: jest.fn(() => false),
}))

jest.mock('../../actions/SegmentActions', () => ({
  saveSegmentBeforeClose: jest.fn(),
  localStorageCommentsClosed: 'comments_closed',
}))

jest.mock('../../stores/CatToolStore', () => ({
  addListener: jest.fn(),
  removeListener: jest.fn(),
}))

jest.mock('../../stores/CommentsStore', () => ({
  db: {},
  addListener: jest.fn(),
  removeListener: jest.fn(),
}))

jest.mock('../../constants/SegmentConstants', () => ({
  ADD_SEGMENT_CLASS: 'ADD_SEGMENT_CLASS',
  REMOVE_SEGMENT_CLASS: 'REMOVE_SEGMENT_CLASS',
  SET_SEGMENT_STATUS: 'SET_SEGMENT_STATUS',
}))
jest.mock('../../constants/CatToolConstants', () => ({
  CLIENT_RECONNECTION: 'CLIENT_RECONNECTION',
}))
jest.mock('../../constants/Constants', () => ({
  SEGMENTS_STATUS: {},
}))

jest.mock('../../actions/ModalsActions', () => ({
  showModalComponent: jest.fn(),
}))

jest.mock('../../utils/segmentUtils', () => ({
  __esModule: true,
  default: {
    isReadonlySegment: (...args) => mockIsReadonlySegment(...args),
    isSecondPassLockedSegment: jest.fn(() => false),
    isUnlockedSegment: jest.fn(() => false),
    isIceSegment: jest.fn(() => false),
  },
}))

jest.mock('../../utils/speech2text', () => ({
  __esModule: true,
  default: {enabled: jest.fn(() => false)},
}))

jest.mock('../../utils/shortcuts', () => ({
  Shortcuts: {cattol: {events: {}}},
}))

jest.mock('./SegmentContext', () => {
  const ReactLib = require('react')
  return {SegmentContext: ReactLib.createContext({})}
})

jest.mock('../common/ApplicationWrapper/ApplicationWrapperContext', () => {
  const ReactLib = require('react')
  return {ApplicationWrapperContext: ReactLib.createContext({})}
})

jest.mock('./SegmentHeader', () => () => null)
jest.mock('./SegmentFooter', () => () => null)
jest.mock('./SegmentBody', () => () => null)
jest.mock('./SegmentsCommentsIcon', () => () => null)
jest.mock('./SegmentCommentsContainer', () => () => null)
jest.mock('../review_extended/ReviewExtendedPanel', () => () => null)
jest.mock('../review/TranslationIssuesSideButton', () => () => null)
jest.mock('./SegmentQAIcon', () => ({SegmentQAIcon: () => null}))

jest.mock('../header/cattol/segment_filter/segment_filter', () => ({
  __esModule: true,
  default: {enabled: jest.fn(() => false)},
}))
jest.mock('../header/cattol/search/searchUtils', () => ({
  __esModule: true,
  default: {getHighlightedElementData: jest.fn(() => null)},
}))

jest.mock('./utils/DraftMatecatUtils', () => ({
  __esModule: true,
  default: {
    checkXliffTagsInText: jest.fn(() => false),
    removeTagsFromText: jest.fn(() => 'text'),
  },
}))

jest.mock('lodash/array', () => ({
  union: jest.fn((...args) => [].concat(...args)),
}))

jest.mock('immutable', () => {
  const createImmutable = (value) => ({
    value,
    equals: (other) =>
      JSON.stringify(value) === JSON.stringify(other?.value ?? other),
    toJS: () => value,
  })
  return {
    __esModule: true,
    fromJS: jest.fn((v) => createImmutable(v)),
  }
})

jest.mock('../modals/ConfirmMessageModal', () => 'ConfirmMessageModal')

import Segment from './Segment'
import {fromJS} from 'immutable'

function makeSegment(overrides = {}) {
  return {
    sid: '10',
    original_sid: '10',
    segment: 'Source segment',
    translation: 'Translated segment',
    status: 'NEW',
    warnings: {},
    metadata: [],
    match_type: 'NO_MATCH',
    autopropagated_from: 0,
    repetitions_in_chunk: 1,
    split_group: [],
    opened: false,
    unlocked: false,
    readonly: false,
    ice_locked: false,
    tagged: false,
    ...overrides,
  }
}

function renderSegment(segment, extraProps = {}) {
  return render(
    <Segment
      segment={segment}
      segImmutable={fromJS(segment)}
      isReview={false}
      guessTagActive={false}
      speechToTextActive={false}
      files={{}}
      {...extraProps}
    />,
  )
}

describe('Segment readonly re-evaluation', () => {
  beforeEach(() => {
    window.React = React
    window.config = {
      id_job: 2,
      basepath: '/',
      password: 'test',
      isReview: false,
      project_completion_feature_enabled: false,
      segmentFilterEnabled: false,
      source_code: 'en-US',
      source_rfc: 'en-US',
      target_code: 'it-IT',
      target_rfc: 'it-IT',
      isSourceRTL: false,
      isTargetRTL: false,
      tag_projection_languages: '{}',
    }

    mockIsReadonlySegment.mockReset()
  })

  test('renders with readonly class when segment is disabled', () => {
    const segment = makeSegment({
      metadata: [{meta_key: 'translation_disabled', meta_value: '1'}],
    })
    mockIsReadonlySegment.mockReturnValue(true)

    const {container} = renderSegment(segment)

    expect(mockIsReadonlySegment).toHaveBeenCalledWith(segment)
    const section = container.querySelector('section')
    expect(section.className).toContain('readonly')
  })

  test('renders without readonly class when segment is enabled', () => {
    const segment = makeSegment()
    mockIsReadonlySegment.mockReturnValue(false)

    const {container} = renderSegment(segment)

    expect(mockIsReadonlySegment).toHaveBeenCalledWith(segment)
    const section = container.querySelector('section')
    expect(section.className).not.toContain('readonly')
  })

  test('updates readonly class when segment prop changes', () => {
    const segment = makeSegment()
    mockIsReadonlySegment.mockReturnValue(false)

    const {container, rerender} = renderSegment(segment)
    const section = container.querySelector('section')
    expect(section.className).not.toContain('readonly')

    const disabledSegment = makeSegment({
      metadata: [{meta_key: 'translation_disabled', meta_value: '1'}],
    })
    mockIsReadonlySegment.mockReturnValue(true)

    rerender(
      <Segment
        segment={disabledSegment}
        segImmutable={fromJS(disabledSegment)}
        isReview={false}
        guessTagActive={false}
        speechToTextActive={false}
        files={{}}
      />,
    )

    expect(section.className).toContain('readonly')
  })

  test('does not add readonly class when segment changes but readonly stays false', () => {
    const segment = makeSegment()
    mockIsReadonlySegment.mockReturnValue(false)

    const {container, rerender} = renderSegment(segment)
    const section = container.querySelector('section')
    expect(section.className).not.toContain('readonly')

    const updatedSegment = makeSegment({translation: 'Different translation'})
    mockIsReadonlySegment.mockReturnValue(false)

    rerender(
      <Segment
        segment={updatedSegment}
        segImmutable={fromJS(updatedSegment)}
        isReview={false}
        guessTagActive={false}
        speechToTextActive={false}
        files={{}}
      />,
    )

    expect(mockIsReadonlySegment).toHaveBeenCalledWith(updatedSegment)
    expect(section.className).not.toContain('readonly')
  })
})
