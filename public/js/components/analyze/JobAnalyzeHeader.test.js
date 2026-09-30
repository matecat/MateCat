import React from 'react'
import {render, screen} from '@testing-library/react'
import '@testing-library/jest-dom'
import JobAnalyzeHeader from './JobAnalyzeHeader'
import {UNIT_COUNT} from '../../constants/Constants'

test('renders id, languages and rounded weighted words count', () => {
  const jobInfo = {
    id: 5,
    source: 'EN',
    target: 'IT',
    total_equivalent: 123.7,
    count_unit: UNIT_COUNT.WORDS,
  }
  render(<JobAnalyzeHeader jobInfo={jobInfo} />)

  expect(screen.getByText('ID: 5')).toBeInTheDocument()
  expect(screen.getByText('EN')).toBeInTheDocument()
  expect(screen.getByText('IT')).toBeInTheDocument()
  expect(screen.getByText('123')).toBeInTheDocument()
  expect(screen.getByText(/Matecat Weighted words/)).toBeInTheDocument()
})

test('shows characters label when count_unit is CHARACTERS', () => {
  const jobInfo = {
    id: 9,
    source: 'FR',
    target: 'DE',
    total_equivalent: 10,
    count_unit: UNIT_COUNT.CHARACTERS,
  }
  render(<JobAnalyzeHeader jobInfo={jobInfo} />)

  expect(screen.getByText(/Matecat weighted characters/)).toBeInTheDocument()
})
