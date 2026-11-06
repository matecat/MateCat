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

function renderWrapper(segment, props = {}) {
  return render(
    <SegmentContext.Provider value={{segment}}>
      <SegmentWrapper {...props} />
    </SegmentContext.Provider>,
  )
}

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
