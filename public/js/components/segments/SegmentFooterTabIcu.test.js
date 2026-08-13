import {render, screen, act, fireEvent, waitFor} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import React from 'react'
import SegmentFooterTabIcu from './SegmentFooterTabIcu'

jest.mock('../../stores/ApplicationStore', () => ({
  __esModule: true,
  default: {
    getPluralRulesForLocale: jest.fn(() => null),
  },
}))

window.config = {
  ...window.config,
  target_code: 'it-IT',
  isTargetRTL: false,
}

// Suppress expected console output from format-message and ICU parser during tests
beforeEach(() => {
  jest.spyOn(console, 'warn').mockImplementation(() => {})
  jest.spyOn(console, 'error').mockImplementation(() => {})
  jest.spyOn(console, 'log').mockImplementation(() => {})
})
afterEach(() => {
  console.warn.mockRestore()
  console.error.mockRestore()
  console.log.mockRestore()
})

const createSegment = (translation) => ({
  segment: translation,
  translation,
})

describe('SegmentFooterTabIcu', () => {
  test('renders with simple ICU string containing a variable', () => {
    const segment = createSegment('Hello {name}')
    render(
      <SegmentFooterTabIcu
        segment={segment}
        active_class="active"
        tab_class="open"
      />,
    )

    expect(screen.getByText('Test values')).toBeInTheDocument()
    expect(screen.getByText('Live preview')).toBeInTheDocument()
    expect(screen.getByText('name')).toBeInTheDocument()
    // The variable has an input field rendered
    const inputs = document.querySelectorAll('.segment-footer-icu-inputs input')
    expect(inputs.length).toBe(1)
  })

  test('renders "No variables" when the ICU string has no variables', () => {
    const segment = createSegment('Hello world')
    render(
      <SegmentFooterTabIcu
        segment={segment}
        active_class="active"
        tab_class="open"
      />,
    )

    expect(screen.getByText('No variables')).toBeInTheDocument()
  })

  test('renders plural rules section when ICU string contains plural', () => {
    const segment = createSegment(
      '{count, plural, one {# item} other {# items}}',
    )
    render(
      <SegmentFooterTabIcu
        segment={segment}
        active_class="active"
        tab_class="open"
      />,
    )

    expect(screen.getByText('Plural Rules')).toBeInTheDocument()
    expect(screen.getByText('count')).toBeInTheDocument()
    expect(screen.getByText('(number)')).toBeInTheDocument()
  })

  test('renders selectordinal rules section when ICU string contains selectordinal', () => {
    const segment = createSegment(
      '{count, selectordinal, one {#st} two {#nd} few {#rd} other {#th}}',
    )
    render(
      <SegmentFooterTabIcu
        segment={segment}
        active_class="active"
        tab_class="open"
      />,
    )

    expect(screen.getByText('SelectOrdinal Rules')).toBeInTheDocument()
  })

  test('renders number input for plural variables', () => {
    const segment = createSegment(
      '{count, plural, one {# item} other {# items}}',
    )
    render(
      <SegmentFooterTabIcu
        segment={segment}
        active_class="active"
        tab_class="open"
      />,
    )

    const input = screen.getByRole('spinbutton')
    expect(input).toBeInTheDocument()
    expect(input).toHaveAttribute('type', 'number')
  })

  test('renders multiple variable inputs for ICU string with multiple variables', () => {
    const segment = createSegment(
      '{name} has {count, plural, one {# item} other {# items}}',
    )
    render(
      <SegmentFooterTabIcu
        segment={segment}
        active_class="active"
        tab_class="open"
      />,
    )

    expect(screen.getByText('name')).toBeInTheDocument()
    expect(screen.getByText('count')).toBeInTheDocument()
  })

  test('renders select type variables correctly', () => {
    const segment = createSegment(
      '{gender, select, male {He} female {She} other {They}}',
    )
    render(
      <SegmentFooterTabIcu
        segment={segment}
        active_class="active"
        tab_class="open"
      />,
    )

    expect(screen.getByText('gender')).toBeInTheDocument()
    expect(screen.getByText('(text)')).toBeInTheDocument()
  })

  test('handles invalid ICU string gracefully', () => {
    const segment = createSegment('{count, plural, one {# item}')
    render(
      <SegmentFooterTabIcu
        segment={segment}
        active_class="active"
        tab_class="open"
      />,
    )

    expect(screen.getByText('No variables')).toBeInTheDocument()
  })

  test('updates live preview when input value changes', async () => {
    const segment = createSegment('Hello {name}')
    render(
      <SegmentFooterTabIcu
        segment={segment}
        active_class="active"
        tab_class="open"
      />,
    )

    const input = screen.getByRole('textbox')
    await act(async () => {
      fireEvent.change(input, {target: {value: 'World'}})
    })

    await waitFor(() => {
      expect(screen.getByText('Hello World')).toBeInTheDocument()
    })
  })

  test('applies correct CSS classes', () => {
    const segment = createSegment('Hello {name}')
    const {container} = render(
      <SegmentFooterTabIcu
        segment={segment}
        active_class="active"
        tab_class="open"
      />,
    )

    const rootDiv = container.firstChild
    expect(rootDiv).toHaveClass('tab')
    expect(rootDiv).toHaveClass('sub-editor')
    expect(rootDiv).toHaveClass('segment-footer-icu-container')
    expect(rootDiv).toHaveClass('active')
    expect(rootDiv).toHaveClass('open')
  })

  test('applies rtl class when config.isTargetRTL is true', () => {
    window.config.isTargetRTL = true
    const segment = createSegment('Hello {name}')
    render(
      <SegmentFooterTabIcu
        segment={segment}
        active_class="active"
        tab_class="open"
      />,
    )

    const previewDiv = document.querySelector('.segment-footer-icu-preview')
    expect(previewDiv).toHaveClass('rtl')

    window.config.isTargetRTL = false
  })

  test('does not apply rtl class when config.isTargetRTL is false', () => {
    window.config.isTargetRTL = false
    const segment = createSegment('Hello {name}')
    render(
      <SegmentFooterTabIcu
        segment={segment}
        active_class="active"
        tab_class="open"
      />,
    )

    const previewDiv = document.querySelector('.segment-footer-icu-preview')
    expect(previewDiv).not.toHaveClass('rtl')
  })

  test('shows fallback preview message for invalid ICU during preview', () => {
    // Force formatMessage to fail by using an invalid locale setup
    const originalTargetCode = window.config.target_code
    window.config.target_code = 'invalid'

    const segment = createSegment('{count, plural, one {# item}')
    render(
      <SegmentFooterTabIcu
        segment={segment}
        active_class="active"
        tab_class="open"
      />,
    )

    expect(
      screen.getByText('Invalid ICU string, fix it to enable live preview'),
    ).toBeInTheDocument()

    window.config.target_code = originalTargetCode
  })

  test('renders plural rule examples when ApplicationStore returns plural rules', () => {
    const ApplicationStore = require('../../stores/ApplicationStore').default
    // Use a persistent mock (not mockReturnValueOnce): the component calls
    // getPluralRulesForLocale on every render, and the variableNames effect
    // triggers extra re-renders via setValues.
    ApplicationStore.getPluralRulesForLocale.mockReturnValue({
      cardinal: [
        {category: 'one', human_rule: 'is exactly 1', example: '1'},
        {category: 'other', human_rule: 'is not 1', example: '2, 3, 4…'},
      ],
      ordinal: [{category: 'one', human_rule: '1st, 21st...', example: '1st'}],
    })

    const segment = createSegment(
      '{count, plural, one {# item} other {# items}}',
    )
    render(
      <SegmentFooterTabIcu
        segment={segment}
        active_class="active"
        tab_class="open"
      />,
    )

    expect(screen.getByText('is exactly 1')).toBeInTheDocument()
    expect(screen.getByText('is not 1')).toBeInTheDocument()

    ApplicationStore.getPluralRulesForLocale.mockReturnValue(null)
  })

  test('renders selectordinal rule examples when ApplicationStore returns ordinal rules', () => {
    const ApplicationStore = require('../../stores/ApplicationStore').default
    ApplicationStore.getPluralRulesForLocale.mockReturnValue({
      cardinal: [],
      ordinal: [{category: 'one', human_rule: '1st, 21st...', example: '1st'}],
    })

    const segment = createSegment(
      '{count, selectordinal, one {#st} two {#nd} few {#rd} other {#th}}',
    )
    render(
      <SegmentFooterTabIcu
        segment={segment}
        active_class="active"
        tab_class="open"
      />,
    )

    expect(screen.getByText('1st, 21st...')).toBeInTheDocument()

    ApplicationStore.getPluralRulesForLocale.mockReturnValue(null)
  })

  test('updates preview when a date variable value changes', async () => {
    const segment = createSegment('Event on {eventDate, date}')
    render(
      <SegmentFooterTabIcu
        segment={segment}
        active_class="active"
        tab_class="open"
      />,
    )

    const input = document.querySelector('input[type="date"]')
    expect(input).toBeInTheDocument()

    await act(async () => {
      fireEvent.change(input, {target: {value: '2024-05-01'}})
    })

    expect(input).toHaveValue('2024-05-01')
  })

  test('updates preview when a time variable value changes', async () => {
    const segment = createSegment('Time is {eventTime, time}')
    render(
      <SegmentFooterTabIcu
        segment={segment}
        active_class="active"
        tab_class="open"
      />,
    )

    const input = document.querySelector('input[type="time"]')
    expect(input).toBeInTheDocument()

    await act(async () => {
      fireEvent.change(input, {target: {value: '10:30'}})
    })

    expect(input).toHaveValue('10:30')
  })
})
