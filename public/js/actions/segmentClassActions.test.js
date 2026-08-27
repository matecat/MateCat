jest.mock('../stores/AppDispatcher', () => ({
  dispatch: jest.fn(),
}))

jest.mock('../constants/SegmentConstants', () => ({
  ADD_SEGMENT_CLASS: 'ADD_SEGMENT_CLASS',
  REMOVE_SEGMENT_CLASS: 'REMOVE_SEGMENT_CLASS',
}))

import {addClassToSegment, removeClassToSegment} from './segmentClassActions'
import AppDispatcher from '../stores/AppDispatcher'

describe('segmentClassActions', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    jest.useFakeTimers()
  })

  afterEach(() => {
    jest.useRealTimers()
  })

  test('addClassToSegment dispatches ADD_SEGMENT_CLASS after timeout', () => {
    addClassToSegment(1, 'highlighted')

    jest.runAllTimers()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'ADD_SEGMENT_CLASS',
      id: 1,
      newClass: 'highlighted',
    })
  })

  test('removeClassToSegment dispatches REMOVE_SEGMENT_CLASS after timeout when sid is set', () => {
    removeClassToSegment(1, 'highlighted')

    jest.runAllTimers()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'REMOVE_SEGMENT_CLASS',
      id: 1,
      className: 'highlighted',
    })
  })

  test('removeClassToSegment does nothing when sid is falsy', () => {
    removeClassToSegment(null, 'highlighted')

    jest.runAllTimers()

    expect(AppDispatcher.dispatch).not.toHaveBeenCalled()
  })
})
