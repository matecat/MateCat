import React from 'react'
import {render, screen} from '@testing-library/react'

const mockCheckCurrentSegmentTPEnabled = jest.fn(() => false)
const mockMarkText = jest.fn((text) => `marked:${text}`)
const mockRemoveTagsFromText = jest.fn((text) => `stripped:${text}`)

jest.mock('./SegmentSource', () => ({
  __esModule: true,
  default: ({segment}) => <div data-testid="segment-source">{segment.sid}</div>,
}))

jest.mock('./SegmentTarget', () => ({
  __esModule: true,
  default: ({segment}) => <div data-testid="segment-target">{segment.sid}</div>,
}))

jest.mock('./SimpleEditor', () => ({
  __esModule: true,
  default: ({text, isTarget, isRtl, className}) => (
    <div
      data-testid="simple-editor"
      data-is-target={String(isTarget)}
      data-is-rtl={String(isRtl)}
      data-class-name={className}
    >
      {text}
    </div>
  ),
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
  default: {markText: (...args) => mockMarkText(...args)},
}))

jest.mock('./utils/DraftMatecatUtils', () => ({
  __esModule: true,
  default: {removeTagsFromText: (...args) => mockRemoveTagsFromText(...args)},
}))

import SegmentWrapper from './SegmentWrapper'
import {SegmentContext} from './SegmentContext'

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
  }
  mockCheckCurrentSegmentTPEnabled.mockReset()
  mockCheckCurrentSegmentTPEnabled.mockReturnValue(false)
  mockMarkText.mockClear()
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
  test('renders the read-only source through SimpleEditor', () => {
    const {container} = renderWrapper(makeSegment())

    const wrapper = container.querySelector('#segment-42-source')
    expect(wrapper).not.toBeNull()
    expect(wrapper.className).toBe('source item')

    const editor = screen.getByTestId('simple-editor')
    expect(editor.textContent).toBe('source text')
    expect(editor.dataset.isTarget).toBe('undefined')
    expect(editor.dataset.isRtl).toBe('false')
    expect(editor.dataset.className).toBe('')
  })

  test('renders the read-only target through SimpleEditor', () => {
    const {container} = renderWrapper(makeSegment(), {isTarget: true})

    const wrapper = container.querySelector('#segment-42-target')
    expect(wrapper).not.toBeNull()
    expect(wrapper.className).toBe('target item')

    const editor = screen.getByTestId('simple-editor')
    expect(editor.textContent).toBe('target text')
    expect(editor.dataset.isTarget).toBe('true')
    expect(editor.dataset.isRtl).toBe('true')
    expect(editor.dataset.className).toBe('targetarea editarea')
  })

  test('strips tags when tag projection is enabled for the segment', () => {
    mockCheckCurrentSegmentTPEnabled.mockReturnValue(true)
    renderWrapper(makeSegment())

    expect(mockRemoveTagsFromText).toHaveBeenCalledWith('source text')
    expect(screen.getByTestId('simple-editor').textContent).toBe(
      'stripped:source text',
    )
  })

  test('marks the search hits when the segment is in search', () => {
    renderWrapper(makeSegment({inSearch: true}))

    expect(mockMarkText).toHaveBeenCalledWith('source text', true, '42')
    expect(screen.getByTestId('simple-editor').textContent).toBe(
      'marked:source text',
    )
  })

  test('marks the target search hits with isSource false', () => {
    renderWrapper(makeSegment({inSearch: true}), {isTarget: true})

    expect(mockMarkText).toHaveBeenCalledWith('target text', false, '42')
  })
})
