import React from 'react'

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

describe('Segment componentDidUpdate readonly re-evaluation', () => {
  let instance
  let setStateCalls

  beforeEach(() => {
    window.React = React
    window.config = {
      id_job: 2,
      basepath: '/',
      password: 'test',
      isReview: false,
      project_completion_feature_enabled: false,
      segmentFilterEnabled: false,
      source_rfc: 'en-US',
      target_rfc: 'it-IT',
      tag_projection_languages: '{}',
    }

    mockIsReadonlySegment.mockReset()
    setStateCalls = []

    const segment = makeSegment()
    instance = new Segment({
      segment,
      segImmutable: segment,
      isReview: false,
      guessTagActive: false,
      speechToTextActive: false,
      files: {},
    })
    // Override setState to capture calls since React's updater is a no-op on unmounted instances
    instance.setState = (arg) => setStateCalls.push(arg)
  })

  test('calls setState with readonly=true when segment changes and becomes disabled', () => {
    const prevSegment = makeSegment()
    const newSegment = makeSegment({
      metadata: [{meta_key: 'translation_disabled', meta_value: '1'}],
    })

    instance.props = {...instance.props, segment: newSegment}
    instance.state = {...instance.state, readonly: false}
    mockIsReadonlySegment.mockReturnValue(true)

    instance.componentDidUpdate({...instance.props, segment: prevSegment})

    expect(mockIsReadonlySegment).toHaveBeenCalledWith(newSegment)
    expect(setStateCalls).toContainEqual({readonly: true})
  })

  test('calls setState with readonly=false when segment changes and becomes enabled', () => {
    const prevSegment = makeSegment({
      metadata: [{meta_key: 'translation_disabled', meta_value: '1'}],
    })
    const newSegment = makeSegment({metadata: []})

    instance.props = {...instance.props, segment: newSegment}
    instance.state = {...instance.state, readonly: true}
    mockIsReadonlySegment.mockReturnValue(false)

    instance.componentDidUpdate({...instance.props, segment: prevSegment})

    expect(mockIsReadonlySegment).toHaveBeenCalledWith(newSegment)
    expect(setStateCalls).toContainEqual({readonly: false})
  })

  test('does not call setState when segment prop is unchanged', () => {
    const segment = makeSegment()

    instance.props = {...instance.props, segment}
    instance.state = {...instance.state, readonly: false}
    mockIsReadonlySegment.mockReturnValue(false)

    instance.componentDidUpdate({...instance.props, segment})

    const readonlyCalls = setStateCalls.filter((call) => 'readonly' in call)
    expect(readonlyCalls).toHaveLength(0)
  })

  test('does not call setState when readonly value has not changed', () => {
    const prevSegment = makeSegment()
    const newSegment = makeSegment({translation: 'Different translation'})

    instance.props = {...instance.props, segment: newSegment}
    instance.state = {...instance.state, readonly: false}
    mockIsReadonlySegment.mockReturnValue(false)

    instance.componentDidUpdate({...instance.props, segment: prevSegment})

    expect(mockIsReadonlySegment).toHaveBeenCalledWith(newSegment)
    const readonlyCalls = setStateCalls.filter((call) => 'readonly' in call)
    expect(readonlyCalls).toHaveLength(0)
  })
})
