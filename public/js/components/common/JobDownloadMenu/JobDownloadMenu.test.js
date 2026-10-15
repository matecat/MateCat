import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'
import {JobDownloadMenu} from './JobDownloadMenu'
import CommonUtils from '../../../utils/commonUtils'
import DownloadFileUtils from '../../../utils/downloadFileUtils'

jest.mock('../../../utils/commonUtils')
jest.mock('../../../utils/downloadFileUtils')

jest.mock('../DropdownMenu/DropdownMenu', () => {
  const actual = jest.requireActual('../DropdownMenu/DropdownMenu')
  return {
    ...actual,
    // Isolate JobDownloadMenu's own click handlers from DropdownMenu's internal
    // Radix rendering, which is covered by its own directory.
    DropdownMenu: ({toggleButtonProps, items, className}) => (
      <div data-testid="download-menu" className={className}>
        <button
          data-testid="toggle-button"
          data-mode={toggleButtonProps.mode}
          data-type={toggleButtonProps.type}
          disabled={toggleButtonProps.disabled}
          onClick={toggleButtonProps.onClick}
        >
          {' '}
        </button>
        {items.map((item, index) => (
          <div
            key={index}
            data-testid={`menu-item-${index}`}
            data-disabled={item.disabled ? 'true' : undefined}
            onClick={item.onClick}
          >
            {item.label}
          </div>
        ))}
      </div>
    ),
  }
})

const renderMenu = (props = {}) =>
  render(
    <JobDownloadMenu
      jid="42"
      password="pass"
      isGDriveProject={false}
      isJobCompleted={false}
      {...props}
    />,
  )

beforeEach(() => {
  // The component must never read the globals: make them differ from props.
  global.config = {id_job: '999', password: 'wrong', isGDriveProject: true}
  jest.clearAllMocks()
  jest.spyOn(window, 'open').mockImplementation(() => {})
})

afterEach(() => {
  window.open.mockRestore()
})

describe('JobDownloadMenu', () => {
  test('shows the draft label and ghost trigger when the job is not completed', () => {
    renderMenu()
    expect(screen.getByText('Download draft')).toBeInTheDocument()
    expect(screen.queryByText('Download translation')).not.toBeInTheDocument()
    expect(screen.getByTestId('download-menu')).not.toHaveClass('job-completed')
    expect(screen.getByTestId('toggle-button')).toHaveAttribute(
      'data-mode',
      'ghost',
    )
  })

  test('shows the translation label and primary trigger when the job is completed', () => {
    renderMenu({isJobCompleted: true})
    expect(screen.getByText('Download translation')).toBeInTheDocument()
    expect(screen.queryByText('Download draft')).not.toBeInTheDocument()
    expect(screen.getByTestId('download-menu')).toHaveClass('job-completed')
  })

  test('shows the Google Drive labels for a Drive project', () => {
    const {rerender} = renderMenu({isGDriveProject: true})
    expect(screen.getByText('Open preview in Google Drive')).toBeInTheDocument()
    expect(
      screen.getByText('Open original in Google Drive'),
    ).toBeInTheDocument()
    expect(screen.queryByText('Download original')).not.toBeInTheDocument()

    rerender(
      <JobDownloadMenu
        jid="42"
        password="pass"
        isGDriveProject={true}
        isJobCompleted={true}
      />,
    )
    expect(screen.getByText('Open in Google Drive')).toBeInTheDocument()
  })

  test('builds the original, XLIFF and TMX urls from the props', () => {
    renderMenu()
    fireEvent.click(screen.getByText('Download original'))
    expect(window.open).toHaveBeenLastCalledWith(
      '/api/v2/original/42/pass',
      '_blank',
    )
    fireEvent.click(screen.getByText('Export XLIFF'))
    expect(window.open).toHaveBeenLastCalledWith(
      '/api/v2/xliff/42/pass/42.zip',
      '_blank',
    )
    fireEvent.click(screen.getByText('Export job TMX'))
    expect(window.open).toHaveBeenLastCalledWith(
      '/api/v2/tmx/42/pass',
      '_blank',
    )
  })

  test('downloads directly with the props when there is no onBeforeDownload', () => {
    renderMenu()
    fireEvent.click(screen.getByText('Download draft'))
    expect(DownloadFileUtils.downloadFile).toHaveBeenCalledWith(
      '42',
      'pass',
      true,
      expect.any(Function),
    )
  })

  test('downloads the original Drive file with the props', () => {
    renderMenu({isGDriveProject: true})
    fireEvent.click(screen.getByText('Open original in Google Drive'))
    expect(DownloadFileUtils.downloadGDriveFile).toHaveBeenCalledWith(
      1,
      '42',
      'pass',
      true,
      expect.any(Function),
    )
  })

  test('waits for onBeforeDownload to proceed before downloading', () => {
    const onBeforeDownload = jest.fn()
    renderMenu({onBeforeDownload})
    fireEvent.click(screen.getByTestId('toggle-button'))

    expect(onBeforeDownload).toHaveBeenCalledWith(
      expect.any(Function),
      expect.any(Function),
    )
    expect(DownloadFileUtils.downloadFile).not.toHaveBeenCalled()

    const [proceed] = onBeforeDownload.mock.calls[0]
    act(() => proceed())
    expect(DownloadFileUtils.downloadFile).toHaveBeenCalledWith(
      '42',
      'pass',
      true,
      expect.any(Function),
    )
  })

  test('proceeds without error checks when onBeforeDownload asks for it', () => {
    const onBeforeDownload = jest.fn()
    renderMenu({onBeforeDownload, isGDriveProject: true})
    fireEvent.click(screen.getByTestId('toggle-button'))

    const [, proceedWithoutErrors] = onBeforeDownload.mock.calls[0]
    act(() => proceedWithoutErrors())
    expect(DownloadFileUtils.downloadGDriveFile).toHaveBeenCalledWith(
      undefined,
      '42',
      'pass',
      false,
      expect.any(Function),
    )
  })

  test('ignores further downloads while one is running', () => {
    renderMenu()
    fireEvent.click(screen.getByText('Download draft'))
    fireEvent.click(screen.getByText('Download draft'))
    expect(DownloadFileUtils.downloadFile).toHaveBeenCalledTimes(1)

    const callback = DownloadFileUtils.downloadFile.mock.calls[0][3]
    act(() => callback())
    fireEvent.click(screen.getByText('Download draft'))
    expect(DownloadFileUtils.downloadFile).toHaveBeenCalledTimes(2)
  })

  test('does nothing when disabled', () => {
    const onBeforeDownload = jest.fn()
    renderMenu({disabled: true, onBeforeDownload})

    expect(screen.getByTestId('toggle-button')).toBeDisabled()
    expect(screen.getByTestId('menu-item-2')).toHaveAttribute(
      'data-disabled',
      'true',
    )

    fireEvent.click(screen.getByText('Download draft'))
    fireEvent.click(screen.getByText('Download original'))
    fireEvent.click(screen.getByText('Export XLIFF'))
    fireEvent.click(screen.getByText('Export job TMX'))

    expect(onBeforeDownload).not.toHaveBeenCalled()
    expect(DownloadFileUtils.downloadFile).not.toHaveBeenCalled()
    expect(CommonUtils.dispatchAnalyticsEvents).not.toHaveBeenCalled()
    expect(window.open).not.toHaveBeenCalled()
  })

  test('dispatches the download_draft event only when the job is not completed', () => {
    const {rerender} = renderMenu()
    fireEvent.click(screen.getByText('Download draft'))
    expect(CommonUtils.dispatchAnalyticsEvents).toHaveBeenCalledWith({
      event: 'download_draft',
    })

    jest.clearAllMocks()
    rerender(
      <JobDownloadMenu
        jid="7"
        password="other"
        isGDriveProject={false}
        isJobCompleted={true}
      />,
    )
    fireEvent.click(screen.getByText('Download translation'))
    expect(CommonUtils.dispatchAnalyticsEvents).not.toHaveBeenCalled()
  })

  test('forwards checkErrors to the download utils', () => {
    renderMenu({checkErrors: false})
    fireEvent.click(screen.getByText('Download draft'))
    expect(DownloadFileUtils.downloadFile).toHaveBeenCalledWith(
      '42',
      'pass',
      false,
      expect.any(Function),
    )
  })

  test('lets triggerProps override the trigger button', () => {
    renderMenu({triggerProps: {mode: 'basic'}})
    expect(screen.getByTestId('toggle-button')).toHaveAttribute(
      'data-mode',
      'basic',
    )
  })

  test('keeps its own onClick when triggerProps passes one', () => {
    const onClick = jest.fn()
    renderMenu({triggerProps: {onClick}})
    fireEvent.click(screen.getByTestId('toggle-button'))
    expect(onClick).not.toHaveBeenCalled()
    expect(DownloadFileUtils.downloadFile).toHaveBeenCalledWith(
      '42',
      'pass',
      true,
      expect.any(Function),
    )
  })

  test('stays disabled when triggerProps passes disabled false', () => {
    renderMenu({disabled: true, triggerProps: {disabled: false}})
    expect(screen.getByTestId('toggle-button')).toBeDisabled()
  })
})
