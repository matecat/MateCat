import React from 'react'
import {render, screen, act} from '@testing-library/react'
import '@testing-library/jest-dom'
import {fromJS} from 'immutable'

jest.mock('./AnalyzeHeader', () =>
  jest.fn(() => <div data-testid="analyze-header" />),
)
jest.mock('./AnalyzeChunksResume', () =>
  jest.fn(() => <div data-testid="analyze-chunks-resume" />),
)
jest.mock('./ProjectAnalyze', () =>
  jest.fn(() => <div data-testid="project-analyze" />),
)

import AnalyzeMain from './AnalyzeMain'
import AnalyzeHeader from './AnalyzeHeader'
import AnalyzeChunksResume from './AnalyzeChunksResume'
import ProjectAnalyze from './ProjectAnalyze'

const buildVolumeAnalysis = (jobs = [{id: 1}, {id: 2}]) =>
  fromJS({
    summary: {status: 'DONE', segments_analyzed: 1, total_segments: 1},
    jobs,
  })

const project = fromJS({id: 1, name: 'My Project'})

beforeEach(() => {
  jest.clearAllMocks()
})

test('shows the spinner while volumeAnalysis or project is missing', () => {
  const {rerender} = render(
    <AnalyzeMain volumeAnalysis={undefined} project={project} />,
  )
  expect(screen.getByText('Loading Volume Analysis')).toBeInTheDocument()
  expect(AnalyzeHeader).not.toHaveBeenCalled()

  rerender(
    <AnalyzeMain volumeAnalysis={buildVolumeAnalysis()} project={undefined} />,
  )
  expect(screen.getByText('Loading Volume Analysis')).toBeInTheDocument()
})

test('renders only the header when there are no jobs yet', () => {
  render(
    <AnalyzeMain volumeAnalysis={buildVolumeAnalysis([])} project={project} />,
  )
  expect(AnalyzeHeader).toHaveBeenCalledTimes(1)
  expect(AnalyzeChunksResume).not.toHaveBeenCalled()
  expect(ProjectAnalyze).not.toHaveBeenCalled()
})

test('renders header, chunks resume and project analyze once jobs are present', () => {
  const volumeAnalysis = buildVolumeAnalysis()
  render(<AnalyzeMain volumeAnalysis={volumeAnalysis} project={project} />)

  expect(AnalyzeHeader).toHaveBeenCalledTimes(1)
  const headerProps = AnalyzeHeader.mock.calls[0][0]
  expect(headerProps.data.get('status')).toBe('DONE')
  expect(headerProps.project).toBe(project)

  expect(AnalyzeChunksResume).toHaveBeenCalledTimes(1)
  const chunksProps = AnalyzeChunksResume.mock.calls[0][0]
  expect(chunksProps.jobsAnalysis).toEqual([{id: 1}, {id: 2}])
  expect(chunksProps.status).toBe('DONE')

  expect(ProjectAnalyze).toHaveBeenCalledTimes(1)
  const projectAnalyzeProps = ProjectAnalyze.mock.calls[0][0]
  expect(projectAnalyzeProps.status).toBe('DONE')
  expect(projectAnalyzeProps.jobToScroll).toBeUndefined()
})

test('openAnalysisReport updates jobToScroll passed down to ProjectAnalyze', () => {
  const volumeAnalysis = buildVolumeAnalysis()
  render(<AnalyzeMain volumeAnalysis={volumeAnalysis} project={project} />)

  const {openAnalysisReport} = AnalyzeChunksResume.mock.calls[0][0]
  act(() => {
    openAnalysisReport(2)
  })

  const lastProjectAnalyzeCall =
    ProjectAnalyze.mock.calls[ProjectAnalyze.mock.calls.length - 1][0]
  expect(lastProjectAnalyzeCall.jobToScroll).toBe(2)
})
