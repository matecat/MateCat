import {render} from '@testing-library/react'
import {fireEvent, screen} from '@testing-library/react'
import React from 'react'
import {fromJS} from 'immutable'
import SegmentQRLine from './SegmentQRLine'

test('renders plain label when onClickLabel is not provided', () => {
  render(
    <SegmentQRLine
      classes="qr-line"
      label="Source"
      text="hello"
      segment={fromJS({})}
    />,
  )
  expect(screen.getByText('Source')).toBeInTheDocument()
})

test('renders word count when showSegmentWords is true', () => {
  render(
    <SegmentQRLine
      classes="qr-line"
      label="Source"
      text="hello"
      showSegmentWords
      segment={fromJS({raw_word_count: 42})}
    />,
  )
  expect(screen.getByText('42')).toBeInTheDocument()
})

test('renders time-to-edit when tte is provided', () => {
  render(
    <SegmentQRLine
      classes="qr-line"
      label="Target"
      text="hi"
      segment={fromJS({})}
      tte={65000}
    />,
  )
  expect(screen.getByText('TTE:')).toBeInTheDocument()
})

test('renders Pre-Translated badge when showIsPretranslated and not rev', () => {
  render(
    <SegmentQRLine
      classes="qr-line"
      label="Target"
      text="hi"
      segment={fromJS({})}
      showIsPretranslated
      rev={false}
    />,
  )
  expect(screen.getByText('Pre-Translated')).toBeInTheDocument()
})

test('renders Pre-Approved badge when showIsPretranslated and rev', () => {
  render(
    <SegmentQRLine
      classes="qr-line"
      label="Target"
      text="hi"
      segment={fromJS({})}
      showIsPretranslated
      rev={true}
    />,
  )
  expect(screen.getByText('Pre-Approved')).toBeInTheDocument()
})

test('omits the spacer for an unlocked ICE match when showIceMatchInfo', () => {
  render(
    <SegmentQRLine
      classes="qr-line"
      label="Target"
      text="hi"
      segment={fromJS({match_type: 'ICE', locked: '0'})}
      showIceMatchInfo
    />,
  )
  expect(screen.queryByTestId('qr-spacer')).not.toBeInTheDocument()
})

test('renders the spacer for a locked non-ICE match when showIceMatchInfo', () => {
  render(
    <SegmentQRLine
      classes="qr-line"
      label="Target"
      text="hi"
      segment={fromJS({match_type: 'MT', locked: '1'})}
      showIceMatchInfo
    />,
  )
  expect(screen.getByTestId('qr-spacer')).toBeInTheDocument()
})

test('renders the suggestion-match percentage when showSuggestionSource and suggestion_match is a number', () => {
  render(
    <SegmentQRLine
      classes="qr-line"
      label="Target"
      text="hi"
      segment={fromJS({match_type: 'MT', suggestion_match: 87})}
      showSuggestionSource
    />,
  )
  expect(screen.getByText('87%')).toBeInTheDocument()
})

// Regression: suggestion_match is parsed with parseInt, so a missing/non-numeric value
// produces NaN — the suggestion-match badge used to render regardless, showing a bare
// "NaN%" instead of nothing.
test('omits the suggestion-match badge when suggestion_match is not a number', () => {
  render(
    <SegmentQRLine
      classes="qr-line"
      label="Target"
      text="hi"
      segment={fromJS({match_type: 'MT'})}
      showSuggestionSource
    />,
  )
  expect(screen.queryByText(/NaN/)).not.toBeInTheDocument()
})

test('shows markup carried by a string text as text, never as HTML', () => {
  render(
    <SegmentQRLine
      classes="qr-line"
      label="Target"
      text="<img src=x onerror=alert(1)>"
      segment={fromJS({})}
    />,
  )
  expect(screen.getByText('<img src=x onerror=alert(1)>')).toBeInTheDocument()
  expect(screen.queryByRole('img')).not.toBeInTheDocument()
})

describe('copying from the text', () => {
  const selectText = (text) => {
    const node = screen.getByText(text)
    const range = document.createRange()
    range.selectNodeContents(node)
    const selection = document.getSelection()
    selection.removeAllRanges()
    selection.addRange(range)
    return node
  }

  beforeEach(() => {
    Object.defineProperty(navigator, 'clipboard', {
      value: {writeText: jest.fn(() => Promise.resolve())},
      configurable: true,
    })
  })

  afterEach(() => document.getSelection().removeAllRanges())

  test('writes the selection as plain text, without zero-width spaces and with · as spaces', () => {
    render(
      <SegmentQRLine
        classes="qr-line"
        label="Target"
        text={'a​b·c'}
        segment={fromJS({})}
      />,
    )
    const node = selectText('a​b·c')

    const notCancelled = fireEvent.copy(node)

    expect(notCancelled).toBe(false)
    expect(navigator.clipboard.writeText).toHaveBeenCalledWith('ab c')
  })

  test('leaves the clipboard alone when a child already wrote its own copy', () => {
    render(
      <SegmentQRLine
        classes="qr-line"
        label="Target"
        text={<span onCopy={(e) => e.preventDefault()}>tagged text</span>}
        segment={fromJS({})}
      />,
    )
    const node = selectText('tagged text')

    fireEvent.copy(node)

    expect(navigator.clipboard.writeText).not.toHaveBeenCalled()
  })
})
