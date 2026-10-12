import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'
import {DownloadMenu} from './DownloadMenu'
import CommonUtils from '../../../utils/commonUtils'
import CatToolStore from '../../../stores/CatToolStore'
import CattolConstants from '../../../constants/CatToolConstants'
import SegmentStore from '../../../stores/SegmentStore'
import ModalsActions from '../../../actions/ModalsActions'
import CatToolActions from '../../../actions/CatToolActions'
import DownloadFileUtils from '../../../utils/downloadFileUtils'

jest.mock('../../../utils/commonUtils')
jest.mock('../../../actions/ModalsActions')
jest.mock('../../../actions/CatToolActions')
jest.mock('../../../utils/downloadFileUtils')

jest.mock('../../common/DropdownMenu/DropdownMenu', () => {
  const actual = jest.requireActual('../../common/DropdownMenu/DropdownMenu')
  return {
    ...actual,
    // Isolate DownloadMenu's own click handlers from DropdownMenu's internal
    // Radix rendering, which is covered by its own directory.
    DropdownMenu: ({toggleButtonProps, items}) => (
      <div data-testid="download-menu">
        <button data-testid="toggle-button" onClick={toggleButtonProps.onClick}>
          {toggleButtonProps.children}
        </button>
        {items.map((item, index) => (
          <div key={index} onClick={item.onClick}>
            {item.label}
          </div>
        ))}
      </div>
    ),
  }
})

beforeEach(() => {
  global.config = {
    id_job: '10',
    password: 'secret',
    isGDriveProject: false,
  }
  jest.clearAllMocks()
  jest.spyOn(SegmentStore, 'getGlobalWarnings').mockReturnValue({
    matecat: {ERROR: {total: 0}},
  })
  jest.spyOn(window, 'open').mockImplementation(() => {})
})

afterEach(() => {
  window.open.mockRestore()
})

test('shows the draft download and export items when the job is not completed', () => {
  render(<DownloadMenu password="secret" jid="10" isGDriveProject={false} />)
  expect(screen.getByText('Download draft')).toBeInTheDocument()
  expect(screen.getByText('Download original')).toBeInTheDocument()
  expect(screen.getByText('Export XLIFF')).toBeInTheDocument()
  expect(screen.getByText('Export job TMX')).toBeInTheDocument()
})

test('shows google drive labels when the project is a google drive project', () => {
  render(<DownloadMenu password="secret" jid="10" isGDriveProject={true} />)
  expect(screen.getByText('Open preview in Google Drive')).toBeInTheDocument()
  expect(
    screen.getByText('Open original in Google Drive'),
  ).toBeInTheDocument()
})

test('dispatches an analytics event and downloads the draft when the toggle button is clicked', () => {
  render(<DownloadMenu password="secret" jid="10" isGDriveProject={false} />)
  fireEvent.click(screen.getByTestId('toggle-button'))

  expect(CommonUtils.dispatchAnalyticsEvents).toHaveBeenCalledWith({
    event: 'download_draft',
  })
  expect(DownloadFileUtils.downloadFile).toHaveBeenCalledWith(
    '10',
    'secret',
    true,
    expect.any(Function),
  )
})

test('opens the original file in a new tab', () => {
  render(<DownloadMenu password="secret" jid="10" isGDriveProject={false} />)
  fireEvent.click(screen.getByText('Download original'))
  expect(window.open).toHaveBeenCalledWith('/api/v2/original/10/secret', '_blank')
})

test('opens the xliff export in a new tab', () => {
  render(<DownloadMenu password="secret" jid="10" isGDriveProject={false} />)
  fireEvent.click(screen.getByText('Export XLIFF'))
  expect(window.open).toHaveBeenCalledWith(
    '/api/v2/xliff/10/secret/10.zip',
    '_blank',
  )
})

test('opens the job tmx export in a new tab', () => {
  render(<DownloadMenu password="secret" jid="10" isGDriveProject={false} />)
  fireEvent.click(screen.getByText('Export job TMX'))
  expect(window.open).toHaveBeenCalledWith('/api/v2/tmx/10/secret', '_blank')
})

test('downloads the original file to google drive', () => {
  render(<DownloadMenu password="secret" jid="10" isGDriveProject={true} />)
  fireEvent.click(screen.getByText('Open original in Google Drive'))
  expect(DownloadFileUtils.downloadGDriveFile).toHaveBeenCalledWith(
    1,
    '10',
    'secret',
    true,
    expect.any(Function),
  )
})

test('switches to the completed-translation labels once the job is completed', () => {
  CommonUtils.isJobCompleted.mockReturnValue(true)
  render(<DownloadMenu password="secret" jid="10" isGDriveProject={false} />)

  act(() => {
    CatToolStore.emit(CattolConstants.SET_PROGRESS, {raw: {total: 10}})
  })

  expect(screen.getByText('Download translation')).toBeInTheDocument()

  fireEvent.click(screen.getByTestId('toggle-button'))
  expect(CommonUtils.dispatchAnalyticsEvents).not.toHaveBeenCalled()
  expect(DownloadFileUtils.downloadFile).toHaveBeenCalledWith(
    '10',
    'secret',
    true,
    expect.any(Function),
  )
})

test('shows the download warnings modal instead of downloading directly when there are errors', () => {
  SegmentStore.getGlobalWarnings.mockReturnValue({
    matecat: {ERROR: {total: 2}},
  })
  render(<DownloadMenu password="secret" jid="10" isGDriveProject={false} />)

  fireEvent.click(screen.getByTestId('toggle-button'))

  expect(ModalsActions.showDownloadWarningsModal).toHaveBeenCalledWith(
    expect.any(Function),
    expect.any(Function),
    expect.any(Function),
  )
  expect(DownloadFileUtils.downloadFile).not.toHaveBeenCalled()

  const goToFirstError = ModalsActions.showDownloadWarningsModal.mock.calls[0][2]
  goToFirstError()
  expect(CatToolActions.toggleQaIssues).toHaveBeenCalledTimes(1)
})
