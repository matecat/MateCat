import React from 'react'
import {fireEvent, render, screen, within} from '@testing-library/react'
import {
  buildVolumeAnalysis,
  projectMock,
} from '../../../../mocks/volumeAnalysisMock'
import CommonUtils from '../../../utils/commonUtils'
import {deriveAnalysisView} from '../utils/deriveAnalysisView'
import {getJobSubtitle, SingleJobAnalysis} from './SingleJobAnalysis'

jest.mock('../../outsource/OutsourceContainer', () => {
  const MockOutsourceContainer = ({job}) => (
    <div data-testid="order-form">{job.get('password')}</div>
  )
  return MockOutsourceContainer
})

// The offer card is tested on its own; here it only hands the chunk over
jest.mock('../OutsourceOfferCard', () => ({
  ...jest.requireActual('../OutsourceOfferCard'),
  OutsourceOfferCard: ({job, chunk, onBuy}) => (
    <button type="button" onClick={() => onBuy(job, chunk)}>
      Buy this job
    </button>
  ),
}))

const singleAnalysis = (summary) => {
  const analysis = buildVolumeAnalysis({summary})
  return {...analysis, jobs: [analysis.jobs[1]]}
}

const project = {
  ...projectMock,
  jobs: [{id: 102, password: 'frpass', outsource: null}],
}

const renderPage = (summary) => {
  const view = deriveAnalysisView({analysis: singleAnalysis(summary)})
  render(
    <SingleJobAnalysis project={project} view={view} workflowType="standard" />,
  )
  return view
}

describe('SingleJobAnalysis', () => {
  beforeEach(() => {
    global.config = {
      ...global.config,
      enable_outsource: true,
      splitEnabled: true,
      splitFeatureAvailable: true,
    }
    jest.spyOn(window, 'open').mockImplementation(() => null)
    jest.spyOn(CommonUtils, 'dispatchAnalyticsEvents').mockImplementation()
  })

  afterEach(() => jest.restoreAllMocks())

  test('shows the project, then the job with its totals, buckets and files', () => {
    renderPage()
    expect(
      screen.getByRole('heading', {level: 1, name: 'Project analysis'}),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('heading', {name: 'Analyzed project'}),
    ).toBeInTheDocument()
    // One language: no combinations badge
    expect(screen.queryByText(/language combinations/)).not.toBeInTheDocument()

    const summary = screen.getByRole('region', {name: 'en-US › fr-FR'})
    expect(within(summary).getByText('Job ID 102 · 120 words')).toBeVisible()
    expect(within(summary).getByText('Words in the source')).toBeVisible()
    expect(
      within(summary).getByRole('button', {name: 'Translate'}),
    ).toBeEnabled()
    expect(within(summary).getByRole('button', {name: 'Split'})).toBeEnabled()
    expect(
      within(summary).getByRole('button', {name: 'Copy the fr-FR link'}),
    ).toBeEnabled()

    expect(
      screen.getByRole('table', {name: /Analysis by bucket/}),
    ).toBeInTheDocument()
    expect(screen.getByRole('heading', {name: 'File'})).toBeInTheDocument()
    expect(screen.getByText('manual.docx')).toBeInTheDocument()
    // No tree on this layout
    expect(
      screen.queryByRole('region', {name: 'Language combinations'}),
    ).not.toBeInTheDocument()
  })

  test('while the analysis runs, actions wait and the cards explain why', () => {
    renderPage({status: 'FAST_OK', segments_analyzed: 10})
    expect(
      screen.getByRole('region', {name: 'Analysis status'}),
    ).toBeInTheDocument()
    expect(screen.getByRole('button', {name: 'Translate'})).toBeDisabled()
    expect(screen.getByRole('button', {name: 'Split'})).toBeDisabled()
    expect(
      screen.getByText(
        'The breakdown by bucket appears when the analysis is complete.',
      ),
    ).toBeInTheDocument()
    expect(
      screen.getByText(
        'The words per file appear when the analysis is complete.',
      ),
    ).toBeInTheDocument()
  })

  test('translate opens the job in the CAT tool', () => {
    const view = renderPage()
    fireEvent.click(screen.getByRole('button', {name: 'Translate'}))
    expect(window.open).toHaveBeenCalledWith(
      view.jobs[0].chunks[0].urls.t,
      '_blank',
    )
  })

  test('buy opens the order drawer and tracks the request', () => {
    renderPage()
    fireEvent.click(screen.getByRole('button', {name: 'Buy this job'}))
    const dialog = screen.getByRole('dialog', {name: 'en-US › fr-FR'})
    expect(within(dialog).getByTestId('order-form')).toHaveTextContent('frpass')
    expect(CommonUtils.dispatchAnalyticsEvents).toHaveBeenCalledWith({
      event: 'outsource_request',
    })
  })

  test('getJobSubtitle', () => {
    const job = {id: 7, totals: {raw: 1500}, countUnit: 'words'}
    expect(getJobSubtitle(job, true)).toBe('Job ID 7 · 1,500 words')
    expect(getJobSubtitle(job, false)).toBe('Job ID 7 · counting words…')
    expect(getJobSubtitle({...job, countUnit: 'characters'}, true)).toBe(
      'Job ID 7 · 1,500 characters',
    )
  })
})
