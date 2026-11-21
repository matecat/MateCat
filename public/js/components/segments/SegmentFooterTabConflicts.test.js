import React from 'react'
import {render, fireEvent, screen} from '@testing-library/react'
import SegmentFooterTabConflicts from './SegmentFooterTabConflicts'
import {setTagSignatureMiddleware} from './utils/DraftMatecatUtils/tagModel'

jest.mock('../../actions/SegmentActions', () => ({
  setFocusOnEditArea: jest.fn(),
  disableTPOnSegment: jest.fn(),
  replaceEditAreaTextContent: jest.fn(),
  modifiedTranslation: jest.fn(),
  openSegment: jest.fn(),
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

// Spaces as plain text, as with the show-whitespace switch off
setTagSignatureMiddleware('space', () => false)

const ALTERNATIVE = {
  id: '1',
  translation: 'Yes world',
  involved_id: ['99'],
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
    expect(container.querySelector('.goto button')).toHaveTextContent('Go to')
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

  test.each([
    ['editable', {editable: [ALTERNATIVE], not_editable: []}],
    ['not editable', {editable: [], not_editable: [ALTERNATIVE]}],
  ])(
    'shows the %s alternative as a diff from the segment translation',
    (kind, alternatives) => {
      renderComponent({
        segment: {...baseSegment, translation: 'Hi world', alternatives},
      })

      expect(screen.getByText('Hi')).toHaveClass('deleted')
      expect(screen.getByText('Yes')).toHaveClass('added')
    },
  )

  test('numbers alternatives from array lists, not editable after editable', () => {
    renderComponent({
      segment: {
        ...baseSegment,
        alternatives: {
          editable: [ALTERNATIVE, {...ALTERNATIVE, id: '2'}],
          not_editable: [{...ALTERNATIVE, id: '3'}],
        },
      },
    })
    expect(
      screen.getAllByRole('list').map((list) => list.dataset.item),
    ).toEqual(['1', '2', '3'])
  })

  test('renders alternatives given as plain objects, keyed like $.each', () => {
    renderComponent({
      segment: {
        ...baseSegment,
        alternatives: {
          editable: {a: ALTERNATIVE},
          not_editable: {b: {...ALTERNATIVE, id: '3'}},
        },
      },
    })
    // Object keys are strings and an object has no length, so the numbering
    // has always been string concatenation.
    expect(
      screen.getAllByRole('list').map((list) => list.dataset.item),
    ).toEqual(['a1', 'bundefined1'])
  })

  test('keeps a tag whole in the diff and decodes entities', () => {
    renderComponent({
      segment: {
        ...baseSegment,
        translation: 'Ciao &amp; mondo',
        alternatives: {
          editable: [
            {
              id: '1',
              translation: 'Ciao &amp; <g id="7">mondo</g>',
              involved_id: ['99'],
            },
          ],
          not_editable: [],
        },
      },
    })

    expect(screen.getByText('Ciao &', {exact: false})).toBeInTheDocument()
    const chips = screen.getAllByText(/7/, {selector: '.tag > .tag-text'})
    expect(chips).toHaveLength(2)
    expect(screen.queryByText(/id=/)).not.toBeInTheDocument()
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

    fireEvent.click(container.querySelector('.goto button'))
    expect(SegmentActions.openSegment).toHaveBeenCalledWith('99')
  })

  test('double clicking a not editable alternative puts its translation in the editor', () => {
    jest.useFakeTimers()
    const SegmentActions = require('../../actions/SegmentActions')
    renderComponent({
      segment: {
        ...baseSegment,
        alternatives: {
          editable: [],
          not_editable: [{...ALTERNATIVE, translation: 'Buonasera mondo'}],
        },
      },
    })

    fireEvent.doubleClick(screen.getByRole('list'))
    jest.runAllTimers()
    jest.useRealTimers()

    expect(SegmentActions.replaceEditAreaTextContent).toHaveBeenCalledWith(
      '5',
      'Buonasera mondo',
    )
  })

  test('clicking "Go to" on a not editable alternative opens its involved segment', () => {
    const SegmentActions = require('../../actions/SegmentActions')
    renderComponent({
      segment: {
        ...baseSegment,
        alternatives: {
          editable: [],
          not_editable: [{...ALTERNATIVE, involved_id: ['100']}],
        },
      },
    })

    fireEvent.click(screen.getByText('Go to'))
    expect(SegmentActions.openSegment).toHaveBeenCalledWith('100')
  })

  test('renders not editable alternatives when the editable list is missing', () => {
    renderComponent({
      segment: {
        ...baseSegment,
        translation: 'Hi world',
        alternatives: {not_editable: [{...ALTERNATIVE, id: '3'}]},
      },
    })
    expect(screen.getAllByRole('list')).toHaveLength(1)
    expect(screen.getByText('Yes')).toHaveClass('added')
  })
})
