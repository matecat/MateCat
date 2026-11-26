import React from 'react'
import {render, screen} from '@testing-library/react'

const mockCheckCurrentSegmentTPEnabled = jest.fn(() => false)
const MARKS = [{start: 0, end: 3, className: 'searchMarker'}]
const mockGetSearchMarks = jest.fn(() => MARKS)
const mockRemoveTagsFromText = jest.fn((text) => `stripped:${text}`)

jest.mock('./SegmentSource', () => ({
  __esModule: true,
  default: ({segment}) => <div data-testid="segment-source">{segment.sid}</div>,
}))

jest.mock('../editor/matecat/SourceEditor', () => ({
  __esModule: true,
  default: ({segment, value, bridge}) => (
    <div
      data-testid="source-editor"
      data-value={value}
      data-bridge={Object.keys(bridge).sort().join(',')}
    >
      {segment.sid}
    </div>
  ),
}))

jest.mock('./SegmentTarget', () => ({
  __esModule: true,
  default: ({segment}) => <div data-testid="segment-target">{segment.sid}</div>,
}))

jest.mock('../../utils/segmentUtils', () => ({
  __esModule: true,
  default: {
    checkCurrentSegmentTPEnabled: (...args) =>
      mockCheckCurrentSegmentTPEnabled(...args),
  },
}))

jest.mock('../header/cattol/search/searchUtils', () => ({
  __esModule: true,
  default: {getSearchMarks: (...args) => mockGetSearchMarks(...args)},
}))

jest.mock('./utils/DraftMatecatUtils/segmentStringUtils', () => ({
  __esModule: true,
  default: {removeTagsFromText: (...args) => mockRemoveTagsFromText(...args)},
}))

import SegmentWrapper from './SegmentWrapper'
import {SegmentContext} from './SegmentContext'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'
import {setTagSignatureMiddleware} from './utils/DraftMatecatUtils/tagModel'

// Spaces as plain text, as with the show-whitespace switch off
setTagSignatureMiddleware('space', () => false)

function makeSegment(overrides = {}) {
  return {
    sid: '42',
    segment: 'source text',
    translation: 'target text',
    opened: false,
    inSearch: false,
    ...overrides,
  }
}

const tree = (segment, props, metadata) => (
  <ApplicationWrapperContext.Provider value={{userInfo: {metadata}}}>
    <SegmentContext.Provider value={{segment}}>
      <SegmentWrapper {...props} />
    </SegmentContext.Provider>
  </ApplicationWrapperContext.Provider>
)

function renderWrapper(segment, props = {}, metadata = {}) {
  return render(tree(segment, props, metadata))
}

// The build flag isNewEditorEnabled() reads; the user's choice sits on top of it
const setBuildFlag = (on) =>
  (process.env.ENABLE_NEW_EDITOR = on ? 'true' : 'false')
const originalBuildFlag = process.env.ENABLE_NEW_EDITOR
afterAll(() => {
  if (originalBuildFlag === undefined) delete process.env.ENABLE_NEW_EDITOR
  else process.env.ENABLE_NEW_EDITOR = originalBuildFlag
})

beforeEach(() => {
  window.config = {
    ...window.config,
    isSourceRTL: false,
    isTargetRTL: true,
    source_code: 'en-US',
    target_code: 'it-IT',
  }
  mockCheckCurrentSegmentTPEnabled.mockReset()
  mockCheckCurrentSegmentTPEnabled.mockReturnValue(false)
  setBuildFlag(false)
  mockGetSearchMarks.mockClear()
  mockRemoveTagsFromText.mockClear()
})

describe('SegmentWrapper on an opened segment', () => {
  test('renders the source editor', () => {
    renderWrapper(makeSegment({opened: true}))
    expect(screen.getByTestId('segment-source').textContent).toBe('42')
    expect(screen.queryByTestId('segment-target')).toBeNull()
  })

  test('renders the target editor when isTarget is set', () => {
    renderWrapper(makeSegment({opened: true}), {isTarget: true})
    expect(screen.getByTestId('segment-target').textContent).toBe('42')
    expect(screen.queryByTestId('segment-source')).toBeNull()
  })
})

describe('SegmentWrapper on a closed segment', () => {
  test('renders the read-only source in the source language', () => {
    const {container} = renderWrapper(makeSegment())

    const wrapper = container.querySelector('#segment-42-source')
    expect(wrapper).not.toBeNull()
    expect(wrapper.className).toBe('source item')

    const text = screen.getByText('source text')
    expect(text).toHaveAttribute('lang', 'en-US')
    expect(text).not.toHaveClass('targetarea')
  })

  test('renders the read-only target in the target language', () => {
    const {container} = renderWrapper(makeSegment(), {isTarget: true})

    const wrapper = container.querySelector('#segment-42-target')
    expect(wrapper).not.toBeNull()
    expect(wrapper.className).toBe('target item')

    const text = screen.getByText('target text')
    expect(text).toHaveAttribute('lang', 'it-IT')
    expect(text).toHaveClass('targetarea', 'editarea')
  })

  test('shows the text decoded and the tags as chips', () => {
    renderWrapper(
      makeSegment({segment: 'L&apos;a &amp;nbsp; <g id="1">tag</g>##$_A0$##'}),
    )

    expect(screen.getByText("L'a &nbsp;", {exact: false})).toBeInTheDocument()
    const chips = screen.getAllByText(/^\u200B1\u200B$/)
    expect(chips).toHaveLength(2)
    chips.forEach((chip) => expect(chip).toHaveClass('tag'))
    expect(screen.getByText('\u00B0')).toHaveClass('tag-nbsp')
  })

  test('strips tags when tag projection is enabled for the segment', () => {
    mockCheckCurrentSegmentTPEnabled.mockReturnValue(true)
    renderWrapper(makeSegment())

    expect(mockRemoveTagsFromText).toHaveBeenCalledWith('source text')
    expect(screen.getByText('stripped:source text')).toBeInTheDocument()
  })

  test('marks the search hits found on the visible text, not the markup', () => {
    renderWrapper(
      makeSegment({inSearch: true, segment: 'a &amp; <x id="1"/>b'}),
    )

    expect(mockGetSearchMarks).toHaveBeenCalledWith('a & \uFFFCb', true, '42')
    expect(screen.getByText('a &', {selector: 'mark'})).toHaveClass(
      'searchMarker',
    )
  })

  test('marks the target search hits with isSource false', () => {
    renderWrapper(makeSegment({inSearch: true}), {isTarget: true})

    expect(mockGetSearchMarks).toHaveBeenCalledWith('target text', false, '42')
  })

  test('marks nothing when the segment is not in search', () => {
    renderWrapper(makeSegment())

    expect(mockGetSearchMarks).not.toHaveBeenCalled()
    expect(screen.queryByText(/.*/, {selector: 'mark'})).not.toBeInTheDocument()
  })
})

describe('SegmentWrapper editor switch (ENABLE_NEW_EDITOR)', () => {
  test('with the flag off the opened source is the Draft editor', () => {
    renderWrapper(makeSegment({opened: true}))
    expect(screen.getByTestId('segment-source')).toBeInTheDocument()
    expect(screen.queryByTestId('source-editor')).not.toBeInTheDocument()
  })

  test('with the flag on the opened source is the CodeMirror editor, given the Draft bridge', () => {
    setBuildFlag(true)
    renderWrapper(makeSegment({opened: true}))
    const editor = screen.getByTestId('source-editor')
    expect(editor).toHaveAttribute('data-value', 'source text')
    expect(editor).toHaveAttribute('data-bridge', 'publishSource,tagIdentities')
    expect(screen.queryByTestId('segment-source')).not.toBeInTheDocument()
  })

  test('with tag projection on, the CodeMirror source shows the source without tags', () => {
    setBuildFlag(true)
    mockCheckCurrentSegmentTPEnabled.mockReturnValue(true)
    renderWrapper(makeSegment({opened: true}))
    expect(screen.getByTestId('source-editor')).toHaveAttribute(
      'data-value',
      'stripped:source text',
    )
  })

  test('the target stays the Draft editor whatever the flag', () => {
    setBuildFlag(true)
    renderWrapper(makeSegment({opened: true}), {isTarget: true})
    expect(screen.getByTestId('segment-target')).toBeInTheDocument()
  })
})

describe('SegmentWrapper per-user editor choice (editor_engine)', () => {
  beforeEach(() => setBuildFlag(true))

  test('a user who never picked an editor gets the CodeMirror source', () => {
    renderWrapper(makeSegment({opened: true}), {}, {})
    expect(screen.getByTestId('source-editor')).toBeInTheDocument()
  })

  test('a user who switched back to the previous editor gets the Draft source', () => {
    renderWrapper(makeSegment({opened: true}), {}, {editor_engine: 'draft'})
    expect(screen.getByTestId('segment-source')).toBeInTheDocument()
    expect(screen.queryByTestId('source-editor')).not.toBeInTheDocument()
  })

  test('the user choice cannot turn the new editor on when the build switches it off', () => {
    setBuildFlag(false)
    renderWrapper(makeSegment({opened: true}), {}, {editor_engine: 'cm6'})
    expect(screen.getByTestId('segment-source')).toBeInTheDocument()
  })

  test('a user outside the application context (no user info) gets the CodeMirror source', () => {
    render(
      <SegmentContext.Provider value={{segment: makeSegment({opened: true})}}>
        <SegmentWrapper />
      </SegmentContext.Provider>,
    )
    expect(screen.getByTestId('source-editor')).toBeInTheDocument()
  })

  test('changing the setting swaps the open source editor without reopening the segment', () => {
    const segment = makeSegment({opened: true})
    const {rerender} = renderWrapper(segment, {}, {editor_engine: 'cm6'})
    expect(screen.getByTestId('source-editor')).toBeInTheDocument()

    rerender(tree(segment, {}, {editor_engine: 'draft'}))
    expect(screen.getByTestId('segment-source')).toBeInTheDocument()
    expect(screen.queryByTestId('source-editor')).not.toBeInTheDocument()
  })
})
