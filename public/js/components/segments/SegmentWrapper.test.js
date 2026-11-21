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

// The build flag isNewEditorEnabled() reads
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
    const chips = screen.getAllByText(/^\u200B1\u200B$/, {
      selector: '.tag > .tag-text',
    })
    expect(chips).toHaveLength(2)
    // the nbsp is the editor's mark, not a chip
    expect(
      screen.getByLabelText('Non-breaking space (U+00A0)'),
    ).not.toHaveClass('tag')
  })

  test("marks the spaces as soon as the user's show whitespace option changes", () => {
    const segment = makeSegment({segment: 'a b'})
    const spaceMarks = () =>
      screen.queryAllByText((_, el) =>
        el.classList.contains('special-char-space'),
      ).length
    const {rerender} = renderWrapper(segment, {}, {show_whitespace: 0})
    expect(spaceMarks()).toBe(0)

    rerender(tree(segment, {}, {show_whitespace: 1}))
    expect(spaceMarks()).toBe(1)

    rerender(tree(segment, {}, {show_whitespace: 0}))
    expect(spaceMarks()).toBe(0)
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

    expect(mockGetSearchMarks).toHaveBeenCalledWith('a & \uFFFCb', true, '42', [
      expect.any(String),
    ])
    expect(screen.getByText('a &', {selector: 'mark'})).toHaveClass(
      'searchMarker',
    )
  })

  test('counts the hits in tag labels but draws only the text ones: the chip is left unmarked', () => {
    const label = 'name'
    mockGetSearchMarks.mockReturnValueOnce([
      {start: 0, end: 1, className: 'searchMarker', tag: true},
      {start: 2, end: 6, className: 'searchMarker currSearchItem'},
    ])
    renderWrapper(
      makeSegment({
        inSearch: true,
        segment: `<ph id="mtc_1" equiv-text="base64:${btoa(label)}"/> name`,
      }),
    )

    expect(mockGetSearchMarks).toHaveBeenCalledWith('\uFFFC name', true, '42', [
      label,
    ])
    const marks = screen.getAllByText(/./, {selector: 'mark'})
    expect(marks).toHaveLength(1)
    expect(marks[0]).toHaveTextContent('name')
    expect(marks[0]).toHaveClass('searchMarker currSearchItem')
  })

  test('marks the target search hits with isSource false', () => {
    renderWrapper(makeSegment({inSearch: true}), {isTarget: true})

    expect(mockGetSearchMarks).toHaveBeenCalledWith(
      'target text',
      false,
      '42',
      [],
    )
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
