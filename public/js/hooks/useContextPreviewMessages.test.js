import {renderHook, act} from '@testing-library/react'
import useContextPreviewMessages from './useContextPreviewMessages'

// ---------------------------------------------------------------------------
// Mocks
// ---------------------------------------------------------------------------

let messageListener = null

jest.mock('../utils/contextPreviewChannel', () => ({
  sendMessage: jest.fn(),
  onMessage: jest.fn((cb) => {
    messageListener = cb
    return () => {
      messageListener = null
    }
  }),
}))

jest.mock('../utils/contextPreviewUtils', () => ({
  getSegmentNodeMap: jest.fn(),
  getSidsFromElement: jest.fn(),
  replaceTextContent: jest.fn(),
  stripSegmentTags: jest.fn((s) => s),
  updateNodeTranslation: jest.fn(),
}))

const ContextPreviewChannel = require('../utils/contextPreviewChannel')
const utils = require('../utils/contextPreviewUtils')

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const nullRef = () => ({current: null})

const domRef = () => {
  const el = document.createElement('div')
  return {current: el}
}

const dispatch = (msg) => {
  act(() => {
    messageListener?.(msg)
  })
}

beforeEach(() => {
  jest.clearAllMocks()
  messageListener = null
  utils.getSegmentNodeMap.mockReturnValue(null)
  utils.updateNodeTranslation.mockReturnValue('ok')
})

// ---------------------------------------------------------------------------
// mount behavior
// ---------------------------------------------------------------------------

describe('mount', () => {
  it('sends requestSegments on mount', () => {
    renderHook(() =>
      useContextPreviewMessages({
        onHighlight: jest.fn(),
        onTranslationUpdate: jest.fn(),
        targetRef: nullRef(),
        showNodeWarning: jest.fn(),
        clearNodeWarning: jest.fn(),
      }),
    )

    expect(ContextPreviewChannel.sendMessage).toHaveBeenCalledWith({
      type: 'requestSegments',
    })
  })

  it('subscribes to onMessage and unsubscribes on unmount', () => {
    const {unmount} = renderHook(() =>
      useContextPreviewMessages({
        onHighlight: jest.fn(),
        onTranslationUpdate: jest.fn(),
        targetRef: nullRef(),
        showNodeWarning: jest.fn(),
        clearNodeWarning: jest.fn(),
      }),
    )

    expect(ContextPreviewChannel.onMessage).toHaveBeenCalledTimes(1)
    expect(messageListener).not.toBeNull()

    unmount()
    expect(messageListener).toBeNull()
  })
})

// ---------------------------------------------------------------------------
// segments message
// ---------------------------------------------------------------------------

describe('segments message', () => {
  it('appends new segments to state', () => {
    const {result} = renderHook(() =>
      useContextPreviewMessages({
        onHighlight: jest.fn(),
        onTranslationUpdate: jest.fn(),
        targetRef: nullRef(),
        showNodeWarning: jest.fn(),
        clearNodeWarning: jest.fn(),
      }),
    )

    dispatch({
      type: 'segments',
      segments: [
        {sid: 1, source: 'Hello', target: 'Hola', context_url: null},
        {sid: 2, source: 'World', target: 'Mundo', context_url: null},
      ],
    })

    expect(result.current.segments).toHaveLength(2)
    expect(result.current.segments[0].sid).toBe(1)
  })

  it('deduplicates segments by sid', () => {
    const {result} = renderHook(() =>
      useContextPreviewMessages({
        onHighlight: jest.fn(),
        onTranslationUpdate: jest.fn(),
        targetRef: nullRef(),
        showNodeWarning: jest.fn(),
        clearNodeWarning: jest.fn(),
      }),
    )

    dispatch({
      type: 'segments',
      segments: [{sid: 1, source: 'Hello', target: 'Hola', context_url: null}],
    })
    dispatch({
      type: 'segments',
      segments: [
        {sid: 1, source: 'Hello', target: 'Hola', context_url: null},
        {sid: 2, source: 'World', target: 'Mundo', context_url: null},
      ],
    })

    expect(result.current.segments).toHaveLength(2)
  })

  it('does not update state when all incoming segments already exist', () => {
    const {result} = renderHook(() =>
      useContextPreviewMessages({
        onHighlight: jest.fn(),
        onTranslationUpdate: jest.fn(),
        targetRef: nullRef(),
        showNodeWarning: jest.fn(),
        clearNodeWarning: jest.fn(),
      }),
    )

    const seg = {sid: 1, source: 'Hello', target: 'Hola', context_url: null}
    dispatch({type: 'segments', segments: [seg]})
    const snapBefore = result.current.segments

    dispatch({type: 'segments', segments: [seg]})

    expect(result.current.segments).toBe(snapBefore)
  })

  it('handles missing segments array gracefully', () => {
    const {result} = renderHook(() =>
      useContextPreviewMessages({
        onHighlight: jest.fn(),
        onTranslationUpdate: jest.fn(),
        targetRef: nullRef(),
        showNodeWarning: jest.fn(),
        clearNodeWarning: jest.fn(),
      }),
    )

    dispatch({type: 'segments'})

    expect(result.current.segments).toHaveLength(0)
  })
})

// ---------------------------------------------------------------------------
// highlight message
// ---------------------------------------------------------------------------

describe('highlight message', () => {
  it('calls onHighlight with numeric sid', () => {
    const onHighlight = jest.fn()
    const {result} = renderHook(() =>
      useContextPreviewMessages({
        onHighlight,
        onTranslationUpdate: jest.fn(),
        targetRef: nullRef(),
        showNodeWarning: jest.fn(),
        clearNodeWarning: jest.fn(),
      }),
    )

    dispatch({
      type: 'segments',
      segments: [{sid: 5, source: 'S', target: 'T', context_url: null}],
    })
    dispatch({type: 'highlight', sid: '5'})

    expect(onHighlight).toHaveBeenCalledWith(5, null)
  })

  it('sets currentContextUrl from matching segment', () => {
    const onHighlight = jest.fn()
    const {result} = renderHook(() =>
      useContextPreviewMessages({
        onHighlight,
        onTranslationUpdate: jest.fn(),
        targetRef: nullRef(),
        showNodeWarning: jest.fn(),
        clearNodeWarning: jest.fn(),
      }),
    )

    dispatch({
      type: 'segments',
      segments: [
        {sid: 3, source: 'S', target: 'T', context_url: 'https://example.com/doc.html'},
      ],
    })
    dispatch({type: 'highlight', sid: 3})

    expect(result.current.currentContextUrl).toBe('https://example.com/doc.html')
    expect(onHighlight).toHaveBeenCalledWith(3, 'https://example.com/doc.html')
  })

  it('passes null context_url when segment has no context_url', () => {
    const onHighlight = jest.fn()
    renderHook(() =>
      useContextPreviewMessages({
        onHighlight,
        onTranslationUpdate: jest.fn(),
        targetRef: nullRef(),
        showNodeWarning: jest.fn(),
        clearNodeWarning: jest.fn(),
      }),
    )

    dispatch({
      type: 'segments',
      segments: [{sid: 7, source: 'S', target: 'T'}],
    })
    dispatch({type: 'highlight', sid: 7})

    expect(onHighlight).toHaveBeenCalledWith(7, null)
  })

  it('passes null when highlighted sid is not in segments', () => {
    const onHighlight = jest.fn()
    renderHook(() =>
      useContextPreviewMessages({
        onHighlight,
        onTranslationUpdate: jest.fn(),
        targetRef: nullRef(),
        showNodeWarning: jest.fn(),
        clearNodeWarning: jest.fn(),
      }),
    )

    dispatch({type: 'highlight', sid: 99})

    expect(onHighlight).toHaveBeenCalledWith(99, null)
  })
})

// ---------------------------------------------------------------------------
// updateTranslation message
// ---------------------------------------------------------------------------

describe('updateTranslation message', () => {
  it('updates the target of the matching segment', () => {
    const {result} = renderHook(() =>
      useContextPreviewMessages({
        onHighlight: jest.fn(),
        onTranslationUpdate: jest.fn(),
        targetRef: nullRef(),
        showNodeWarning: jest.fn(),
        clearNodeWarning: jest.fn(),
      }),
    )

    dispatch({
      type: 'segments',
      segments: [{sid: 1, source: 'Hello', target: '', context_url: null}],
    })
    dispatch({type: 'updateTranslation', sid: 1, target: 'Hola'})

    expect(result.current.segments[0].target).toBe('Hola')
  })

  it('calls onTranslationUpdate with updated segments', () => {
    const onTranslationUpdate = jest.fn()
    renderHook(() =>
      useContextPreviewMessages({
        onHighlight: jest.fn(),
        onTranslationUpdate,
        targetRef: nullRef(),
        showNodeWarning: jest.fn(),
        clearNodeWarning: jest.fn(),
      }),
    )

    dispatch({
      type: 'segments',
      segments: [{sid: 2, source: 'S', target: '', context_url: null}],
    })
    dispatch({type: 'updateTranslation', sid: 2, target: 'T'})

    expect(onTranslationUpdate).toHaveBeenCalledWith(
      2,
      'T',
      expect.arrayContaining([expect.objectContaining({sid: 2, target: 'T'})]),
    )
  })

  it('calls clearNodeWarning on successful updateNodeTranslation', () => {
    utils.updateNodeTranslation.mockReturnValue('ok')
    const el = document.createElement('p')
    el.textContent = 'Hello'
    const targetRef = {current: document.createElement('div')}
    const clearNodeWarning = jest.fn()

    utils.getSegmentNodeMap.mockReturnValue({
      sidToNodeIndices: new Map([[1, [0]]]),
      nodes: [el],
    })

    renderHook(() =>
      useContextPreviewMessages({
        onHighlight: jest.fn(),
        onTranslationUpdate: jest.fn(),
        targetRef,
        showNodeWarning: jest.fn(),
        clearNodeWarning,
      }),
    )

    dispatch({
      type: 'segments',
      segments: [{sid: 1, source: 'Hello', target: '', context_url: null}],
    })
    dispatch({type: 'updateTranslation', sid: 1, target: 'Hola'})

    expect(clearNodeWarning).toHaveBeenCalledWith(el)
  })

  it('calls showNodeWarning and reverts to source on mismatch', () => {
    utils.updateNodeTranslation.mockReturnValue('mismatch')
    utils.getSidsFromElement.mockReturnValue([1])
    utils.stripSegmentTags.mockReturnValue('Hello')

    const el = document.createElement('p')
    el.textContent = 'Hello'
    const targetRef = {current: document.createElement('div')}
    const showNodeWarning = jest.fn()

    utils.getSegmentNodeMap.mockReturnValue({
      sidToNodeIndices: new Map([[1, [0]]]),
      nodes: [el],
    })

    renderHook(() =>
      useContextPreviewMessages({
        onHighlight: jest.fn(),
        onTranslationUpdate: jest.fn(),
        targetRef,
        showNodeWarning,
        clearNodeWarning: jest.fn(),
      }),
    )

    dispatch({
      type: 'segments',
      segments: [{sid: 1, source: 'Hello', target: '', context_url: null}],
    })
    dispatch({type: 'updateTranslation', sid: 1, target: 'Mismatch!'})

    expect(showNodeWarning).toHaveBeenCalledWith(el)
    expect(utils.replaceTextContent).toHaveBeenCalledWith(el, 'Hello')
  })

  it('skips DOM update when targetRef is null', () => {
    const onTranslationUpdate = jest.fn()
    renderHook(() =>
      useContextPreviewMessages({
        onHighlight: jest.fn(),
        onTranslationUpdate,
        targetRef: nullRef(),
        showNodeWarning: jest.fn(),
        clearNodeWarning: jest.fn(),
      }),
    )

    dispatch({
      type: 'segments',
      segments: [{sid: 5, source: 'S', target: '', context_url: null}],
    })
    dispatch({type: 'updateTranslation', sid: 5, target: 'T'})

    expect(utils.updateNodeTranslation).not.toHaveBeenCalled()
    expect(onTranslationUpdate).toHaveBeenCalled()
  })

  it('skips nodes with no matching entry in the map', () => {
    utils.getSegmentNodeMap.mockReturnValue({
      sidToNodeIndices: new Map(),
      nodes: [],
    })

    const targetRef = {current: document.createElement('div')}

    renderHook(() =>
      useContextPreviewMessages({
        onHighlight: jest.fn(),
        onTranslationUpdate: jest.fn(),
        targetRef,
        showNodeWarning: jest.fn(),
        clearNodeWarning: jest.fn(),
      }),
    )

    dispatch({
      type: 'segments',
      segments: [{sid: 1, source: 'S', target: '', context_url: null}],
    })
    dispatch({type: 'updateTranslation', sid: 1, target: 'T'})

    expect(utils.updateNodeTranslation).not.toHaveBeenCalled()
  })
})
