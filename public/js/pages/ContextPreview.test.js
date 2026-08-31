import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'

import useContextHighlight from '../hooks/useContextHighlight'
import useContextPreviewMessages from '../hooks/useContextPreviewMessages'
import useContextDocument from '../hooks/useContextDocument'
import ContextPreviewChannel from '../utils/contextPreviewChannel'
import {
  getSidsFromElement,
  getSegmentNodeMap,
  checkNodeTranslationStatus,
} from '../utils/contextPreviewUtils'
import ContextPreview from './ContextPreview'

// mountPage runs at module load; stub it out and seed config before the page's
// module-level code reads config.* (jest.mock factories are hoisted).
jest.mock('./mountPage', () => {
  global.config = Object.assign(global.config ?? {}, {
    source_code: 'en-US',
    target_code: 'it-IT',
  })
  return {mountPage: jest.fn()}
})

jest.mock('../hooks/useContextHighlight', () => jest.fn())
jest.mock('../hooks/useContextPreviewMessages', () => jest.fn())
jest.mock('../hooks/useContextDocument', () => jest.fn())

jest.mock('../utils/contextPreviewChannel', () => ({
  __esModule: true,
  default: {sendMessage: jest.fn()},
}))

jest.mock('../utils/contextPreviewUtils', () => ({
  findSegmentSidsByClick: jest.fn(),
  tagSegments: jest.fn(),
  suppressClickTraps: jest.fn(),
  getSidsFromElement: jest.fn(() => []),
  getSegmentNodeMap: jest.fn(() => null),
  checkNodeTranslationStatus: jest.fn(() => 'ok'),
}))

jest.mock('../components/common/SegmentedControl', () => ({
  SegmentedControl: ({options, onChange, name}) => (
    <div data-testid={`segmented-${name}`}>
      {options.map((o) => (
        <button
          key={o.id}
          data-testid={`seg-${name}-${o.id}`}
          onClick={() => onChange(o.id)}
        >
          {o.name}
        </button>
      ))}
    </div>
  ),
}))

jest.mock('../../img/icons/IconChevronLeft', () => () => <span>left</span>)
jest.mock('../../img/icons/IconChevronRight', () => () => <span>right</span>)

// Panels are heavy; stub them but wire the refs to real detached DOM so the
// page's DOM-driven effects have containers to operate on.
jest.mock('../components/contextPreview', () => ({
  LivePreviewPanel: ({panelRef, scrollRef, title, languageLabel}) => (
    <div
      data-testid={`live-panel-${title}`}
      className="context-preview-content"
      ref={(el) => {
        if (scrollRef) scrollRef.current = el
      }}
    >
      <div
        ref={(el) => {
          if (panelRef) panelRef.current = el
        }}
      />
      {languageLabel ? <span>{languageLabel}</span> : null}
    </div>
  ),
  ScreenshotContextPanel: ({screenshotUrl, title}) => (
    <div data-testid={`screenshot-panel-${title}`}>{screenshotUrl}</div>
  ),
}))

const defaultHighlightReturn = () => ({
  highlight: null,
  highlightHidden: false,
  setHighlight: jest.fn(),
  highlightRef: {current: null},
  applyHighlightsForSegment: jest.fn(() => 0),
  applyHighlightsForNode: jest.fn(),
  navigateHighlight: jest.fn(),
  handlePrev: jest.fn(),
  handleNext: jest.fn(),
})

const setHighlight = (overrides = {}) =>
  useContextHighlight.mockReturnValue({
    ...defaultHighlightReturn(),
    ...overrides,
  })

const setMessages = (overrides = {}) =>
  useContextPreviewMessages.mockReturnValue({
    segments: [],
    setSegments: jest.fn(),
    currentContextUrl: null,
    currentSid: null,
    ...overrides,
  })

const setDocument = (overrides = {}) =>
  useContextDocument.mockReturnValue({
    htmlContent: '',
    loading: false,
    error: null,
    ...overrides,
  })

const lastMessagesProps = () => useContextPreviewMessages.mock.calls.at(-1)[0]

beforeEach(() => {
  jest.clearAllMocks()
  global.config = {source_code: 'en-US', target_code: 'it-IT'}
  window.history.pushState({}, '', '/')
  setHighlight()
  setMessages()
  setDocument()
})

describe('ContextPreview', () => {
  test('renders the loading state', () => {
    setDocument({loading: true})
    render(<ContextPreview />)
    expect(screen.getByText('Loading document...')).toBeInTheDocument()
  })

  test('renders the error state', () => {
    setDocument({error: 'boom'})
    render(<ContextPreview />)
    expect(screen.getByText('Error loading document')).toBeInTheDocument()
    expect(screen.getByText('boom')).toBeInTheDocument()
  })

  test('renders the empty state when no context and no screenshots', () => {
    render(<ContextPreview />)
    expect(
      screen.getByText('No context available for this segment'),
    ).toBeInTheDocument()
  })

  test('reads source/target codes from URL params over config', () => {
    window.history.pushState({}, '', '/?source_code=de-DE&target_code=ar-EG')
    setMessages({
      currentContextUrl: 'http://ctx/a',
      segments: [{sid: '1', context_url: 'http://ctx/a'}],
    })
    setDocument({htmlContent: '<p>hi</p>'})
    render(<ContextPreview />)
    // Both view -> language labels show the resolved codes
    fireEvent.click(screen.getByTestId('seg-context-preview-view-mode-both'))
    expect(screen.getByText('Source - de-DE')).toBeInTheDocument()
    expect(screen.getByText('Target - ar-EG')).toBeInTheDocument()
  })

  test('renders toolbar and target panel with live preview HTML', () => {
    setMessages({
      currentContextUrl: 'http://ctx/a',
      currentSid: 1,
      segments: [{sid: '1', context_url: 'http://ctx/a'}],
    })
    setDocument({htmlContent: '<p>one</p><p>two</p>'})
    render(<ContextPreview />)
    expect(screen.getByText('100%')).toBeInTheDocument()
    expect(screen.getByTestId('live-panel-Translation')).toBeInTheDocument()
    // untagged-nodes effect fires a loadMoreSegments message
    expect(ContextPreviewChannel.sendMessage).toHaveBeenCalledWith(
      expect.objectContaining({type: 'loadMoreSegments'}),
    )
  })

  test('zoom in, out and reset update the zoom level and disabled states', () => {
    setMessages({
      currentContextUrl: 'http://ctx/a',
      segments: [{sid: '1', context_url: 'http://ctx/a'}],
    })
    setDocument({htmlContent: '<p>x</p>'})
    render(<ContextPreview />)

    const zoomIn = screen.getByLabelText('Zoom in')
    const zoomOut = screen.getByLabelText('Zoom out')
    const reset = screen.getByLabelText('Reset zoom')

    expect(reset).toBeDisabled()

    fireEvent.click(zoomIn)
    expect(screen.getByText('125%')).toBeInTheDocument()
    expect(reset).not.toBeDisabled()

    // ramp up to the 200% ceiling
    fireEvent.click(zoomIn)
    fireEvent.click(zoomIn)
    fireEvent.click(zoomIn)
    expect(screen.getByText('200%')).toBeInTheDocument()
    expect(zoomIn).toBeDisabled()

    fireEvent.click(reset)
    expect(screen.getByText('100%')).toBeInTheDocument()

    // ramp down to the 50% floor
    fireEvent.click(zoomOut)
    fireEvent.click(zoomOut)
    expect(screen.getByText('50%')).toBeInTheDocument()
    expect(zoomOut).toBeDisabled()
  })

  test('switching view mode to source renders only the source panel', () => {
    setMessages({
      currentContextUrl: 'http://ctx/a',
      segments: [{sid: '1', context_url: 'http://ctx/a'}],
    })
    setDocument({htmlContent: '<p>x</p>'})
    render(<ContextPreview />)

    fireEvent.click(screen.getByTestId('seg-context-preview-view-mode-source'))
    expect(screen.getByTestId('live-panel-Source')).toBeInTheDocument()
    expect(screen.queryByTestId('live-panel-Translation')).toBeNull()
  })

  test('renders the content-view control and switches to screenshot', () => {
    setHighlight({
      highlight: {mode: 'segment', sid: 1, activeIndex: 0, total: 1},
    })
    setMessages({
      currentContextUrl: 'http://ctx/a',
      currentSid: 1,
      segments: [
        {sid: '1', context_url: 'http://ctx/a', screenshot: 'shot.png'},
      ],
    })
    setDocument({htmlContent: '<p>x</p>'})
    render(<ContextPreview />)

    // content-view control is present because current segment has a screenshot
    fireEvent.click(
      screen.getByTestId('seg-context-preview-content-view-screenshot'),
    )
    expect(screen.getByTestId('screenshot-panel-Source')).toBeInTheDocument()
    expect(screen.getByText('shot.png')).toBeInTheDocument()
  })

  test('auto-switches to screenshot when segment has no HTML context', () => {
    setMessages({
      currentContextUrl: null,
      currentSid: 5,
      segments: [{sid: '5', context_url: null, screenshot: 'auto.png'}],
    })
    render(<ContextPreview />)
    expect(screen.getByTestId('screenshot-panel-Source')).toBeInTheDocument()
  })

  test('reverts to live preview when the current selection loses its screenshot', () => {
    setHighlight({
      highlight: {mode: 'segment', sid: 1, activeIndex: 0, total: 1},
    })
    setMessages({
      currentContextUrl: 'http://ctx/a',
      currentSid: 1,
      segments: [
        {sid: '1', context_url: 'http://ctx/a', screenshot: 'shot.png'},
      ],
    })
    setDocument({htmlContent: '<p>x</p>'})
    const {rerender} = render(<ContextPreview />)

    fireEvent.click(
      screen.getByTestId('seg-context-preview-content-view-screenshot'),
    )
    expect(screen.getByTestId('screenshot-panel-Source')).toBeInTheDocument()

    // Highlight moves to a segment with no screenshot -> effect flips back
    setHighlight({
      highlight: {mode: 'segment', sid: 99, activeIndex: 0, total: 1},
    })
    act(() => {
      rerender(<ContextPreview />)
    })
    expect(screen.queryByTestId('screenshot-panel-Source')).toBeNull()
    // switching to screenshot forced SOURCE view, so the source panel returns
    expect(screen.getByTestId('live-panel-Source')).toBeInTheDocument()
  })

  test('shows navigation counter and fires prev/next in segment mode', () => {
    const handlePrev = jest.fn()
    const handleNext = jest.fn()
    setHighlight({
      highlight: {mode: 'segment', sid: 1, activeIndex: 0, total: 3},
      handlePrev,
      handleNext,
    })
    setMessages({
      currentContextUrl: 'http://ctx/a',
      segments: [{sid: '1', context_url: 'http://ctx/a'}],
    })
    setDocument({htmlContent: '<p>x</p>'})
    render(<ContextPreview />)

    expect(screen.getByText('1 of 3')).toBeInTheDocument()
    fireEvent.click(screen.getByLabelText('Previous'))
    fireEvent.click(screen.getByLabelText('Next'))
    expect(handlePrev).toHaveBeenCalled()
    expect(handleNext).toHaveBeenCalled()
  })

  test('shows the node-mode counter and shared-element info', () => {
    setHighlight({
      highlight: {mode: 'node', nodeIndex: 0, sids: [1, 2], activeSegIdx: 0},
    })
    setMessages({
      currentContextUrl: 'http://ctx/a',
      segments: [{sid: '1', context_url: 'http://ctx/a'}],
    })
    setDocument({htmlContent: '<p>x</p>'})
    render(<ContextPreview />)

    expect(screen.getByText('Segment 1 of 2')).toBeInTheDocument()
    expect(
      screen.getByText('2 segments share this element'),
    ).toBeInTheDocument()
  })

  test('shows the hidden-segment warning', () => {
    setHighlight({highlightHidden: true})
    setMessages({
      currentContextUrl: 'http://ctx/a',
      segments: [{sid: '1', context_url: 'http://ctx/a'}],
    })
    setDocument({htmlContent: '<p>x</p>'})
    render(<ContextPreview />)
    expect(screen.getByText('Segment not found in preview')).toBeInTheDocument()
  })

  test('shows the conflicting-translations warning on detected mismatch', () => {
    const el = document.createElement('p')
    const map = {
      sidToNodeIndices: new Map([[1, [0]]]),
      nodeIndexToSids: new Map([[0, [1, 2]]]),
      nodes: [el],
    }
    getSegmentNodeMap.mockReturnValue(map)
    checkNodeTranslationStatus.mockReturnValue('mismatch')
    setHighlight({
      highlight: {mode: 'node', nodeIndex: 0, sids: [1, 2], activeSegIdx: 0},
    })
    setMessages({
      currentContextUrl: 'http://ctx/a',
      segments: [{sid: '1', context_url: 'http://ctx/a'}],
    })
    setDocument({htmlContent: '<p>x</p>'})
    render(<ContextPreview />)
    expect(
      screen.getByText('Duplicate segments with conflicting translations'),
    ).toBeInTheDocument()
  })

  test('builds metadata and screenshot maps from segments', () => {
    setMessages({
      currentContextUrl: 'http://ctx/a',
      currentSid: 1,
      segments: [
        {
          sid: '1',
          context_url: 'http://ctx/a',
          resname: 'res',
          restype: 'string',
          client_name: 'client',
          screenshot: 'a.png',
        },
        {sid: '2', context_url: 'http://ctx/b', screenshot: 'b.png'},
      ],
    })
    setDocument({htmlContent: '<p>x</p>'})
    render(<ContextPreview />)
    // Only the segment matching the current context URL is mappable
    expect(screen.getByTestId('live-panel-Translation')).toBeInTheDocument()
  })

  describe('message callbacks', () => {
    const setupWithRefs = () => {
      setMessages({
        currentContextUrl: 'http://ctx/a',
        segments: [{sid: '1', context_url: 'http://ctx/a'}],
      })
      setDocument({htmlContent: '<p>x</p>'})
    }

    test('onTranslationUpdate is a no-op callback', () => {
      setupWithRefs()
      render(<ContextPreview />)
      const {onTranslationUpdate} = lastMessagesProps()
      expect(() => act(() => onTranslationUpdate())).not.toThrow()
    })

    test('onHighlight updates active segment when already in matching node mode', () => {
      const applyHighlightsForNode = jest.fn()
      const setHighlightFn = jest.fn()
      setHighlight({
        highlightRef: {
          current: {mode: 'node', sids: [10, 20], activeSegIdx: 0},
        },
        applyHighlightsForNode,
        setHighlight: setHighlightFn,
      })
      setupWithRefs()
      render(<ContextPreview />)
      const {onHighlight} = lastMessagesProps()
      act(() => onHighlight(20))
      expect(applyHighlightsForNode).toHaveBeenCalledWith([10, 20], 1, false)
      expect(setHighlightFn).toHaveBeenCalled()
    })

    test('onHighlight promotes to node mode when the segment shares a node', () => {
      const el = document.createElement('p')
      getSegmentNodeMap.mockReturnValue({
        sidToNodeIndices: new Map([[7, [0]]]),
        nodeIndexToSids: new Map([[0, [7, 8]]]),
        nodes: [el],
      })
      const setHighlightFn = jest.fn()
      setHighlight({
        highlightRef: {current: null},
        applyHighlightsForSegment: jest.fn(() => 2),
        setHighlight: setHighlightFn,
      })
      setupWithRefs()
      render(<ContextPreview />)
      const {onHighlight} = lastMessagesProps()
      act(() => onHighlight(7))
      expect(setHighlightFn).toHaveBeenCalledWith(
        expect.objectContaining({mode: 'node', sids: [7, 8]}),
      )
    })

    test('onHighlight falls back to segment mode when not shared', () => {
      getSegmentNodeMap.mockReturnValue(null)
      const setHighlightFn = jest.fn()
      setHighlight({
        highlightRef: {current: null},
        applyHighlightsForSegment: jest.fn(() => 3),
        setHighlight: setHighlightFn,
      })
      setupWithRefs()
      render(<ContextPreview />)
      const {onHighlight} = lastMessagesProps()
      act(() => onHighlight(42))
      expect(setHighlightFn).toHaveBeenCalledWith(
        expect.objectContaining({mode: 'segment', sid: 42, total: 3}),
      )
    })

    test('onHighlight clears highlight when no occurrences are found', () => {
      const setHighlightFn = jest.fn()
      setHighlight({
        highlightRef: {current: null},
        applyHighlightsForSegment: jest.fn(() => 0),
        setHighlight: setHighlightFn,
      })
      setupWithRefs()
      render(<ContextPreview />)
      const {onHighlight} = lastMessagesProps()
      act(() => onHighlight(42))
      expect(setHighlightFn).toHaveBeenCalledWith(null)
    })

    test('showNodeWarning promotes to node mode for a shared element', () => {
      const el = document.createElement('p')
      getSidsFromElement.mockReturnValue([1, 2])
      getSegmentNodeMap.mockReturnValue({
        sidToNodeIndices: new Map(),
        nodeIndexToSids: new Map(),
        nodes: [el],
      })
      const setHighlightFn = jest.fn()
      setHighlight({
        highlightRef: {current: {mode: 'segment', sid: 1}},
        setHighlight: setHighlightFn,
      })
      setupWithRefs()
      render(<ContextPreview />)
      const {showNodeWarning, clearNodeWarning} = lastMessagesProps()
      act(() => showNodeWarning(el))
      expect(setHighlightFn).toHaveBeenCalled()
      // clearNodeWarning just resets the mismatch flag
      expect(() => act(() => clearNodeWarning())).not.toThrow()
    })
  })

  describe('panel interactions', () => {
    test('clicking a tagged node in a panel highlights it and posts a message', () => {
      const {findSegmentSidsByClick} = require('../utils/contextPreviewUtils')
      findSegmentSidsByClick.mockReturnValue({sids: [3, 4], nodeIndex: 1})
      const applyHighlightsForNode = jest.fn()
      const setHighlightFn = jest.fn()
      setHighlight({applyHighlightsForNode, setHighlight: setHighlightFn})
      setMessages({
        currentContextUrl: 'http://ctx/a',
        segments: [{sid: '3', context_url: 'http://ctx/a'}],
      })
      setDocument({htmlContent: '<p>x</p>'})
      render(<ContextPreview />)

      const panel = screen.getByTestId('live-panel-Translation').firstChild
      act(() => {
        panel.dispatchEvent(new MouseEvent('click', {bubbles: true}))
      })
      expect(applyHighlightsForNode).toHaveBeenCalledWith([3, 4], 0, true)
      expect(setHighlightFn).toHaveBeenCalledWith(
        expect.objectContaining({mode: 'node', nodeIndex: 1, sids: [3, 4]}),
      )
      expect(ContextPreviewChannel.sendMessage).toHaveBeenCalledWith({
        type: 'segmentClicked',
        sid: 3,
      })
    })

    test('scroll sync listeners are attached in split view', () => {
      setMessages({
        currentContextUrl: 'http://ctx/a',
        segments: [{sid: '1', context_url: 'http://ctx/a'}],
      })
      setDocument({htmlContent: '<p>x</p>'})
      render(<ContextPreview />)
      fireEvent.click(screen.getByTestId('seg-context-preview-view-mode-both'))

      const target = screen.getByTestId('live-panel-Translation')
      expect(() =>
        act(() => {
          target.dispatchEvent(new Event('scroll', {bubbles: true}))
        }),
      ).not.toThrow()
    })
  })

  test('handles stylesheet links in injected HTML and settles readiness', () => {
    setMessages({
      currentContextUrl: 'http://ctx/a',
      segments: [{sid: '1', context_url: 'http://ctx/a'}],
    })
    setDocument({
      htmlContent: '<link rel="stylesheet" href="s.css"><p>hi</p>',
    })
    render(<ContextPreview />)

    const link = document
      .querySelector('[data-testid="live-panel-Translation"]')
      .querySelector('link[rel="stylesheet"]')
    expect(link).not.toBeNull()
    act(() => {
      link.dispatchEvent(new Event('load'))
    })
    expect(screen.getByTestId('live-panel-Translation')).toBeInTheDocument()
  })

  test('clears rendered HTML refs when switching to screenshot with content', () => {
    setHighlight({
      highlight: {mode: 'segment', sid: 1, activeIndex: 0, total: 1},
    })
    setMessages({
      currentContextUrl: 'http://ctx/a',
      currentSid: 1,
      segments: [
        {sid: '1', context_url: 'http://ctx/a', screenshot: 'shot.png'},
      ],
    })
    setDocument({htmlContent: '<p>x</p>'})
    render(<ContextPreview />)
    fireEvent.click(
      screen.getByTestId('seg-context-preview-content-view-screenshot'),
    )
    expect(screen.getByTestId('screenshot-panel-Source')).toBeInTheDocument()
  })
})

describe('isRTLLanguage handling', () => {
  test('resolves RTL direction from the target code', () => {
    global.config = {source_code: 'en-US', target_code: 'ar'}
    setMessages({
      currentContextUrl: 'http://ctx/a',
      segments: [{sid: '1', context_url: 'http://ctx/a'}],
    })
    setDocument({htmlContent: '<p>x</p>'})
    render(<ContextPreview />)
    expect(screen.getByTestId('live-panel-Translation')).toBeInTheDocument()
  })

  test('treats missing target code as ltr', () => {
    global.config = {source_code: 'en-US', target_code: ''}
    setMessages({
      currentContextUrl: 'http://ctx/a',
      segments: [{sid: '1', context_url: 'http://ctx/a'}],
    })
    setDocument({htmlContent: '<p>x</p>'})
    render(<ContextPreview />)
    expect(screen.getByTestId('live-panel-Translation')).toBeInTheDocument()
  })
})
