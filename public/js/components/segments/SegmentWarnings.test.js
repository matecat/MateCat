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

  test('renders escaped tag names in debug as visible text', () => {
    // `debug` is injected as HTML, so the backend escapes the tag names it mentions.
    // Written raw they would be parsed as markup and vanish from the warning.
    const warnings = {
      ERROR: {
        Categories: {
          cat1: [
            {
              outcome: 1302,
              debug:
                '&lt;ex&gt;, &lt;bx&gt; and/or &lt;g&gt; total count mismatch',
              tip: '',
            },
          ],
        },
      },
    }
    const {container} = render(<SegmentWarnings warnings={warnings} />)

    expect(container.textContent).toContain(
      '<ex>, <bx> and/or <g> total count mismatch',
    )
  })

  test('keeps intentional markup in debug, such as the ICU line breaks', () => {
    const warnings = {
      ERROR: {
        Categories: {
          cat1: [
            {outcome: 30, debug: 'first line<br/><br/>second line', tip: ''},
          ],
        },
      },
    }
    const {container} = render(<SegmentWarnings warnings={warnings} />)

    expect(container.querySelectorAll('br')).toHaveLength(2)
    expect(container.textContent).toContain('first line')
    expect(container.textContent).toContain('second line')
  })

  test('renders a tip as plain text, so raw tag names survive', () => {
    const warnings = {
      ERROR: {
        Categories: {
          cat1: [
            {
              outcome: 29,
              debug: 'File-breaking tag issue',
              tip: 'Should be <g>...</g>',
            },
          ],
        },
      },
    }
    const {container} = render(<SegmentWarnings warnings={warnings} />)

    expect(container.querySelector('.error-solution')).toHaveTextContent(
      'Should be <g>...</g>',
    )
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
})
