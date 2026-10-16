import React from 'react'
import {fireEvent, render, screen, within} from '@testing-library/react'
import {
  buildVolumeAnalysis,
  projectMock,
} from '../../../../mocks/volumeAnalysisMock'
import CommonUtils from '../../../utils/commonUtils'
import {deriveAnalysisView} from '../utils/deriveAnalysisView'
import {getDefaultSelection, ProjectTreeAnalysis} from './ProjectTreeAnalysis'

jest.mock('../../outsource/OutsourceOrderPanel', () => {
  const MockOrderPanel = ({job}) => (
    <div data-testid="order-form">{job.get('password')}</div>
  )
  return {
    __esModule: true,
    default: MockOrderPanel,
    OutsourceOrderPanel: MockOrderPanel,
  }
})

// The tree and the panel are tested on their own; here only the wiring
jest.mock('../OutsourceOfferCard', () => ({
  ...jest.requireActual('../OutsourceOfferCard'),
  OutsourceOfferCard: () => null,
}))

const project = {
  ...projectMock,
  jobs: [
    {id: 101, password: 'chunkpass1'},
    {id: 101, password: 'chunkpass2'},
    {id: 102, password: 'frpass'},
  ],
}

const renderPage = (analysis = buildVolumeAnalysis()) =>
  render(
    <ProjectTreeAnalysis
      project={project}
      view={deriveAnalysisView({analysis})}
      workflowType="standard"
    />,
  )

const panel = () => screen.getByRole('region', {name: /^en-US › /})

describe('ProjectTreeAnalysis', () => {
  beforeEach(() => {
    global.config = {...global.config, enable_outsource: true}
    jest.spyOn(window, 'open').mockImplementation(() => null)
    jest.spyOn(CommonUtils, 'dispatchAnalyticsEvents').mockImplementation()
  })

  afterEach(() => jest.restoreAllMocks())

  test('the first split job is selected as a whole, else the first job', () => {
    const {jobs} = deriveAnalysisView({analysis: buildVolumeAnalysis()})
    expect(getDefaultSelection(jobs)).toEqual({jobId: 101, chunkIndex: null})
    expect(getDefaultSelection([jobs[1]])).toEqual({
      jobId: 102,
      chunkIndex: null,
    })
    expect(getDefaultSelection([])).toBeNull()
  })

  test('shows the title, the header and the selected job details', () => {
    renderPage()
    expect(
      screen.getByRole('heading', {level: 1, name: 'Project analysis'}),
    ).toBeInTheDocument()
    expect(
      within(panel()).getByText('Whole job · 2 chunks · ID 101'),
    ).toBeInTheDocument()
  })

  test('selecting a chunk shows it in the panel', () => {
    renderPage()
    fireEvent.click(
      screen.getByRole('button', {name: 'Chunks of en-US › it-IT'}),
    )
    fireEvent.click(
      screen.getByRole('button', {name: 'en-US › it-IT, chunk 2'}),
    )
    expect(
      within(panel()).getByText('Chunk 2 of 2 · job 101'),
    ).toBeInTheDocument()
  })

  test('buy on a row selects it and opens the order drawer for that chunk', () => {
    renderPage()
    const frenchRow = screen.getByRole('row', {name: /^en-US › fr-FR ID/})
    fireEvent.click(
      within(frenchRow).getByRole('button', {
        name: 'Buy translation from Translated',
      }),
    )

    expect(CommonUtils.dispatchAnalyticsEvents).toHaveBeenCalledTimes(1)
    expect(CommonUtils.dispatchAnalyticsEvents).toHaveBeenCalledWith({
      event: 'outsource_request',
    })
    const dialog = screen.getByRole('dialog', {
      name: 'Let Translated do it for you',
    })
    expect(within(dialog).getByTestId('order-form')).toHaveTextContent('frpass')
    expect(within(panel()).getByText('ID 102')).toBeInTheDocument()

    fireEvent.keyDown(document, {key: 'Escape'})
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  test('a job that cannot be outsourced sends to the contact page', () => {
    const analysis = buildVolumeAnalysis()
    analysis.jobs[1].outsource_available = false
    renderPage(analysis)
    const frenchRow = screen.getByRole('row', {name: /^en-US › fr-FR ID/})
    fireEvent.click(
      within(frenchRow).getByRole('button', {
        name: 'Buy translation from Translated',
      }),
    )
    expect(window.open).toHaveBeenCalledWith(
      'https://translated.com/contact-us',
      '_blank',
    )
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(CommonUtils.dispatchAnalyticsEvents).not.toHaveBeenCalled()
  })

  test('the status card shows while the analysis runs', () => {
    renderPage(
      buildVolumeAnalysis({summary: {status: 'FAST_OK', segments_analyzed: 4}}),
    )
    expect(
      screen.getByRole('status', {name: 'Analysis status'}),
    ).toBeInTheDocument()
  })
})
