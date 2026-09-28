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
  const {container} = render(
    <SegmentQRLine
      classes="qr-line"
      label="Target"
      text="hi"
      segment={fromJS({match_type: 'ICE', locked: '0'})}
      showIceMatchInfo
    />,
  )
  expect(container.querySelector('.qr-spec')).toBeNull()
})

test('renders the spacer for a locked non-ICE match when showIceMatchInfo', () => {
  const {container} = render(
    <SegmentQRLine
      classes="qr-line"
      label="Target"
      text="hi"
      segment={fromJS({match_type: 'MT', locked: '1'})}
      showIceMatchInfo
    />,
  )
  expect(container.querySelector('.qr-spec')).not.toBeNull()
})
