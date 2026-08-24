import {fromJS} from 'immutable'
import SegmentStore from './SegmentStore'
import {SEGMENTS_STATUS} from '../constants/Constants'

describe('SegmentStore.getNextSegment', () => {
  afterEach(() => {
    SegmentStore._segments = fromJS([])
  })

  test('skips a segment with no status instead of throwing', () => {
    SegmentStore._segments = fromJS([
      {sid: 1, status: SEGMENTS_STATUS.TRANSLATED, readonly: 'false'},
      {sid: 2, readonly: 'false'},
      {sid: 3, status: SEGMENTS_STATUS.NEW, readonly: 'false'},
    ])

    let next
    expect(() => {
      next = SegmentStore.getNextSegment({
        current_sid: 1,
        status: SEGMENTS_STATUS.UNTRANSLATED,
        autopropagated: true,
      })
    }).not.toThrow()

    expect(next.sid).toBe(3)
  })
})
