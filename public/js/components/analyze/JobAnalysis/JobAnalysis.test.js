import React from 'react'
import {render, screen, within} from '@testing-library/react'
import {
  buildVolumeAnalysis,
  projectMock,
} from '../../../../mocks/volumeAnalysisMock'
import {deriveAnalysisView} from '../utils/deriveAnalysisView'
import {JobAnalysis} from './JobAnalysis'

const mockDownloadMenuProps = []
jest.mock('../../common/JobDownloadMenu', () => ({
  JobDownloadMenu: (props) => {
    mockDownloadMenuProps.push(props)
    return <div data-testid="download-menu" />
  },
}))

// What useVolumeAnalysis hands the job page: the job with the opened chunk
const jobPageAnalysis = (jobIndex, chunkIndex) => {
  const analysis = buildVolumeAnalysis()
  const job = analysis.jobs[jobIndex]
  return {
    ...analysis,
    jobs: [
      {
        ...job,
        chunks: [job.chunks[chunkIndex]],
        chunkPosition: chunkIndex + 1,
        chunkCount: job.chunks.length,
      },
    ],
  }
}

const renderPage = (jobIndex, chunkIndex) => {
  const view = deriveAnalysisView({
    analysis: jobPageAnalysis(jobIndex, chunkIndex),
    jobAnalysis: true,
  })
  render(
    <JobAnalysis
      project={{...projectMock, password: undefined}}
      view={view}
      workflowType="standard"
    />,
  )
  return view
}

describe('JobAnalysis', () => {
  beforeEach(() => {
    mockDownloadMenuProps.length = 0
    global.config = {
      ...global.config,
      enable_outsource: true,
      splitEnabled: true,
      splitFeatureAvailable: true,
    }
  })

  test('a chunk of a split job shows its place, totals, buckets and files', () => {
    const view = renderPage(0, 1)
    expect(
      screen.getByRole('heading', {level: 1, name: 'Job analysis'}),
    ).toBeInTheDocument()
    const summary = screen.getByRole('region', {name: 'en-US › it-IT'})
    expect(within(summary).getByText('Chunk 2 of 2')).toBeVisible()
    expect(within(summary).getByText('Job ID 101')).toBeVisible()
    expect(within(summary).getByText('Complete')).toBeVisible()
    expect(
      within(summary).getByRole('link', {name: 'Back to translation'}),
    ).toHaveAttribute('href', view.jobs[0].chunks[0].urls.t)
    // The chunk the translator opened, not the first one of the job
    expect(
      within(summary).getByRole('link', {name: 'Back to translation'}),
    ).toHaveAttribute('href', expect.stringContaining('101-chunkpass2'))

    expect(
      screen.getByRole('table', {name: /Analysis by bucket/}),
    ).toBeInTheDocument()
    expect(screen.getByText('guide.docx')).toBeInTheDocument()
  })

  test('nothing to buy, split or merge on the job page', () => {
    // An unsplit job, with split enabled and outsourcing on: still neither
    renderPage(1, 0)
    expect(screen.getByTestId('download-menu')).toBeInTheDocument()
    expect(screen.queryByRole('button', {name: /Buy/})).not.toBeInTheDocument()
    expect(
      screen.queryByRole('button', {name: 'Split'}),
    ).not.toBeInTheDocument()
    expect(
      screen.queryByRole('button', {name: 'Merge'}),
    ).not.toBeInTheDocument()
  })

  test('a chunk of a split job has no download', () => {
    renderPage(0, 0)
    expect(screen.queryByTestId('download-menu')).not.toBeInTheDocument()
  })

  test('an unsplit job downloads with its own password', () => {
    renderPage(1, 0)
    expect(screen.queryByText(/^Chunk /)).not.toBeInTheDocument()
    expect(mockDownloadMenuProps.at(-1)).toEqual(
      expect.objectContaining({jid: 102, password: 'frpass', disabled: false}),
    )
  })

  test('before the analysis is complete the page asks for a reload', () => {
    const analysis = jobPageAnalysis(1, 0)
    analysis.summary = {...analysis.summary, status: 'FAST_OK'}
    render(
      <JobAnalysis
        project={projectMock}
        view={deriveAnalysisView({analysis, jobAnalysis: true})}
        workflowType="standard"
      />,
    )
    expect(
      screen.getByText(
        'The analysis is still running. Reload the page once it is complete to see the breakdown by bucket.',
      ),
    ).toBeInTheDocument()
    expect(
      screen.getByText(
        'The analysis is still running. Reload the page once it is complete to see the words per file.',
      ),
    ).toBeInTheDocument()
  })
})
