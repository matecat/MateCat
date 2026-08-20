import React from 'react'
import {render} from '@testing-library/react'
import '@testing-library/jest-dom'
import {fromJS} from 'immutable'

jest.mock('./JobAnalyze', () =>
  jest.fn(() => <div data-testid="job-analyze" />),
)

import ProjectAnalyze from './ProjectAnalyze'
import JobAnalyze from './JobAnalyze'

const project = fromJS({
  jobs: [
    {id: 1, password: 'p1'},
    {id: 2, password: 'p2'},
    {id: 1, password: 'p1-dup'}, // duplicate id, should be de-duplicated
    {id: 3, password: 'p3'}, // no matching analysis entry, should be skipped
  ],
})

const volumeAnalysis = fromJS([
  {id: 1, chunks: [{password: 'p1'}]},
  {id: 2, chunks: [{password: 'p2'}]},
])

beforeEach(() => {
  jest.clearAllMocks()
})

test('renders one JobAnalyze per unique matched job id', () => {
  render(
    <ProjectAnalyze
      project={project}
      volumeAnalysis={volumeAnalysis}
      status="DONE"
      jobToScroll={undefined}
    />,
  )
  expect(JobAnalyze).toHaveBeenCalledTimes(2)
  const idsCalled = JobAnalyze.mock.calls.map((c) => c[0].idJob)
  expect(idsCalled).toEqual([1, 2])
})

test('passes chunks, plain jobInfo and status down to JobAnalyze', () => {
  render(
    <ProjectAnalyze
      project={project}
      volumeAnalysis={volumeAnalysis}
      status="DONE"
      jobToScroll={5}
    />,
  )
  const call = JobAnalyze.mock.calls[0][0]
  expect(call.status).toBe('DONE')
  expect(call.jobToScroll).toBe(5)
  expect(call.jobInfo).toEqual({id: 1, chunks: [{password: 'p1'}]})
  // chunks should still be an Immutable structure (not converted to JS)
  expect(typeof call.chunks.toJS).toBe('function')
})

test('memoizes and skips re-render when volumeAnalysis/status/jobToScroll are unchanged', () => {
  const {rerender} = render(
    <ProjectAnalyze
      project={project}
      volumeAnalysis={volumeAnalysis}
      status="DONE"
      jobToScroll={undefined}
    />,
  )
  expect(JobAnalyze).toHaveBeenCalledTimes(2)

  // same values (new object reference but .equals() true for volumeAnalysis)
  rerender(
    <ProjectAnalyze
      project={project}
      volumeAnalysis={fromJS([
        {id: 1, chunks: [{password: 'p1'}]},
        {id: 2, chunks: [{password: 'p2'}]},
      ])}
      status="DONE"
      jobToScroll={undefined}
    />,
  )
  expect(JobAnalyze).toHaveBeenCalledTimes(2)
})

test('re-renders when status changes', () => {
  const {rerender} = render(
    <ProjectAnalyze
      project={project}
      volumeAnalysis={volumeAnalysis}
      status="DONE"
      jobToScroll={undefined}
    />,
  )
  expect(JobAnalyze).toHaveBeenCalledTimes(2)

  rerender(
    <ProjectAnalyze
      project={project}
      volumeAnalysis={volumeAnalysis}
      status="BUSY"
      jobToScroll={undefined}
    />,
  )
  expect(JobAnalyze).toHaveBeenCalledTimes(4)
})
