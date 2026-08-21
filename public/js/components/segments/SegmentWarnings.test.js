import React from 'react'
import {render} from '@testing-library/react'
import '@testing-library/jest-dom'
import {forOwn} from 'lodash'

import SegmentWarnings from './SegmentWarnings'

jest.mock('lodash', () => {
  const actual = jest.requireActual('lodash')
  return {...actual, forOwn: jest.fn(actual.forOwn)}
})

describe('SegmentWarnings', () => {
  test('renders an empty warnings-block when no warnings are provided', () => {
    const {container} = render(<SegmentWarnings warnings={null} />)
    expect(container.querySelector('.warnings-block')).toBeInTheDocument()
    expect(container.querySelectorAll('.alert-block')).toHaveLength(0)
  })

  test('renders ERROR, WARNING and INFO entries with correct classes and icons', () => {
    const warnings = {
      ERROR: {
        Categories: {
          cat1: [{outcome: 'e1', debug: 'error debug', tip: 'fix it'}],
        },
      },
      WARNING: {
        Categories: {
          cat1: [{outcome: 'w1', debug: 'warning debug', tip: ''}],
        },
      },
      INFO: {
        Categories: {
          cat1: [{outcome: 'i1', debug: 'info debug', tip: 'info tip'}],
        },
      },
    }
    const {container} = render(<SegmentWarnings warnings={warnings} />)

    expect(container.querySelector('.error-alert')).toBeInTheDocument()
    expect(container.querySelector('.warning-alert')).toBeInTheDocument()
    expect(container.querySelector('.info-alert')).toBeInTheDocument()
    expect(container.querySelectorAll('.alert-block')).toHaveLength(3)
    expect(container.textContent).toContain('error debug')
    expect(container.textContent).toContain('fix it')
    expect(container.textContent).toContain('info tip')
  })

  test('does not render a tip paragraph when tip is an empty string', () => {
    const warnings = {
      WARNING: {
        Categories: {
          cat1: [{outcome: 'w1', debug: 'warning debug', tip: ''}],
        },
      },
    }
    const {container} = render(<SegmentWarnings warnings={warnings} />)
    expect(container.querySelector('.error-solution')).not.toBeInTheDocument()
  })

  test('counts duplicate outcomes within the same category instead of duplicating entries', () => {
    const warnings = {
      ERROR: {
        Categories: {
          cat1: [
            {outcome: 'dup', debug: 'first', tip: ''},
            {outcome: 'dup', debug: 'second', tip: ''},
          ],
        },
      },
    }
    const {container} = render(<SegmentWarnings warnings={warnings} />)
    expect(container.querySelectorAll('.alert-block')).toHaveLength(1)
    expect(container.textContent).toContain('first')
  })

  test('shouldComponentUpdate re-renders when warnings prop changes', () => {
    const initial = {
      ERROR: {Categories: {cat1: [{outcome: 'e1', debug: 'first', tip: ''}]}},
    }
    const updated = {
      ERROR: {Categories: {cat1: [{outcome: 'e2', debug: 'second', tip: ''}]}},
    }
    const {container, rerender} = render(<SegmentWarnings warnings={initial} />)
    expect(container.textContent).toContain('first')

    rerender(<SegmentWarnings warnings={updated} />)
    expect(container.textContent).toContain('second')
  })

  test('memo bails out (skips re-render) when warnings is deep-equal but a new object reference', () => {
    // fnMap() mutates each warning entry in place (`item.type = type`), aliasing the actual
    // props object — a pre-existing characteristic of this component, unrelated to this
    // migration. Pre-setting `type` here keeps both objects deep-equal even after that
    // in-render mutation runs on the first one, so this test isolates the memo comparator
    // behavior instead of tripping over that mutation quirk.
    const warnings = {
      ERROR: {
        Categories: {
          cat1: [{outcome: 'e1', debug: 'first', tip: '', type: 'ERROR'}],
        },
      },
    }
    const sameShapeNewReference = {
      ERROR: {
        Categories: {
          cat1: [{outcome: 'e1', debug: 'first', tip: '', type: 'ERROR'}],
        },
      },
    }

    forOwn.mockClear()
    const {rerender} = render(<SegmentWarnings warnings={warnings} />)
    const callsAfterFirstRender = forOwn.mock.calls.length
    expect(callsAfterFirstRender).toBeGreaterThan(0)

    rerender(<SegmentWarnings warnings={sameShapeNewReference} />)
    expect(forOwn.mock.calls.length).toBe(callsAfterFirstRender)
  })
})
