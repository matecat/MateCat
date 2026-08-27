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

describe('SegmentStore.getSegmentIndex', () => {
  afterEach(() => {
    SegmentStore._segments = fromJS([])
  })

  test('returns -1 instead of throwing when sid is undefined', () => {
    SegmentStore._segments = fromJS([{sid: 1}, {sid: 2}])

    let index
    expect(() => {
      index = SegmentStore.getSegmentIndex(undefined)
    }).not.toThrow()

    expect(index).toBe(-1)
  })

  test('finds the matching segment by numeric sid', () => {
    SegmentStore._segments = fromJS([{sid: 1}, {sid: 2}])

    expect(SegmentStore.getSegmentIndex(2)).toBe(1)
  })
})
