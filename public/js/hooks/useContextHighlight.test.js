import {renderHook, act} from '@testing-library/react'
import useContextHighlight from './useContextHighlight'

// ---------------------------------------------------------------------------
// Mocks
// ---------------------------------------------------------------------------

jest.mock('../utils/contextPreviewUtils', () => ({
  clearHighlights: jest.fn(),
  highlightBySid: jest.fn(),
  setActiveHighlight: jest.fn(),
  getSegmentNodeMap: jest.fn(),
  isNodeHidden: jest.fn(),
}))

jest.mock('../utils/contextPreviewChannel', () => ({
  sendMessage: jest.fn(),
}))

const {
  clearHighlights,
  highlightBySid,
  setActiveHighlight,
  getSegmentNodeMap,
  isNodeHidden,
} = require('../utils/contextPreviewUtils')

const ContextPreviewChannel = require('../utils/contextPreviewChannel')

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const makeRef = () => ({current: document.createElement('div')})
const nullRef = () => ({current: null})

beforeEach(() => {
  jest.clearAllMocks()
  highlightBySid.mockReturnValue({total: 0, marks: []})
  setActiveHighlight.mockReturnValue(null)
  getSegmentNodeMap.mockReturnValue(null)
  isNodeHidden.mockReturnValue(false)
})

// ---------------------------------------------------------------------------
// setHighlight
// ---------------------------------------------------------------------------

describe('setHighlight', () => {
  it('updates state and keeps highlightRef in sync when called with a value', () => {
    const {result} = renderHook(() =>
      useContextHighlight({sourceRef: nullRef(), targetRef: nullRef()}),
    )

    act(() => {
      result.current.setHighlight({mode: 'segment', sid: 1, activeIndex: 0, total: 3})
    })

    expect(result.current.highlight).toEqual({
      mode: 'segment',
      sid: 1,
      activeIndex: 0,
      total: 3,
    })
    expect(result.current.highlightRef.current).toEqual(result.current.highlight)
  })

  it('accepts an updater function', () => {
    const {result} = renderHook(() =>
      useContextHighlight({sourceRef: nullRef(), targetRef: nullRef()}),
    )

    act(() => {
      result.current.setHighlight({mode: 'segment', sid: 1, activeIndex: 0, total: 3})
    })
    act(() => {
      result.current.setHighlight((prev) => ({...prev, activeIndex: 1}))
    })

    expect(result.current.highlight.activeIndex).toBe(1)
    expect(result.current.highlightRef.current.activeIndex).toBe(1)
  })

  it('sets highlight to null', () => {
    const {result} = renderHook(() =>
      useContextHighlight({sourceRef: nullRef(), targetRef: nullRef()}),
    )

    act(() => {
      result.current.setHighlight({mode: 'segment', sid: 1, activeIndex: 0, total: 1})
    })
    act(() => {
      result.current.setHighlight(null)
    })

    expect(result.current.highlight).toBeNull()
    expect(result.current.highlightRef.current).toBeNull()
  })
})

// ---------------------------------------------------------------------------
// applyHighlightsForSegment
// ---------------------------------------------------------------------------

describe('applyHighlightsForSegment', () => {
  it('clears and reapplies highlights on both panels, returns total from source', () => {
    highlightBySid.mockReturnValue({total: 3, marks: []})
    const sourceRef = makeRef()
    const targetRef = makeRef()

    const {result} = renderHook(() =>
      useContextHighlight({sourceRef, targetRef}),
    )

    let total
    act(() => {
      total = result.current.applyHighlightsForSegment(42, 0, false)
    })

    expect(clearHighlights).toHaveBeenCalledWith(sourceRef.current)
    expect(clearHighlights).toHaveBeenCalledWith(targetRef.current)
    expect(highlightBySid).toHaveBeenCalledWith(sourceRef.current, 42, 0)
    expect(highlightBySid).toHaveBeenCalledWith(targetRef.current, 42, 0)
    expect(total).toBe(3)
  })

  it('skips null refs', () => {
    highlightBySid.mockReturnValue({total: 2, marks: []})
    const {result} = renderHook(() =>
      useContextHighlight({sourceRef: nullRef(), targetRef: nullRef()}),
    )

    let total
    act(() => {
      total = result.current.applyHighlightsForSegment(1, 0, false)
    })

    expect(clearHighlights).not.toHaveBeenCalled()
    expect(total).toBe(0)
  })

  it('falls back to target total when source total is 0', () => {
    highlightBySid
      .mockReturnValueOnce({total: 0, marks: []})
      .mockReturnValueOnce({total: 5, marks: []})
    const sourceRef = makeRef()
    const targetRef = makeRef()

    const {result} = renderHook(() =>
      useContextHighlight({sourceRef, targetRef}),
    )

    let total
    act(() => {
      total = result.current.applyHighlightsForSegment(7, 0, false)
    })

    expect(total).toBe(5)
  })

  it('calls scrollIntoView on the active mark when scroll=true', () => {
    const mockMark = {scrollIntoView: jest.fn()}
    highlightBySid.mockReturnValue({total: 1, marks: [[mockMark]]})
    const sourceRef = makeRef()

    const {result} = renderHook(() =>
      useContextHighlight({sourceRef, targetRef: nullRef()}),
    )

    act(() => {
      result.current.applyHighlightsForSegment(1, 0, true)
    })

    expect(mockMark.scrollIntoView).toHaveBeenCalledWith({
      behavior: 'smooth',
      block: 'center',
    })
  })
})

// ---------------------------------------------------------------------------
// applyHighlightsForNode
// ---------------------------------------------------------------------------

describe('applyHighlightsForNode', () => {
  it('resolves SIDs from the node map and highlights the active one', () => {
    const el = document.createElement('div')
    const mockMap = {
      nodeIndexToSids: new Map([[0, [10, 20]]]),
      nodes: [el],
    }
    getSegmentNodeMap.mockReturnValue(mockMap)
    highlightBySid.mockReturnValue({total: 1, marks: []})

    const sourceRef = makeRef()
    const {result} = renderHook(() =>
      useContextHighlight({sourceRef, targetRef: nullRef()}),
    )

    act(() => {
      result.current.applyHighlightsForNode(0, 1, false)
    })

    expect(highlightBySid).toHaveBeenCalledWith(sourceRef.current, 20, 0)
  })

  it('falls back to sids[0] when activeSegIdx is out of range', () => {
    const el = document.createElement('div')
    const mockMap = {
      nodeIndexToSids: new Map([[0, [99]]]),
      nodes: [el],
    }
    getSegmentNodeMap.mockReturnValue(mockMap)
    highlightBySid.mockReturnValue({total: 1, marks: []})

    const sourceRef = makeRef()
    const {result} = renderHook(() =>
      useContextHighlight({sourceRef, targetRef: nullRef()}),
    )

    act(() => {
      result.current.applyHighlightsForNode(0, 5, false)
    })

    expect(highlightBySid).toHaveBeenCalledWith(sourceRef.current, 99, 0)
  })

  it('does nothing when nodeIndex has no SIDs', () => {
    const mockMap = {
      nodeIndexToSids: new Map(),
      nodes: [],
    }
    getSegmentNodeMap.mockReturnValue(mockMap)

    const sourceRef = makeRef()
    const {result} = renderHook(() =>
      useContextHighlight({sourceRef, targetRef: nullRef()}),
    )

    act(() => {
      result.current.applyHighlightsForNode(99, 0, false)
    })

    expect(highlightBySid).not.toHaveBeenCalled()
  })
})

// ---------------------------------------------------------------------------
// navigateHighlight — segment mode
// ---------------------------------------------------------------------------

describe('navigateHighlight — segment mode', () => {
  it('advances activeIndex forward and wraps around', () => {
    setActiveHighlight.mockReturnValue(null)
    const sourceRef = makeRef()
    const targetRef = makeRef()

    const {result} = renderHook(() =>
      useContextHighlight({sourceRef, targetRef}),
    )

    act(() => {
      result.current.setHighlight({
        mode: 'segment',
        sid: 1,
        activeIndex: 1,
        total: 3,
      })
    })
    act(() => {
      result.current.handleNext()
    })

    expect(result.current.highlight.activeIndex).toBe(2)
    expect(setActiveHighlight).toHaveBeenCalledWith(sourceRef.current, 2)
    expect(setActiveHighlight).toHaveBeenCalledWith(targetRef.current, 2)
  })

  it('wraps from last to first on next', () => {
    setActiveHighlight.mockReturnValue(null)
    const sourceRef = makeRef()
    const {result} = renderHook(() =>
      useContextHighlight({sourceRef, targetRef: nullRef()}),
    )

    act(() => {
      result.current.setHighlight({
        mode: 'segment',
        sid: 1,
        activeIndex: 2,
        total: 3,
      })
    })
    act(() => {
      result.current.handleNext()
    })

    expect(result.current.highlight.activeIndex).toBe(0)
  })

  it('goes backward with handlePrev', () => {
    setActiveHighlight.mockReturnValue(null)
    const sourceRef = makeRef()
    const {result} = renderHook(() =>
      useContextHighlight({sourceRef, targetRef: nullRef()}),
    )

    act(() => {
      result.current.setHighlight({
        mode: 'segment',
        sid: 1,
        activeIndex: 1,
        total: 3,
      })
    })
    act(() => {
      result.current.handlePrev()
    })

    expect(result.current.highlight.activeIndex).toBe(0)
  })

  it('wraps from first to last on prev', () => {
    setActiveHighlight.mockReturnValue(null)
    const sourceRef = makeRef()
    const {result} = renderHook(() =>
      useContextHighlight({sourceRef, targetRef: nullRef()}),
    )

    act(() => {
      result.current.setHighlight({
        mode: 'segment',
        sid: 1,
        activeIndex: 0,
        total: 3,
      })
    })
    act(() => {
      result.current.handlePrev()
    })

    expect(result.current.highlight.activeIndex).toBe(2)
  })

  it('does nothing when highlight is null', () => {
    const {result} = renderHook(() =>
      useContextHighlight({sourceRef: nullRef(), targetRef: nullRef()}),
    )

    act(() => {
      result.current.handleNext()
    })

    expect(setActiveHighlight).not.toHaveBeenCalled()
  })

  it('scrolls to the mark when setActiveHighlight returns one', () => {
    const mockMark = {scrollIntoView: jest.fn()}
    setActiveHighlight.mockReturnValue(mockMark)
    const sourceRef = makeRef()

    const {result} = renderHook(() =>
      useContextHighlight({sourceRef, targetRef: nullRef()}),
    )

    act(() => {
      result.current.setHighlight({
        mode: 'segment',
        sid: 1,
        activeIndex: 0,
        total: 2,
      })
    })
    act(() => {
      result.current.handleNext()
    })

    expect(mockMark.scrollIntoView).toHaveBeenCalledWith({
      behavior: 'smooth',
      block: 'center',
    })
  })
})

// ---------------------------------------------------------------------------
// navigateHighlight — node mode
// ---------------------------------------------------------------------------

describe('navigateHighlight — node mode', () => {
  it('advances activeSegIdx and sends segmentClicked', () => {
    getSegmentNodeMap.mockReturnValue(null)
    highlightBySid.mockReturnValue({total: 1, marks: []})

    const sourceRef = makeRef()
    const {result} = renderHook(() =>
      useContextHighlight({sourceRef, targetRef: nullRef()}),
    )

    act(() => {
      result.current.setHighlight({
        mode: 'node',
        nodeIndex: 0,
        sids: [10, 20, 30],
        activeSegIdx: 0,
      })
    })
    act(() => {
      result.current.handleNext()
    })

    expect(result.current.highlight.activeSegIdx).toBe(1)
    expect(ContextPreviewChannel.sendMessage).toHaveBeenCalledWith({
      type: 'segmentClicked',
      sid: 20,
    })
  })

  it('wraps from last to first', () => {
    getSegmentNodeMap.mockReturnValue(null)
    const sourceRef = makeRef()
    const {result} = renderHook(() =>
      useContextHighlight({sourceRef, targetRef: nullRef()}),
    )

    act(() => {
      result.current.setHighlight({
        mode: 'node',
        nodeIndex: 0,
        sids: [10, 20],
        activeSegIdx: 1,
      })
    })
    act(() => {
      result.current.handleNext()
    })

    expect(result.current.highlight.activeSegIdx).toBe(0)
    expect(ContextPreviewChannel.sendMessage).toHaveBeenCalledWith({
      type: 'segmentClicked',
      sid: 10,
    })
  })
})

// ---------------------------------------------------------------------------
// highlightHidden detection
// ---------------------------------------------------------------------------

describe('highlightHidden', () => {
  it('is false by default', () => {
    const {result} = renderHook(() =>
      useContextHighlight({sourceRef: nullRef(), targetRef: nullRef()}),
    )
    expect(result.current.highlightHidden).toBe(false)
  })

  it('is true when applyHighlightsForSegment detects a hidden mark', () => {
    const mockMark = {scrollIntoView: jest.fn()}
    highlightBySid.mockReturnValue({total: 1, marks: [[mockMark]]})
    isNodeHidden.mockReturnValue(true)
    const sourceRef = makeRef()

    const {result} = renderHook(() =>
      useContextHighlight({sourceRef, targetRef: nullRef()}),
    )

    act(() => {
      result.current.applyHighlightsForSegment(1, 0, true)
    })

    expect(result.current.highlightHidden).toBe(true)
    expect(mockMark.scrollIntoView).not.toHaveBeenCalled()
  })

  it('is false when mark is visible', () => {
    const mockMark = {scrollIntoView: jest.fn()}
    highlightBySid.mockReturnValue({total: 1, marks: [[mockMark]]})
    isNodeHidden.mockReturnValue(false)
    const sourceRef = makeRef()

    const {result} = renderHook(() =>
      useContextHighlight({sourceRef, targetRef: nullRef()}),
    )

    act(() => {
      result.current.applyHighlightsForSegment(1, 0, true)
    })

    expect(result.current.highlightHidden).toBe(false)
    expect(mockMark.scrollIntoView).toHaveBeenCalled()
  })

  it('resets to false when navigating to a visible occurrence', () => {
    const mockMark = {scrollIntoView: jest.fn()}
    highlightBySid.mockReturnValue({total: 2, marks: [[mockMark], [mockMark]]})
    isNodeHidden.mockReturnValueOnce(true)
    const sourceRef = makeRef()

    const {result} = renderHook(() =>
      useContextHighlight({sourceRef, targetRef: nullRef()}),
    )

    act(() => {
      result.current.applyHighlightsForSegment(1, 0, true)
    })
    expect(result.current.highlightHidden).toBe(true)

    isNodeHidden.mockReturnValue(false)
    setActiveHighlight.mockReturnValue(mockMark)

    act(() => {
      result.current.setHighlight({
        mode: 'segment',
        sid: 1,
        activeIndex: 0,
        total: 2,
      })
    })
    act(() => {
      result.current.handleNext()
    })

    expect(result.current.highlightHidden).toBe(false)
  })

  it('is true when navigateHighlight in segment mode hits a hidden mark', () => {
    const mockMark = {scrollIntoView: jest.fn()}
    setActiveHighlight.mockReturnValue(mockMark)
    isNodeHidden.mockReturnValue(true)
    const sourceRef = makeRef()

    const {result} = renderHook(() =>
      useContextHighlight({sourceRef, targetRef: nullRef()}),
    )

    act(() => {
      result.current.setHighlight({
        mode: 'segment',
        sid: 1,
        activeIndex: 0,
        total: 2,
      })
    })
    act(() => {
      result.current.handleNext()
    })

    expect(result.current.highlightHidden).toBe(true)
    expect(mockMark.scrollIntoView).not.toHaveBeenCalled()
  })
})
