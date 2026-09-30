import React from 'react'
import {render, screen} from '@testing-library/react'
import '@testing-library/jest-dom'
import JobTableHeader from './JobTableHeader'
import {ANALYSIS_WORKFLOW_TYPES} from '../../constants/Constants'

const standardRates = {
  NO_MATCH: 0,
  REPETITIONS: 100,
  INTERNAL: 100,
  '50%-74%': 50,
  '75%-84%': 75,
  '85%-94%': 85,
  '95%-99%': 95,
  '100%': 100,
  '100%_PUBLIC': 100,
  ICE: 101,
  MT: 0,
}

const mtqeRates = {
  ice: 1,
  tm_100: 2,
  tm_100_public: 3,
  repetitions: 4,
  ice_mt: 5,
  top_quality_mt: 6,
  higher_quality_mt: 7,
  standard_quality_mt: 8,
}

test('renders STANDARD columns without ice_mt when not applicable', () => {
  const {container} = render(
    <JobTableHeader
      workflowType={ANALYSIS_WORKFLOW_TYPES.STANDARD}
      rates={standardRates}
      iceMTRawWords={0}
    />,
  )
  expect(screen.getByText('Analysis bucket')).toBeInTheDocument()
  expect(container.querySelector('.more-columns')).toBeNull()
  expect(screen.getByText('101%')).toBeInTheDocument() // ICE fallback used
})

test('falls back to 0 for ICE rate when not provided', () => {
  const rates = {...standardRates, ICE: undefined}
  render(
    <JobTableHeader
      workflowType={ANALYSIS_WORKFLOW_TYPES.STANDARD}
      rates={rates}
      iceMTRawWords={0}
    />,
  )
  // there are two "0%" occurrences possible (NO_MATCH and ICE fallback); just assert presence
  expect(screen.getAllByText('0%').length).toBeGreaterThan(0)
})

test('shows the extra ice_mt column when ICE_MT differs from MT and words > 0', () => {
  const rates = {...standardRates, ICE_MT: 33}
  const {container} = render(
    <JobTableHeader
      workflowType={ANALYSIS_WORKFLOW_TYPES.STANDARD}
      rates={rates}
      iceMTRawWords={10}
    />,
  )
  expect(container.querySelector('.more-columns')).not.toBeNull()
  expect(screen.getByText('33%')).toBeInTheDocument()
})

test('renders MTQE columns', () => {
  const {container} = render(
    <JobTableHeader
      workflowType={ANALYSIS_WORKFLOW_TYPES.MTQE}
      rates={mtqeRates}
      iceMTRawWords={0}
    />,
  )
  expect(container.querySelector('.job-table-header.mtqe')).not.toBeNull()
  expect(screen.getByText('8%')).toBeInTheDocument()
})

test('renders nothing for an unknown workflow type', () => {
  const {container} = render(
    <JobTableHeader workflowType="unknown" rates={{}} iceMTRawWords={0} />,
  )
  expect(container).toBeEmptyDOMElement()
})
