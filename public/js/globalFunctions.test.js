jest.mock('./stores/SegmentStore', () => ({
  __esModule: true,
  default: {
    getPrevSegment: jest.fn(),
    getNextSegment: jest.fn(),
  },
}))

jest.mock('./utils/segmentUtils', () => ({
  __esModule: true,
  default: {
    collectSplittedTranslations: jest.fn(),
  },
}))

jest.mock('./actions/SegmentActions', () => ({
  __esModule: true,
  default: {
    registerTab: jest.fn(),
  },
}))

import globalFunctions from './globalFunctions'
import SegmentStore from './stores/SegmentStore'
import SegmentUtils from './utils/segmentUtils'
import SegmentActions from './actions/SegmentActions'

beforeEach(() => {
  jest.clearAllMocks()
})

describe('globalFunctions.getContextBefore', () => {
  test('returns null when there is no previous segment', () => {
    SegmentStore.getPrevSegment.mockReturnValueOnce(null)

    expect(globalFunctions.getContextBefore('10-1')).toBeNull()
    expect(SegmentStore.getPrevSegment).toHaveBeenCalledWith('10-1', true)
  })

  test('returns the segment text when the previous segment is not splitted', () => {
    SegmentStore.getPrevSegment.mockReturnValueOnce({
      splitted: false,
      segment: 'previous segment text',
      original_sid: '9',
    })

    expect(globalFunctions.getContextBefore('10')).toBe('previous segment text')
  })

  test('collects splitted translations when original_sid differs from the current segment id', () => {
    SegmentStore.getPrevSegment.mockReturnValueOnce({
      splitted: true,
      original_sid: '9',
      segment: 'unused',
    })
    SegmentUtils.collectSplittedTranslations.mockReturnValueOnce('collected source text')

    expect(globalFunctions.getContextBefore('10-2')).toBe('collected source text')
    expect(SegmentUtils.collectSplittedTranslations).toHaveBeenCalledWith('9', '.source')
  })

  test('recurses on getContextBefore when original_sid matches the current segment id', () => {
    SegmentStore.getPrevSegment
      .mockReturnValueOnce({
        splitted: 'recurse-id',
        original_sid: '10',
      })
      .mockReturnValueOnce({
        splitted: false,
        segment: 'final previous text',
      })

    expect(globalFunctions.getContextBefore('10-2')).toBe('final previous text')
    expect(SegmentStore.getPrevSegment).toHaveBeenCalledTimes(2)
    expect(SegmentStore.getPrevSegment).toHaveBeenNthCalledWith(2, 'recurse-id', true)
  })
})

describe('globalFunctions.getContextAfter', () => {
  test('returns null when there is no next segment', () => {
    SegmentStore.getNextSegment.mockReturnValueOnce(null)

    expect(globalFunctions.getContextAfter('10-1')).toBeNull()
    expect(SegmentStore.getNextSegment).toHaveBeenCalledWith({
      current_sid: '10-1',
      alsoMutedSegment: true,
    })
  })

  test('returns the segment text when the next segment is not splitted', () => {
    SegmentStore.getNextSegment.mockReturnValueOnce({
      splitted: false,
      segment: 'next segment text',
    })

    expect(globalFunctions.getContextAfter('10')).toBe('next segment text')
  })

  test('collects splitted translations when the next segment is the first of a split', () => {
    SegmentStore.getNextSegment.mockReturnValueOnce({
      splitted: true,
      firstOfSplit: true,
      original_sid: '11',
      sid: '11-1',
    })
    SegmentUtils.collectSplittedTranslations.mockReturnValueOnce('collected next source text')

    expect(globalFunctions.getContextAfter('10')).toBe('collected next source text')
    expect(SegmentUtils.collectSplittedTranslations).toHaveBeenCalledWith('11', '.source')
  })

  test('recurses on getContextAfter when the next segment is not the first of a split', () => {
    SegmentStore.getNextSegment
      .mockReturnValueOnce({
        splitted: true,
        firstOfSplit: false,
        sid: '11-2',
      })
      .mockReturnValueOnce({
        splitted: false,
        segment: 'final next text',
      })

    expect(globalFunctions.getContextAfter('10')).toBe('final next text')
    expect(SegmentStore.getNextSegment).toHaveBeenCalledTimes(2)
    expect(SegmentStore.getNextSegment).toHaveBeenNthCalledWith(2, {
      current_sid: '11-2',
      alsoMutedSegment: true,
    })
  })
})

describe('globalFunctions.getIdBefore', () => {
  test('returns null when there is no previous segment', () => {
    SegmentStore.getPrevSegment.mockReturnValueOnce(null)

    expect(globalFunctions.getIdBefore('10')).toBeNull()
  })

  test('returns the original_sid of the previous segment', () => {
    SegmentStore.getPrevSegment.mockReturnValueOnce({original_sid: '9'})

    expect(globalFunctions.getIdBefore('10')).toBe('9')
  })
})

describe('globalFunctions.getIdAfter', () => {
  test('returns null when there is no next segment', () => {
    SegmentStore.getNextSegment.mockReturnValueOnce(null)

    expect(globalFunctions.getIdAfter('10')).toBeNull()
  })

  test('returns the original_sid of the next segment', () => {
    SegmentStore.getNextSegment.mockReturnValueOnce({original_sid: '11'})

    expect(globalFunctions.getIdAfter('10')).toBe('11')
  })
})

describe('globalFunctions.registerFooterTabs', () => {
  test('registers the matches tab when translation_matches_enabled is true', () => {
    global.config = {translation_matches_enabled: true}

    globalFunctions.registerFooterTabs()

    expect(SegmentActions.registerTab).toHaveBeenCalledTimes(4)
    expect(SegmentActions.registerTab).toHaveBeenNthCalledWith(1, 'concordances', true, false)
    expect(SegmentActions.registerTab).toHaveBeenNthCalledWith(2, 'matches', true, true)
    expect(SegmentActions.registerTab).toHaveBeenNthCalledWith(3, 'glossary', true, false)
    expect(SegmentActions.registerTab).toHaveBeenNthCalledWith(4, 'alternatives', false, false)
  })

  test('does not register the matches tab when translation_matches_enabled is false', () => {
    global.config = {translation_matches_enabled: false}

    globalFunctions.registerFooterTabs()

    expect(SegmentActions.registerTab).toHaveBeenCalledTimes(3)
    expect(SegmentActions.registerTab).not.toHaveBeenCalledWith('matches', true, true)
  })
})
