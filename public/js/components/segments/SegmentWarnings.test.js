import React from 'react'
import {render} from '@testing-library/react'
import '@testing-library/jest-dom'

import SegmentWarnings from './SegmentWarnings'

describe('SegmentWarnings', () => {
  test('renders an empty warnings-block when no warnings are provided', () => {
    const {container} = render(<SegmentWarnings warnings={null} />)
    expect(container.querySelector('.warnings-block')).toBeInTheDocument()
    expect(container.querySelectorAll('.alert-block')).toHaveLength(0)
  })

  test('leaves the warnings it was given untouched', () => {
    const entry = {outcome: 'e1', debug: 'error debug', tip: 'fix it'}
    const warnings = {ERROR: {Categories: {cat1: [entry]}}}

    render(<SegmentWarnings warnings={warnings} />)

    // The class stamped `type` onto this object, which then compared unequal to
    // a freshly supplied set and forced a re-render through the memo comparator.
    expect(entry).not.toHaveProperty('type')
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
    expect(container).toHaveTextContent(/error debug/)
    expect(container).toHaveTextContent(/fix it/)
    expect(container).toHaveTextContent(/info tip/)
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
    expect(container).toHaveTextContent(/first/)
  })

  test('shouldComponentUpdate re-renders when warnings prop changes', () => {
    const initial = {
      ERROR: {Categories: {cat1: [{outcome: 'e1', debug: 'first', tip: ''}]}},
    }
    const updated = {
      ERROR: {Categories: {cat1: [{outcome: 'e2', debug: 'second', tip: ''}]}},
    }
    const {container, rerender} = render(<SegmentWarnings warnings={initial} />)
    expect(container).toHaveTextContent(/first/)

    rerender(<SegmentWarnings warnings={updated} />)
    expect(container).toHaveTextContent(/second/)
  })
})
