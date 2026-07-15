jest.mock('../stores/SegmentStore', () => ({}))
jest.mock('../components/segments/utils/DraftMatecatUtils', () => ({}))
jest.mock('../constants/Constants', () => ({}))
jest.mock('../stores/UserStore', () => ({}))

import segmentUtils from './segmentUtils'

describe('segmentUtils.isReadonlySegment', () => {
  beforeEach(() => {
    global.config = {
      id_job: 2,
      project_completion_feature_enabled: false,
      isReview: false,
      job_completion_current_phase: '',
    }
  })

  test('returns true when metadata contains translation_disabled = true', () => {
    const segment = {
      readonly: false,
      metadata: [{meta_key: 'translation_disabled', meta_value: true}],
    }

    expect(segmentUtils.isReadonlySegment(segment)).toBe(true)
  })

  test('returns false when metadata is an empty array', () => {
    const segment = {
      readonly: false,
      metadata: [],
    }

    expect(segmentUtils.isReadonlySegment(segment)).toBe(false)
  })

  test('returns false when metadata does not contain translation_disabled key', () => {
    const segment = {
      readonly: false,
      metadata: [{meta_key: 'foo', meta_value: true}],
    }

    expect(segmentUtils.isReadonlySegment(segment)).toBe(false)
  })

  test('returns false when translation_disabled is present but value is false', () => {
    const segment = {
      readonly: false,
      metadata: [{meta_key: 'translation_disabled', meta_value: false}],
    }

    expect(segmentUtils.isReadonlySegment(segment)).toBe(false)
  })

  test('returns true when segment.readonly is true', () => {
    const segment = {
      readonly: true,
      metadata: [],
    }

    expect(segmentUtils.isReadonlySegment(segment)).toBe(true)
  })

  test('returns true when project completion is enabled and current phase is revise', () => {
    global.config.project_completion_feature_enabled = true
    global.config.isReview = false
    global.config.job_completion_current_phase = 'revise'

    const segment = {
      readonly: false,
      metadata: [],
    }

    expect(segmentUtils.isReadonlySegment(segment)).toBe(true)
  })
})
