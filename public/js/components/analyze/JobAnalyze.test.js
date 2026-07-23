import React from 'react'
import {render, act} from '@testing-library/react'
import '@testing-library/jest-dom'
import {fromJS} from 'immutable'

const mockAnimate = jest.fn()
jest.mock('jquery', () => {
  const $ = jest.fn(() => ({
    animate: (...args) => mockAnimate(...args),
    offset: () => ({top: 100}),
  }))
  return $
})

jest.mock('./ChunkAnalyze', () =>
  jest.fn(() => <div data-testid="chunk-analyze" />),
)
jest.mock('./JobAnalyzeHeader', () =>
  jest.fn(() => <div data-testid="job-analyze-header" />),
)
jest.mock('./JobTableHeader', () =>
  jest.fn(() => <div data-testid="job-table-header" />),
)

import JobAnalyze from './JobAnalyze'
import ChunkAnalyze from './ChunkAnalyze'
import JobTableHeader from './JobTableHeader'

const buildProject = () =>
  fromJS({
    jobs: [{id: 1, password: 'p1'}],
    analysis: {workflow_type: 'standard'},
  })

const buildChunks = () => fromJS([{password: 'p1', files: []}])

const buildJobInfo = (overrides = {}) => ({
  chunks: [{password: 'p1', summary: [{type: 'ice_mt', raw: 5}]}],
  payable_rates: {NO_MATCH: 0},
  ...overrides,
})

beforeEach(() => {
  jest.clearAllMocks()
  jest.useFakeTimers()
})

afterEach(() => {
  act(() => {
    jest.runOnlyPendingTimers()
  })
  jest.useRealTimers()
})

test('renders header, table header and one ChunkAnalyze per chunk', () => {
  render(
    <JobAnalyze
      chunks={buildChunks()}
      jobInfo={buildJobInfo()}
      project={buildProject()}
      idJob={1}
      status="DONE"
    />,
  )
  expect(ChunkAnalyze).toHaveBeenCalledTimes(1)
  const call = ChunkAnalyze.mock.calls[0][0]
  expect(call.index).toBe(1)
  expect(call.chunksSize).toBe(1)
  expect(call.workflowType).toBe('standard')
})

test('renders an empty chunks-analyze section when chunks is falsy', () => {
  const {container} = render(
    <JobAnalyze
      chunks={undefined}
      jobInfo={buildJobInfo()}
      project={buildProject()}
      idJob={1}
      status="DONE"
    />,
  )
  expect(ChunkAnalyze).not.toHaveBeenCalled()
  expect(container.querySelector('.chunks-analyze')).toBeEmptyDOMElement()
})

test('sums ice_mt raw words across chunks and forwards to JobTableHeader', () => {
  const jobInfo = buildJobInfo({
    chunks: [
      {password: 'p1', summary: [{type: 'ice_mt', raw: 5}]},
      {password: 'p2', summary: [{type: 'new', raw: 2}]},
    ],
  })
  render(
    <JobAnalyze
      chunks={fromJS([
        {password: 'p1', files: []},
        {password: 'p2', files: []},
      ])}
      jobInfo={jobInfo}
      project={buildProject()}
      idJob={1}
      status="DONE"
    />,
  )
  const call = JobTableHeader.mock.calls[0][0]
  expect(call.iceMTRawWords).toBe(5)
})

test('scrolls to the job element when jobToScroll matches idJob', () => {
  render(
    <JobAnalyze
      chunks={buildChunks()}
      jobInfo={buildJobInfo()}
      project={buildProject()}
      idJob={1}
      status="DONE"
      jobToScroll={1}
    />,
  )
  act(() => {
    jest.runOnlyPendingTimers()
  })
  expect(mockAnimate).toHaveBeenCalled()
})

test('does not scroll when jobToScroll does not match idJob', () => {
  render(
    <JobAnalyze
      chunks={buildChunks()}
      jobInfo={buildJobInfo()}
      project={buildProject()}
      idJob={1}
      status="DONE"
      jobToScroll={2}
    />,
  )
  act(() => {
    jest.runOnlyPendingTimers()
  })
  expect(mockAnimate).not.toHaveBeenCalled()
})
