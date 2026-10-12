import React from 'react'
import {act, render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {fromJS} from 'immutable'
import {ChunksJobContainer} from './ChunksJobContainer'
import ProjectsStore from '../../stores/ProjectsStore'
import ManageConstants from '../../constants/ManageConstants'
import ManageActions from '../../actions/ManageActions'
import ModalsActions from '../../actions/ModalsActions'
import CatToolActions from '../../actions/CatToolActions'
import CommonUtils from '../../utils/commonUtils'

jest.mock('../../actions/ManageActions')
jest.mock('../../actions/ModalsActions')
jest.mock('../../actions/CatToolActions')
jest.mock('../../utils/commonUtils')

jest.mock('./JobMenu', () => ({
  __esModule: true,
  default: (props) => (
    <div data-testid="job-menu-mock">
      <span data-testid="disable-download">
        {props.disableDownload ? 'disabled' : 'enabled'}
      </span>
      <div data-testid="download-label">{props.getDownloadLabel.label}</div>
      <button
        data-testid="download-action"
        onClick={() => props.getDownloadLabel.action()}
      >
        download
      </button>
      <button data-testid="archive-job" onClick={props.archiveJobFn}>
        archive
      </button>
      <button data-testid="activate-job" onClick={props.activateJobFn}>
        activate
      </button>
      <button data-testid="cancel-job" onClick={props.cancelJobFn}>
        cancel
      </button>
      <button data-testid="delete-job" onClick={props.deleteJobFn}>
        delete
      </button>
      <button data-testid="merge-job" onClick={props.openMergeModalFn}>
        merge
      </button>
    </div>
  ),
}))

jest.mock('./JobContainer', () => ({
  JobContainer: ({job, index}) => (
    <div data-testid={`job-container-${job.get('id')}-${index}`} />
  ),
}))

const makeJob = (overrides = {}) =>
  fromJS({
    id: 90,
    password: 'pwd90',
    source: 'en-US',
    target: 'it-IT',
    sourceTxt: 'English US',
    targetTxt: 'Italian',
    status: 'active',
    stats: {raw: {draft: 1, new: 0, total: 10}, equivalent: {total: 10}},
    outsource_available: false,
    ...overrides,
  })

const makeProject = (jobsSize = 2, overrides = {}) =>
  fromJS({
    id: 1,
    project_slug: 'test-project',
    remote_file_service: null,
    jobs: new Array(jobsSize).fill({id: 1}),
    ...overrides,
  })

afterEach(() => jest.clearAllMocks())

test('renders the chunk header with source/target languages and nested JobContainer per chunk', () => {
  const chunks = [makeJob({id: 90}), makeJob({id: 91})]
  const project = makeProject(2)
  const onCheckedJob = jest.fn()

  render(
    <ChunksJobContainer
      chunks={chunks}
      project={project}
      isChecked={false}
      isChunk={true}
      isChunkOutsourced={false}
      onCheckedJob={onCheckedJob}
      downloadTranslationFn={jest.fn()}
    />,
  )

  expect(screen.getByText(/en-US/)).toBeInTheDocument()
  expect(screen.getByText(/it-IT/)).toBeInTheDocument()
  expect(screen.getByTestId('job-container-90-1')).toBeInTheDocument()
  expect(screen.getByTestId('job-container-91-2')).toBeInTheDocument()
})

test('clicking the checkbox notifies onCheckedJob with the first chunk id', async () => {
  const chunks = [makeJob({id: 90})]
  const onCheckedJob = jest.fn()

  render(
    <ChunksJobContainer
      chunks={chunks}
      project={makeProject(2)}
      isChecked={false}
      isChunk={true}
      isChunkOutsourced={false}
      onCheckedJob={onCheckedJob}
      downloadTranslationFn={jest.fn()}
    />,
  )

  await userEvent.click(screen.getByRole('checkbox'))

  expect(onCheckedJob).toHaveBeenCalledWith(90)
})

test('download label defaults to Draft and dispatches analytics before downloading', async () => {
  const downloadTranslationFn = jest.fn()
  const chunks = [
    makeJob({
      id: 90,
      password: 'pwd90',
      stats: {raw: {draft: 1, new: 0, total: 10}, equivalent: {total: 10}},
    }),
  ]
  const project = makeProject(2, {
    project_slug: 'slug',
    remote_file_service: null,
  })

  render(
    <ChunksJobContainer
      chunks={chunks}
      project={project}
      isChecked={false}
      isChunk={true}
      isChunkOutsourced={false}
      onCheckedJob={jest.fn()}
      downloadTranslationFn={downloadTranslationFn}
    />,
  )

  expect(screen.getByTestId('download-label')).toHaveTextContent('Draft')

  await userEvent.click(screen.getByTestId('download-action'))

  expect(CommonUtils.dispatchAnalyticsEvents).toHaveBeenCalledWith({
    event: 'download_draft',
  })
  expect(downloadTranslationFn).toHaveBeenCalledTimes(1)
  const [projectArg, jobArg, urlArg] = downloadTranslationFn.mock.calls[0]
  expect(projectArg).toEqual(project.toJS())
  expect(jobArg).toEqual(chunks[0].toJS())
  expect(urlArg).toContain('?action=warnings')
})

test('download label shows Download Translation when job is translated and no remote service', async () => {
  const downloadTranslationFn = jest.fn()
  const chunks = [
    makeJob({
      stats: {raw: {draft: 0, new: 0, total: 10}, equivalent: {total: 10}},
    }),
  ]
  const project = makeProject(2, {remote_file_service: null})

  render(
    <ChunksJobContainer
      chunks={chunks}
      project={project}
      isChecked={false}
      isChunk={true}
      isChunkOutsourced={false}
      onCheckedJob={jest.fn()}
      downloadTranslationFn={downloadTranslationFn}
    />,
  )

  expect(screen.getByTestId('download-label')).toHaveTextContent(
    'Download Translation',
  )

  await userEvent.click(screen.getByTestId('download-action'))
  expect(downloadTranslationFn).toHaveBeenCalledTimes(1)
})

test('download label shows Open in Google Drive when job is translated via gdrive', () => {
  const chunks = [
    makeJob({
      stats: {raw: {draft: 0, new: 0, total: 10}, equivalent: {total: 10}},
    }),
  ]
  const project = makeProject(2, {remote_file_service: 'gdrive'})

  render(
    <ChunksJobContainer
      chunks={chunks}
      project={project}
      isChecked={false}
      isChunk={true}
      isChunkOutsourced={false}
      onCheckedJob={jest.fn()}
      downloadTranslationFn={jest.fn()}
    />,
  )

  expect(screen.getByTestId('download-label')).toHaveTextContent(
    'Open in Google Drive',
  )
})

test('download label shows Preview in Google Drive when not yet translated via gdrive', () => {
  const chunks = [
    makeJob({
      stats: {raw: {draft: 1, new: 0, total: 10}, equivalent: {total: 10}},
    }),
  ]
  const project = makeProject(2, {remote_file_service: 'gdrive'})

  render(
    <ChunksJobContainer
      chunks={chunks}
      project={project}
      isChecked={false}
      isChunk={true}
      isChunkOutsourced={false}
      onCheckedJob={jest.fn()}
      downloadTranslationFn={jest.fn()}
    />,
  )

  expect(screen.getByTestId('download-label')).toHaveTextContent(
    'Preview in Google Drive',
  )
})

test('openMergeModal calls ModalsActions.openMergeModal with project, job and reloadProjects', async () => {
  const chunks = [makeJob({id: 90})]
  const project = makeProject(2)

  render(
    <ChunksJobContainer
      chunks={chunks}
      project={project}
      isChecked={false}
      isChunk={true}
      isChunkOutsourced={false}
      onCheckedJob={jest.fn()}
      downloadTranslationFn={jest.fn()}
    />,
  )

  await userEvent.click(screen.getByTestId('merge-job'))

  expect(ModalsActions.openMergeModal).toHaveBeenCalledWith(
    project.toJS(),
    chunks[0].toJS(),
    ManageActions.reloadProjects,
  )
})

test('archiveJob changes status and notifies when more than one job exists', async () => {
  const chunks = [makeJob({id: 90})]
  const project = makeProject(2)

  render(
    <ChunksJobContainer
      chunks={chunks}
      project={project}
      isChecked={false}
      isChunk={true}
      isChunkOutsourced={false}
      onCheckedJob={jest.fn()}
      downloadTranslationFn={jest.fn()}
    />,
  )

  await userEvent.click(screen.getByTestId('archive-job'))

  expect(ManageActions.changeJobStatus).toHaveBeenCalledWith(
    project,
    chunks[0],
    'archive',
  )
  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({title: 'Jobs archived'}),
  )
})

test('activateJob changes status and skips notification for a single job project', async () => {
  const chunks = [makeJob({id: 90})]
  const project = makeProject(1)

  render(
    <ChunksJobContainer
      chunks={chunks}
      project={project}
      isChecked={false}
      isChunk={true}
      isChunkOutsourced={false}
      onCheckedJob={jest.fn()}
      downloadTranslationFn={jest.fn()}
    />,
  )

  await userEvent.click(screen.getByTestId('activate-job'))

  expect(ManageActions.changeJobStatus).toHaveBeenCalledWith(
    project,
    chunks[0],
    'active',
  )
  expect(CatToolActions.addNotification).not.toHaveBeenCalled()
})

test('cancelJob changes status and notifies', async () => {
  const chunks = [makeJob({id: 90})]
  const project = makeProject(2)

  render(
    <ChunksJobContainer
      chunks={chunks}
      project={project}
      isChecked={false}
      isChunk={true}
      isChunkOutsourced={false}
      onCheckedJob={jest.fn()}
      downloadTranslationFn={jest.fn()}
    />,
  )

  await userEvent.click(screen.getByTestId('cancel-job'))

  expect(ManageActions.changeJobStatus).toHaveBeenCalledWith(
    project,
    chunks[0],
    'cancel',
  )
  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({title: 'Jobs canceled'}),
  )
})

test('deleteJob opens a confirmation modal whose success callback deletes the job', async () => {
  const chunks = [makeJob({id: 90})]
  const project = makeProject(2)

  render(
    <ChunksJobContainer
      chunks={chunks}
      project={project}
      isChecked={false}
      isChunk={true}
      isChunkOutsourced={false}
      onCheckedJob={jest.fn()}
      downloadTranslationFn={jest.fn()}
    />,
  )

  await userEvent.click(screen.getByTestId('delete-job'))

  expect(ModalsActions.showModalComponent).toHaveBeenCalledTimes(1)
  const [, modalProps] = ModalsActions.showModalComponent.mock.calls[0]

  modalProps.successCallback()
  modalProps.cancelCallback()

  expect(ManageActions.changeJobStatus).toHaveBeenCalledWith(
    project,
    chunks[0],
    'delete',
  )
  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({title: 'Jobs deleted permanently'}),
  )
})

test('toggles disableDownload state on DISABLE/ENABLE_DOWNLOAD_BUTTON store events for the matching job', () => {
  const chunks = [makeJob({id: 90})]

  render(
    <ChunksJobContainer
      chunks={chunks}
      project={makeProject(2)}
      isChecked={false}
      isChunk={true}
      isChunkOutsourced={false}
      onCheckedJob={jest.fn()}
      downloadTranslationFn={jest.fn()}
    />,
  )

  expect(screen.getByTestId('disable-download')).toHaveTextContent('enabled')

  act(() => {
    ProjectsStore.emit(ManageConstants.DISABLE_DOWNLOAD_BUTTON, 90)
  })
  expect(screen.getByTestId('disable-download')).toHaveTextContent('disabled')

  act(() => {
    ProjectsStore.emit(ManageConstants.ENABLE_DOWNLOAD_BUTTON, 90)
  })
  expect(screen.getByTestId('disable-download')).toHaveTextContent('enabled')

  // non-matching job id should not toggle the state
  act(() => {
    ProjectsStore.emit(ManageConstants.DISABLE_DOWNLOAD_BUTTON, 999)
  })
  expect(screen.getByTestId('disable-download')).toHaveTextContent('enabled')
})

test('removes the store listeners on unmount', () => {
  const chunks = [makeJob({id: 90})]

  const {unmount} = render(
    <ChunksJobContainer
      chunks={chunks}
      project={makeProject(2)}
      isChecked={false}
      isChunk={true}
      isChunkOutsourced={false}
      onCheckedJob={jest.fn()}
      downloadTranslationFn={jest.fn()}
    />,
  )

  unmount()

  expect(() => {
    act(() => {
      ProjectsStore.emit(ManageConstants.DISABLE_DOWNLOAD_BUTTON, 90)
    })
  }).not.toThrow()
})
