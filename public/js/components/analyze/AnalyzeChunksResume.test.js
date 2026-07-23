import React from 'react'
import {render, act} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import '@testing-library/jest-dom'
import {fromJS} from 'immutable'

jest.mock('../../actions/ModalsActions')
jest.mock('./CompareTableHeader', () =>
  jest.fn(() => <div data-testid="compare-table-header" />),
)
jest.mock('./SingleChunkJob', () =>
  jest.fn(() => <div data-testid="single-chunk-job" />),
)
jest.mock('./SplitChunkJob', () =>
  jest.fn(() => <div data-testid="split-chunk-job" />),
)

import AnalyzeChunksResume from './AnalyzeChunksResume'
import CompareTableHeader from './CompareTableHeader'
import SingleChunkJob from './SingleChunkJob'
import SplitChunkJob from './SplitChunkJob'
import ModalsActions from '../../actions/ModalsActions'
import CommonUtils from '../../utils/commonUtils'
import UserStore from '../../stores/UserStore'
import {ANALYSIS_STATUS} from '../../constants/Constants'

const buildProject = (overrides = {}) =>
  fromJS({
    jobs: [
      {id: 1, password: 'p1', outsource: null},
      {id: 2, password: 'p2', outsource: {id_vendor: '5'}},
    ],
    analysis: {workflow_type: 'standard'},
    ...overrides,
  })

const buildJobsAnalysis = () => [
  {id: 1, count_unit: 'words', chunks: [{password: 'p1'}]},
  {id: 2, count_unit: 'words', chunks: [{password: 'p2'}, {password: 'p2'}]},
]

beforeEach(() => {
  global.config = {id_project: 99}
  jest.clearAllMocks()
  window.sessionStorage.clear()
})

test('renders a compare header per project job when jobsAnalysis is not provided yet', () => {
  render(
    <AnalyzeChunksResume
      project={buildProject()}
      status={ANALYSIS_STATUS.NEW}
      jobsAnalysis={undefined}
      idJob={1}
      openAnalysisReport={jest.fn()}
    />,
  )
  expect(CompareTableHeader).toHaveBeenCalledTimes(2)
  expect(SingleChunkJob).not.toHaveBeenCalled()
  expect(SplitChunkJob).not.toHaveBeenCalled()
})

test('renders SingleChunkJob for a single-chunk job and SplitChunkJob for a multi-chunk job', () => {
  render(
    <AnalyzeChunksResume
      project={buildProject()}
      status={ANALYSIS_STATUS.DONE}
      jobsAnalysis={buildJobsAnalysis()}
      idJob={1}
      openAnalysisReport={jest.fn()}
    />,
  )
  expect(CompareTableHeader).toHaveBeenCalledTimes(2)
  expect(SingleChunkJob).toHaveBeenCalledTimes(1)
  expect(SplitChunkJob).toHaveBeenCalledTimes(1)

  const headerCalls = CompareTableHeader.mock.calls.map((c) => c[0])
  expect(headerCalls[0].isSplit).toBe(false)
  expect(headerCalls[1].isSplit).toBe(true)
})

test('showDetails ignores clicks inside an outsource-container and forwards others', () => {
  const openAnalysisReport = jest.fn()
  render(
    <AnalyzeChunksResume
      project={buildProject()}
      status={ANALYSIS_STATUS.DONE}
      jobsAnalysis={buildJobsAnalysis()}
      idJob={1}
      openAnalysisReport={openAnalysisReport}
    />,
  )
  const {showDetails} = SingleChunkJob.mock.calls[0][0]

  const insideOutsource = {
    target: {closest: () => true},
    preventDefault: jest.fn(),
    stopPropagation: jest.fn(),
  }
  showDetails(1)(insideOutsource)
  expect(openAnalysisReport).not.toHaveBeenCalled()

  const outsideOutsource = {
    target: {closest: () => null},
    preventDefault: jest.fn(),
    stopPropagation: jest.fn(),
  }
  showDetails(1)(outsideOutsource)
  expect(openAnalysisReport).toHaveBeenCalledWith(1, true)
})

test('openSplitModal finds the job and delegates to ModalsActions', () => {
  const project = buildProject()
  render(
    <AnalyzeChunksResume
      project={project}
      status={ANALYSIS_STATUS.DONE}
      jobsAnalysis={buildJobsAnalysis()}
      idJob={1}
      openAnalysisReport={jest.fn()}
    />,
  )
  const {openSplitModal} = CompareTableHeader.mock.calls[0][0]
  const evt = {stopPropagation: jest.fn(), preventDefault: jest.fn()}
  openSplitModal(1)(evt)

  expect(ModalsActions.openSplitJobModal).toHaveBeenCalledTimes(1)
  const [jobArg, projectArg] = ModalsActions.openSplitJobModal.mock.calls[0]
  expect(jobArg.get('id')).toBe(1)
  expect(projectArg).toBe(project)
})

test('openMergeModal finds the job and delegates to ModalsActions with plain JS', () => {
  render(
    <AnalyzeChunksResume
      project={buildProject()}
      status={ANALYSIS_STATUS.DONE}
      jobsAnalysis={buildJobsAnalysis()}
      idJob={1}
      openAnalysisReport={jest.fn()}
    />,
  )
  const {openMergeModal} = CompareTableHeader.mock.calls[0][0]
  const evt = {stopPropagation: jest.fn(), preventDefault: jest.fn()}
  openMergeModal(2)(evt)

  expect(ModalsActions.openMergeModal).toHaveBeenCalledTimes(1)
  const [projectArg, jobArg] = ModalsActions.openMergeModal.mock.calls[0]
  expect(projectArg.analysis.workflow_type).toBe('standard')
  expect(jobArg.id).toBe(2)
})

test('thereIsChunkOutsourced reflects whether the current idJob has an outsource entry', () => {
  const {rerender} = render(
    <AnalyzeChunksResume
      project={buildProject()}
      status={ANALYSIS_STATUS.DONE}
      jobsAnalysis={buildJobsAnalysis()}
      idJob={2}
      openAnalysisReport={jest.fn()}
    />,
  )
  let call = CompareTableHeader.mock.calls.find((c) => c[0].job.id === 2)[0]
  expect(call.thereIsChunkOutsourced()).toBe(true)

  rerender(
    <AnalyzeChunksResume
      project={buildProject()}
      status={ANALYSIS_STATUS.DONE}
      jobsAnalysis={buildJobsAnalysis()}
      idJob={1}
      openAnalysisReport={jest.fn()}
    />,
  )
  call = CompareTableHeader.mock.calls
    .reverse()
    .find((c) => c[0].job.id === 1)[0]
  expect(call.thereIsChunkOutsourced()).toBe(false)
})

test('handleOpenOutsourceModal is a no-op when status is not DONE', () => {
  const analyticsSpy = jest
    .spyOn(CommonUtils, 'dispatchAnalyticsEvents')
    .mockImplementation(() => {})
  render(
    <AnalyzeChunksResume
      project={buildProject()}
      status={ANALYSIS_STATUS.NEW}
      jobsAnalysis={buildJobsAnalysis()}
      idJob={1}
      openAnalysisReport={jest.fn()}
    />,
  )
  const {handleOpenOutsourceModal} = SingleChunkJob.mock.calls[0][0]
  const evt = {stopPropagation: jest.fn(), preventDefault: jest.fn()}
  handleOpenOutsourceModal(1, {outsource_available: true})(evt)
  expect(analyticsSpy).not.toHaveBeenCalled()
  analyticsSpy.mockRestore()
})

test('handleOpenOutsourceModal opens the outsource box when chunk is outsource_available', () => {
  jest
    .spyOn(CommonUtils, 'dispatchAnalyticsEvents')
    .mockImplementation(() => {})
  render(
    <AnalyzeChunksResume
      project={buildProject()}
      status={ANALYSIS_STATUS.DONE}
      jobsAnalysis={buildJobsAnalysis()}
      idJob={1}
      openAnalysisReport={jest.fn()}
    />,
  )
  const {handleOpenOutsourceModal} = SingleChunkJob.mock.calls[0][0]
  const evt = {stopPropagation: jest.fn(), preventDefault: jest.fn()}
  act(() => {
    handleOpenOutsourceModal(1, {outsource_available: true})(evt)
  })

  expect(CommonUtils.dispatchAnalyticsEvents).toHaveBeenCalledWith({
    event: 'outsource_request',
  })
  // state update: SingleChunkJob should be re-rendered with the new openOutsource state
  const lastCall =
    SingleChunkJob.mock.calls[SingleChunkJob.mock.calls.length - 1][0]
  expect(lastCall.openOutsource).toBe(true)
  expect(lastCall.outsourceJobId).toBe(1)

  CommonUtils.dispatchAnalyticsEvents.mockRestore()
})

test('handleOpenOutsourceModal opens the contact-us page when not outsource_available', () => {
  jest
    .spyOn(CommonUtils, 'dispatchAnalyticsEvents')
    .mockImplementation(() => {})
  window.open = jest.fn()
  render(
    <AnalyzeChunksResume
      project={buildProject()}
      status={ANALYSIS_STATUS.DONE}
      jobsAnalysis={buildJobsAnalysis()}
      idJob={1}
      openAnalysisReport={jest.fn()}
    />,
  )
  const {handleOpenOutsourceModal} = SingleChunkJob.mock.calls[0][0]
  const evt = {stopPropagation: jest.fn(), preventDefault: jest.fn()}
  handleOpenOutsourceModal(1, {outsource_available: false})(evt)

  expect(window.open).toHaveBeenCalledWith(
    'https://translated.com/contact-us',
    '_blank',
  )
  CommonUtils.dispatchAnalyticsEvents.mockRestore()
})

test('closeOutsourceModal resets the outsource state', () => {
  jest
    .spyOn(CommonUtils, 'dispatchAnalyticsEvents')
    .mockImplementation(() => {})
  render(
    <AnalyzeChunksResume
      project={buildProject()}
      status={ANALYSIS_STATUS.DONE}
      jobsAnalysis={buildJobsAnalysis()}
      idJob={1}
      openAnalysisReport={jest.fn()}
    />,
  )
  const {handleOpenOutsourceModal, closeOutsourceModal} =
    SingleChunkJob.mock.calls[0][0]
  const evt = {stopPropagation: jest.fn(), preventDefault: jest.fn()}
  act(() => {
    handleOpenOutsourceModal(1, {outsource_available: true})(evt)
  })
  act(() => {
    closeOutsourceModal()
  })

  const lastCall =
    SingleChunkJob.mock.calls[SingleChunkJob.mock.calls.length - 1][0]
  expect(lastCall.openOutsource).toBe(false)
  expect(lastCall.outsourceJobId).toBe(null)
  CommonUtils.dispatchAnalyticsEvents.mockRestore()
})

test('copyJobLinkToClipboard writes the ref value to the clipboard', () => {
  Object.assign(navigator, {clipboard: {writeText: jest.fn()}})
  render(
    <AnalyzeChunksResume
      project={buildProject()}
      status={ANALYSIS_STATUS.DONE}
      jobsAnalysis={buildJobsAnalysis()}
      idJob={1}
      openAnalysisReport={jest.fn()}
    />,
  )
  const {copyJobLinkToClipboard, jobLinkRef} = SingleChunkJob.mock.calls[0][0]
  jobLinkRef.current[1] = {value: 'http://example.com/job'}
  copyJobLinkToClipboard(1)({stopPropagation: jest.fn()})

  expect(navigator.clipboard.writeText).toHaveBeenCalledWith(
    'http://example.com/job',
  )
})

test('checkPayableChanged records payable changes without throwing', () => {
  render(
    <AnalyzeChunksResume
      project={buildProject()}
      status={ANALYSIS_STATUS.DONE}
      jobsAnalysis={buildJobsAnalysis()}
      idJob={1}
      openAnalysisReport={jest.fn()}
    />,
  )
  const {checkPayableChanged} = SingleChunkJob.mock.calls[0][0]
  expect(() => {
    checkPayableChanged(1, 100)
    checkPayableChanged(1, 150)
  }).not.toThrow()
})

test('getDirectOpenButton renders a Translate button that opens the job url', async () => {
  jest
    .spyOn(CommonUtils, 'dispatchAnalyticsEvents')
    .mockImplementation(() => {})
  UserStore.userInfo = {user: {uid: 42}}
  window.open = jest.fn()

  render(
    <AnalyzeChunksResume
      project={buildProject()}
      status={ANALYSIS_STATUS.DONE}
      jobsAnalysis={buildJobsAnalysis()}
      idJob={1}
      openAnalysisReport={jest.fn()}
    />,
  )
  const {getDirectOpenButton} = SingleChunkJob.mock.calls[0][0]
  const chunk = {id: 1, urls: {t: 'http://example.com/translate'}}

  const {getByText} = render(getDirectOpenButton(chunk, 0))
  await userEvent.click(getByText('Translate'))

  expect(window.open).toHaveBeenCalledWith(
    'http://example.com/translate',
    '_blank',
  )
  expect(CommonUtils.dispatchAnalyticsEvents).toHaveBeenCalledWith(
    expect.objectContaining({event: 'open_job', userId: 42}),
  )

  // a second click should not dispatch analytics again (sessionStorage guard)
  CommonUtils.dispatchAnalyticsEvents.mockClear()
  await userEvent.click(getByText('Translate'))
  expect(CommonUtils.dispatchAnalyticsEvents).not.toHaveBeenCalled()

  UserStore.userInfo = undefined
  CommonUtils.dispatchAnalyticsEvents.mockRestore()
})

test('getDirectOpenButton is disabled when status is not DONE', () => {
  render(
    <AnalyzeChunksResume
      project={buildProject()}
      status={ANALYSIS_STATUS.NEW}
      jobsAnalysis={buildJobsAnalysis()}
      idJob={1}
      openAnalysisReport={jest.fn()}
    />,
  )
  const {getDirectOpenButton} = SingleChunkJob.mock.calls[0][0]
  const chunk = {id: 1, urls: {t: 'http://example.com/translate'}}
  const {getByText} = render(getDirectOpenButton(chunk, 0))
  expect(getByText('Translate').closest('button')).toBeDisabled()
})
