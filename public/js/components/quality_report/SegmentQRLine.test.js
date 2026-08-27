import {render} from '@testing-library/react'
import {screen} from '@testing-library/dom'
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
