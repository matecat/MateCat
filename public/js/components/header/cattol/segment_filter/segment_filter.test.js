import SegmentFilterUtils from './segment_filter'
import SegmentStore from '../../../../stores/SegmentStore'
import SegmentActions from '../../../../actions/SegmentActions'

jest.mock('../../../../stores/SegmentStore', () => ({
  getCurrentSegment: jest.fn(),
}))
jest.mock('../../../../actions/SegmentActions', () => ({
  openSegment: jest.fn(),
}))
jest.mock('../../../../setTranslationUtil', () => ({
  segmentTranslation: jest.fn((segment, status, callback) => callback()),
}))

describe('SegmentFilterUtils.goToNextRepetitionGroup', () => {
  afterEach(() => {
    SegmentFilterUtils.cachedStoredState = null
    jest.clearAllMocks()
  })

  test('does not throw and does not open a segment when there is no repetition group', () => {
    SegmentStore.getCurrentSegment.mockReturnValue({
      sid: 1,
      segment_hash: 'abc',
    })
    SegmentFilterUtils.cachedStoredState = {serverData: {grouping: {}}}

    expect(() =>
      SegmentFilterUtils.goToNextRepetitionGroup('translated'),
    ).not.toThrow()

    expect(SegmentActions.openSegment).not.toHaveBeenCalled()
  })

  test('opens the first item of the next repetition group', () => {
    SegmentStore.getCurrentSegment.mockReturnValue({sid: 1, segment_hash: 'a'})
    SegmentFilterUtils.cachedStoredState = {
      serverData: {grouping: {a: [1], b: [2, 3]}},
    }

    SegmentFilterUtils.goToNextRepetitionGroup('translated')

    expect(SegmentActions.openSegment).toHaveBeenCalledWith(2)
  })
})
