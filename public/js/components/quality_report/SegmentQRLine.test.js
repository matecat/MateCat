import {render} from '@testing-library/react'
import {screen} from '@testing-library/react'
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

// Regression: a 101% match picked by hand in the editor is stored as suggestion_match 100,
// and match_type stays the analysis result (here MT), so the badge read "TM - 100%".
test('renders 101% when the chosen suggestion was an in-context match', () => {
  render(
    <SegmentQRLine
      classes="qr-line"
      label="Suggestion"
      text="hi"
      segment={fromJS({
        match_type: 'MT',
        suggestion_match: 100,
        suggestion_source: 'TM',
        suggestion_ice: true,
      })}
      showSuggestionSource
    />,
  )
  expect(screen.getByText('TM - 101%')).toBeInTheDocument()
})

test('renders the chosen match when the analysis was ICE but another suggestion was picked', () => {
  render(
    <SegmentQRLine
      classes="qr-line"
      label="Suggestion"
      text="hi"
      segment={fromJS({
        match_type: 'ICE',
        suggestion_match: 85,
        suggestion_source: 'TM',
        suggestion_ice: false,
      })}
      showSuggestionSource
    />,
  )
  expect(screen.getByText('TM - 85%')).toBeInTheDocument()
})

test('falls back to the analysis match type when suggestion_ice is missing', () => {
  render(
    <SegmentQRLine
      classes="qr-line"
      label="Suggestion"
      text="hi"
      segment={fromJS({
        match_type: 'ICE',
        suggestion_match: 100,
        suggestion_source: 'TM',
      })}
      showSuggestionSource
    />,
  )
  expect(screen.getByText('TM - 101%')).toBeInTheDocument()
})
