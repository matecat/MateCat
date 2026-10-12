jest.mock('../components/segments/utils/DraftMatecatUtils/tagUtils', () => ({
  hasDataOriginalTags: jest.fn(),
}))
jest.mock('./segmentDispatchActions', () => ({
  setSegmentAsTagged: jest.fn(),
}))
jest.mock('../stores/SegmentStore', () => ({
  getCurrentSegment: jest.fn(),
}))
jest.mock('../utils/tagProjectionUtils', () => ({
  checkTPEnabled: jest.fn(),
}))

import {disableTPOnSegment} from './tagProjectionActions'
import {hasDataOriginalTags} from '../components/segments/utils/DraftMatecatUtils/tagUtils'
import {setSegmentAsTagged} from './segmentDispatchActions'
import SegmentStore from '../stores/SegmentStore'
import {checkTPEnabled} from '../utils/tagProjectionUtils'

describe('tagProjectionActions.disableTPOnSegment', () => {
  beforeEach(() => {
    jest.clearAllMocks()
  })

  test('does nothing when there is no current segment and none passed', () => {
    SegmentStore.getCurrentSegment.mockReturnValueOnce(undefined)

    disableTPOnSegment(undefined)

    expect(setSegmentAsTagged).not.toHaveBeenCalled()
  })

  test('sets segment as tagged when tag projection enabled and segment has original tags and is not tagged', () => {
    checkTPEnabled.mockReturnValueOnce(true)
    hasDataOriginalTags.mockReturnValueOnce(true)
    const segmentObj = {
      sid: 10,
      id_file: 5,
      tagged: false,
      segment: 'source text',
    }

    disableTPOnSegment(segmentObj)

    expect(setSegmentAsTagged).toHaveBeenCalledWith(10, 5)
  })

  test('does not set segment as tagged when checkTPEnabled is false', () => {
    checkTPEnabled.mockReturnValueOnce(false)
    hasDataOriginalTags.mockReturnValueOnce(true)
    const segmentObj = {sid: 10, id_file: 5, tagged: false, segment: 'source'}

    disableTPOnSegment(segmentObj)

    expect(setSegmentAsTagged).not.toHaveBeenCalled()
  })

  test('does not set segment as tagged when segment already tagged', () => {
    checkTPEnabled.mockReturnValueOnce(true)
    hasDataOriginalTags.mockReturnValueOnce(true)
    const segmentObj = {sid: 10, id_file: 5, tagged: true, segment: 'source'}

    disableTPOnSegment(segmentObj)

    expect(setSegmentAsTagged).not.toHaveBeenCalled()
  })

  test('uses SegmentStore current segment when no segmentObj passed', () => {
    checkTPEnabled.mockReturnValueOnce(true)
    hasDataOriginalTags.mockReturnValueOnce(true)
    SegmentStore.getCurrentSegment.mockReturnValueOnce({
      sid: 20,
      id_file: 3,
      tagged: false,
      segment: 'source',
    })

    disableTPOnSegment(undefined)

    expect(setSegmentAsTagged).toHaveBeenCalledWith(20, 3)
  })
})
