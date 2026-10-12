import React from 'react'
import {render, fireEvent} from '@testing-library/react'
import SegmentFooterTabConflicts from './SegmentFooterTabConflicts'

jest.mock('../../actions/SegmentActions', () => ({
  setFocusOnEditArea: jest.fn(),
  disableTPOnSegment: jest.fn(),
  replaceEditAreaTextContent: jest.fn(),
  modifiedTranslation: jest.fn(),
  openSegment: jest.fn(),
}))

jest.mock('./utils/DraftMatecatUtils', () => ({
  __esModule: true,
  default: {
    transformTagsToHtml: jest.fn((text) => text),
  },
}))

jest.mock('../../utils/textUtils', () => ({
  __esModule: true,
  default: {
    getDiffHtml: jest.fn((a, b) => `${a}-vs-${b}`),
    execDiff: jest.fn((a, b) => `${a}-diff-${b}`),
    diffMatchPatch: {
      diff_prettyHtml: jest.fn((diffObj) => diffObj),
    },
  },
}))

beforeAll(() => {
  global.config = {
    ...global.config,
    isSourceRTL: false,
    isTargetRTL: false,
  }
})

afterEach(() => {
  jest.clearAllMocks()
})

const baseSegment = {
  sid: '5',
  segment: 'Hello world',
  translation: 'Ciao mondo',
}

const renderComponent = (props = {}) =>
  render(
    <SegmentFooterTabConflicts
      code="al"
      active_class="active"
      tab_class="alternatives"
      segment={baseSegment}
      {...props}
    />,
  )

describe('SegmentFooterTabConflicts', () => {
  test('renders nothing when segment has no alternatives', () => {
    const {container} = renderComponent()
    expect(container).toBeEmptyDOMElement()
  })

  test('renders editable alternatives', () => {
    const {container} = renderComponent({
      segment: {
        ...baseSegment,
        alternatives: {
          editable: [
            {
              id: '1',
              translation: 'Buongiorno mondo',
              involved_id: ['99'],
            },
          ],
          not_editable: [],
        },
      },
    })

    expect(container.querySelectorAll('ul.graysmall')).toHaveLength(1)
    expect(container.querySelector('.goto a')).toHaveTextContent('Go to')
  })

  test('renders not_editable alternatives', () => {
    const {container} = renderComponent({
      segment: {
        ...baseSegment,
        alternatives: {
          editable: [],
          not_editable: [
            {
              id: '2',
              translation: 'Buonasera mondo',
              involved_id: ['100'],
            },
          ],
        },
      },
    })

    expect(container.querySelector('ul.notEditable')).toBeInTheDocument()
  })

  test('double clicking an alternative triggers SegmentActions', () => {
    const SegmentActions = require('../../actions/SegmentActions')
    const {container} = renderComponent({
      segment: {
        ...baseSegment,
        alternatives: {
          editable: [
            {id: '1', translation: 'Buongiorno mondo', involved_id: ['99']},
          ],
          not_editable: [],
        },
      },
    })

    fireEvent.doubleClick(container.querySelector('ul.graysmall'))
    expect(SegmentActions.setFocusOnEditArea).toHaveBeenCalled()
    expect(SegmentActions.disableTPOnSegment).toHaveBeenCalled()
  })

  test('clicking "Go to" triggers SegmentActions.openSegment with involved id', () => {
    const SegmentActions = require('../../actions/SegmentActions')
    const {container} = renderComponent({
      segment: {
        ...baseSegment,
        alternatives: {
          editable: [
            {id: '1', translation: 'Buongiorno mondo', involved_id: ['99']},
          ],
          not_editable: [],
        },
      },
    })

    fireEvent.click(container.querySelector('.goto a'))
    expect(SegmentActions.openSegment).toHaveBeenCalledWith('99')
  })
})
