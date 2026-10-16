import React from 'react'
import {fireEvent, render, screen, waitFor} from '@testing-library/react'
import {buildVolumeAnalysis} from '../../../../mocks/volumeAnalysisMock'
import ModalsActions from '../../../actions/ModalsActions'
import {getGlobalWarnings} from '../../../api/getGlobalWarnings'
import {deriveAnalysisView} from '../utils/deriveAnalysisView'
import {DetailsPanel} from './DetailsPanel'

jest.mock('../../../actions/ModalsActions', () => ({
  __esModule: true,
  default: {
    openSplitJobModal: jest.fn(),
    openMergeModal: jest.fn(),
    showDownloadWarningsModal: jest.fn(),
  },
}))

jest.mock('../../../api/getGlobalWarnings', () => ({
  getGlobalWarnings: jest.fn(),
}))

const mockDownloadMenuProps = []
jest.mock('../../common/JobDownloadMenu', () => ({
  JobDownloadMenu: (props) => {
    mockDownloadMenuProps.push(props)
    return <div data-testid="download-menu" />
  },
}))

jest.mock('../OutsourceOfferCard', () => ({
  OutsourceOfferCard: (props) => (
    <div data-testid="offer-card" data-chunk={props.chunk?.password ?? ''} />
  ),
}))

const {jobs, known} = deriveAnalysisView({analysis: buildVolumeAnalysis()})
const [italian, french] = jobs

const doneStats = {raw: {draft: 0, new: 0}}
const draftStats = {raw: {draft: 10, new: 0}}

const project = {
  id: 20,
  password: 'projpass',
  name: 'Analyzed project',
  jobs: [
    {id: 101, password: 'chunkpass1', stats: doneStats, outsource: null},
    {id: 101, password: 'chunkpass2', stats: draftStats, outsource: null},
    {id: 102, password: 'frpass', stats: doneStats, outsource: null},
  ],
}

const renderPanel = (props = {}) =>
  render(
    <DetailsPanel
      project={project}
      job={french}
      chunk={null}
      workflowType="standard"
      known={known}
      actionsEnabled={true}
      onBuy={jest.fn()}
      {...props}
    />,
  )

const lastDownloadMenu = () => mockDownloadMenuProps.at(-1)

describe('DetailsPanel', () => {
  beforeEach(() => {
    mockDownloadMenuProps.length = 0
    jest.clearAllMocks()
    global.config = {
      ...global.config,
      splitEnabled: true,
      splitFeatureAvailable: true,
      isGDriveProject: false,
    }
    jest.spyOn(window, 'open').mockImplementation(() => null)
  })

  afterEach(() => {
    window.open.mockRestore()
  })

  test.each([
    ['an unsplit job', () => ({job: french, chunk: null}), 'ID 102'],
    [
      'a whole split job',
      () => ({job: italian, chunk: null}),
      'Whole job · 2 chunks · ID 101',
    ],
    [
      'a chunk',
      () => ({job: italian, chunk: italian.chunks[1]}),
      'Chunk 2 of 2 · job 101',
    ],
  ])('subtitle of %s', (_, getProps, subtitle) => {
    renderPanel(getProps())
    expect(screen.getByText(subtitle)).toBeInTheDocument()
  })

  test('the title is the language pair', () => {
    renderPanel()
    expect(
      screen.getByRole('heading', {level: 2, name: 'en-US › fr-FR'}),
    ).toBeInTheDocument()
  })

  test('an unsplit job can be split', () => {
    renderPanel()
    expect(
      screen.queryByRole('button', {name: 'Merge'}),
    ).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', {name: 'Split'}))
    expect(ModalsActions.openSplitJobModal).toHaveBeenCalledTimes(1)
    const [job, immutableProject] =
      ModalsActions.openSplitJobModal.mock.calls[0]
    expect(job.get('id')).toBe(102)
    expect(immutableProject.get('id')).toBe(20)
  })

  test('split waits for the analysis and for no outsourced chunk', () => {
    const {unmount} = renderPanel({actionsEnabled: false})
    expect(screen.getByRole('button', {name: 'Split'})).toBeDisabled()
    unmount()

    renderPanel({
      project: {
        ...project,
        jobs: [{id: 102, password: 'frpass', outsource: {id: 1}}],
      },
    })
    expect(screen.getByRole('button', {name: 'Split'})).toBeDisabled()
  })

  test('without split rights the button explains why', () => {
    global.config.splitEnabled = false
    renderPanel()
    const split = screen.getByRole('button', {name: 'Split'})
    expect(split).toHaveAttribute('aria-disabled', 'true')
    fireEvent.click(split)
    expect(ModalsActions.openSplitJobModal).not.toHaveBeenCalled()
  })

  test('no split at all when the feature is unavailable', () => {
    global.config.splitFeatureAvailable = false
    renderPanel()
    expect(
      screen.queryByRole('button', {name: 'Split'}),
    ).not.toBeInTheDocument()
  })

  test.each([
    ['a whole split job', () => null],
    ['a chunk', () => italian.chunks[0]],
  ])('%s can be merged', (_, getChunk) => {
    renderPanel({job: italian, chunk: getChunk()})
    expect(
      screen.queryByRole('button', {name: 'Split'}),
    ).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', {name: 'Merge'}))
    expect(ModalsActions.openMergeModal).toHaveBeenCalledWith(
      project,
      project.jobs[0],
      expect.any(Function),
    )
  })

  test('merge waits for the analysis, like split', () => {
    renderPanel({job: italian, chunk: null, actionsEnabled: false})
    const merge = screen.getByRole('button', {name: 'Merge'})
    expect(merge).toBeDisabled()
    expect(
      screen.getAllByTitle('Available when the analysis is complete'),
    ).toHaveLength(2)
  })

  test('a Google Drive download decides from warnings_count without waiting', () => {
    global.config.isGDriveProject = true
    const proceed = jest.fn()
    const {unmount} = renderPanel({
      project: {
        ...project,
        jobs: [{id: 102, password: 'frpass', warnings_count: 0}],
      },
    })
    lastDownloadMenu().onBeforeDownload(proceed, jest.fn())
    // Synchronously, inside the click: the browser keeps the popup
    expect(proceed).toHaveBeenCalledTimes(1)
    expect(getGlobalWarnings).not.toHaveBeenCalled()
    unmount()

    renderPanel({
      project: {
        ...project,
        jobs: [{id: 102, password: 'frpass', warnings_count: 2}],
      },
    })
    lastDownloadMenu().onBeforeDownload(proceed, jest.fn())
    expect(ModalsActions.showDownloadWarningsModal).toHaveBeenCalledTimes(1)
    expect(proceed).toHaveBeenCalledTimes(1)
    expect(getGlobalWarnings).not.toHaveBeenCalled()
  })

  test('a Google Drive project without warnings_count asks the API', async () => {
    global.config.isGDriveProject = true
    getGlobalWarnings.mockResolvedValue({details: {ERROR: {Categories: {}}}})
    renderPanel({project: {...project, jobs: []}})
    const proceed = jest.fn()
    lastDownloadMenu().onBeforeDownload(proceed, jest.fn())
    await waitFor(() => expect(proceed).toHaveBeenCalledTimes(1))
    expect(getGlobalWarnings).toHaveBeenCalledTimes(1)
  })

  test('the download of a whole split job uses a chunk password', () => {
    renderPanel({job: italian, chunk: null})
    expect(lastDownloadMenu()).toEqual(
      expect.objectContaining({
        jid: 101,
        password: 'chunkpass1',
        isGDriveProject: false,
        // One chunk is still in draft
        isJobCompleted: false,
        disabled: false,
      }),
    )
  })

  test('an unsplit job downloads with its own chunk password', () => {
    renderPanel()
    expect(screen.getByTestId('download-menu')).toBeInTheDocument()
    expect(lastDownloadMenu()).toEqual(
      expect.objectContaining({
        jid: 102,
        password: 'frpass',
        isJobCompleted: true,
      }),
    )
  })

  test('a selected chunk of a split job has no download menu', () => {
    renderPanel({job: italian, chunk: italian.chunks[1]})
    expect(screen.queryByTestId('download-menu')).not.toBeInTheDocument()
    expect(mockDownloadMenuProps).toHaveLength(0)
  })

  test('the download is disabled until the analysis is complete', () => {
    renderPanel({actionsEnabled: false})
    expect(lastDownloadMenu().disabled).toBe(true)
  })

  test('without job stats the job is not completed', () => {
    renderPanel({project: {...project, jobs: []}})
    expect(lastDownloadMenu().isJobCompleted).toBe(false)
  })

  test('the download goes straight on when the job has no errors', async () => {
    getGlobalWarnings.mockResolvedValue({
      details: {ERROR: {Categories: {}}, WARNING: {Categories: {TAGS: [5]}}},
    })
    renderPanel()

    const proceed = jest.fn()
    lastDownloadMenu().onBeforeDownload(proceed, jest.fn())

    await waitFor(() => expect(proceed).toHaveBeenCalledTimes(1))
    expect(getGlobalWarnings).toHaveBeenCalledWith({
      id_job: 102,
      password: 'frpass',
    })
    expect(ModalsActions.showDownloadWarningsModal).not.toHaveBeenCalled()
  })

  test('errors open the download warnings modal', async () => {
    getGlobalWarnings.mockResolvedValue({
      details: {ERROR: {Categories: {TAGS: [1, 2], MISMATCH: [2]}}},
    })
    global.config.splitEnabled = false
    renderPanel()

    const proceed = jest.fn()
    const proceedWithoutErrors = jest.fn()
    lastDownloadMenu().onBeforeDownload(proceed, proceedWithoutErrors)

    await waitFor(() =>
      expect(ModalsActions.showDownloadWarningsModal).toHaveBeenCalledTimes(1),
    )
    const [success, successWithoutErrors, fixIssues, options] =
      ModalsActions.showDownloadWarningsModal.mock.calls[0]
    expect(success).toBe(proceed)
    expect(successWithoutErrors).toBe(proceedWithoutErrors)
    expect(options).toEqual({ownerIsMe: false})
    expect(proceed).not.toHaveBeenCalled()

    fixIssues()
    expect(window.open).toHaveBeenCalledWith(french.chunks[0].urls.t, '_blank')
  })

  test('a whole split job checks every chunk and fixes the one with errors', async () => {
    getGlobalWarnings.mockImplementation(({password}) =>
      Promise.resolve({
        details: {
          ERROR: {Categories: password === 'chunkpass2' ? {TAGS: [9]} : {}},
        },
      }),
    )
    renderPanel({job: italian, chunk: null})

    lastDownloadMenu().onBeforeDownload(jest.fn(), jest.fn())

    await waitFor(() =>
      expect(ModalsActions.showDownloadWarningsModal).toHaveBeenCalled(),
    )
    expect(getGlobalWarnings).toHaveBeenCalledTimes(2)
    const [, , fixIssues, options] =
      ModalsActions.showDownloadWarningsModal.mock.calls[0]
    expect(options).toEqual({ownerIsMe: true})
    fixIssues()
    expect(window.open).toHaveBeenCalledWith(italian.chunks[1].urls.t, '_blank')
  })

  test('a failed warnings call does not block the download', async () => {
    getGlobalWarnings.mockRejectedValue(new Error('offline'))
    renderPanel()
    const proceed = jest.fn()
    lastDownloadMenu().onBeforeDownload(proceed, jest.fn())
    await waitFor(() => expect(proceed).toHaveBeenCalledTimes(1))
  })

  test('shows the totals, the buckets and the files', () => {
    renderPanel()
    expect(screen.getByText('Total word count')).toBeInTheDocument()
    expect(screen.getByText('Industry weighted')).toBeInTheDocument()
    expect(screen.getByText('Matecat weighted')).toBeInTheDocument()
    expect(
      screen.getByRole('table', {name: /Analysis by bucket/}),
    ).toBeInTheDocument()
    expect(screen.getByRole('heading', {name: 'File'})).toBeInTheDocument()
    expect(screen.getByText('manual.docx')).toBeInTheDocument()
  })

  test('a whole split job sums the buckets and files of its chunks', () => {
    renderPanel({job: italian, chunk: null})
    // Several files sit behind a toggle
    fireEvent.click(
      screen.getByRole('button', {name: 'Show the breakdown by file (2)'}),
    )
    // Both chunks' files; the job totals sit in the tiles (the panel's
    // bucket table has no total row)
    expect(screen.getByText('manual.docx')).toBeInTheDocument()
    expect(screen.getByText('guide.docx')).toBeInTheDocument()
    expect(screen.getByText(String(italian.totals.raw))).toBeInTheDocument()
  })

  test('the offer card gets the chunk, or none for a whole split job', () => {
    const {unmount} = renderPanel({job: italian, chunk: null})
    expect(screen.getByTestId('offer-card')).toHaveAttribute('data-chunk', '')
    unmount()

    renderPanel()
    expect(screen.getByTestId('offer-card')).toHaveAttribute(
      'data-chunk',
      'frpass',
    )
  })
})
test('many files sit behind a toggle; the rates note follows the job', () => {
  renderPanel({job: italian, chunk: null})
  expect(
    screen.getByText(
      'Payable rates are set on the job, so all 2 chunks share them.',
    ),
  ).toBeInTheDocument()
  const toggle = screen.getByRole('button', {
    name: 'Show the breakdown by file (2)',
  })
  expect(toggle).toHaveAttribute('aria-expanded', 'false')
  expect(screen.queryByText('guide.docx')).not.toBeInTheDocument()
  fireEvent.click(toggle)
  expect(
    screen.getByRole('button', {name: 'Hide the breakdown by file (2)'}),
  ).toHaveAttribute('aria-expanded', 'true')
})

test('before the analysis ends the breakdown waits', () => {
  renderPanel({known: {raw: true, weighted: false, liveWeighted: true}})
  expect(
    screen.getByText(
      'The breakdown by bucket and by file appears when the analysis is complete.',
    ),
  ).toBeInTheDocument()
  expect(screen.queryByRole('table')).not.toBeInTheDocument()
})
