import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'
import {FilesMenu} from './FilesMenu'
import {getJobFileInfo} from '../../../api/getJobFileInfo'
import {getFileSegments} from '../../../api/getFileSegments'
import CatToolActions from '../../../actions/CatToolActions'
import CommonUtils from '../../../utils/commonUtils'
import SegmentActions from '../../../actions/SegmentActions'
import SegmentStore from '../../../stores/SegmentStore'

jest.mock('../../../api/getJobFileInfo')
jest.mock('../../../api/getFileSegments')
jest.mock('../../../actions/CatToolActions')
jest.mock('../../../actions/SegmentActions')
jest.mock('../../../utils/commonUtils')

jest.mock('../../common/DropdownMenu/DropdownMenu', () => {
  const actual = jest.requireActual('../../common/DropdownMenu/DropdownMenu')
  return {
    ...actual,
    // Isolate FilesMenu's own click handlers from DropdownMenu's internal
    // Radix rendering, which is covered by its own directory.
    DropdownMenu: ({toggleButtonProps, items, onOpenChange, disabled}) => (
      <div data-testid="files-menu" data-disabled={String(!!disabled)}>
        <button data-testid="toggle-button">{toggleButtonProps.children}</button>
        <button data-testid="open-menu" onClick={() => onOpenChange(true)}>
          open
        </button>
        <button data-testid="close-menu" onClick={() => onOpenChange(false)}>
          close
        </button>
        {(items || []).map((item, index) =>
          item === actual.DROPDOWN_SEPARATOR ? null : (
            <div
              key={index}
              data-disabled={String(!!item.disabled)}
              onClick={item.disabled ? undefined : item.onClick}
            >
              {item.label}
            </div>
          ),
        )}
      </div>
    ),
  }
})

const files = [
  {id: 1, file_name: 'alpha.docx', first_segment: 100},
  {id: 2, file_name: 'beta.xliff'},
]

beforeEach(() => {
  global.config = {id_job: '7', password: 'pw'}
  jest.clearAllMocks()
  CommonUtils.parseFiles.mockImplementation((f) => f)
  jest.spyOn(SegmentStore, 'getCurrentSegment').mockReturnValue(undefined)
})

const loadFiles = async (response = {}) => {
  getJobFileInfo.mockResolvedValue({
    files,
    first_segment: 100,
    last_segment: 200,
    ...response,
  })
  render(<FilesMenu projectName="My project" />)
  await act(async () => {})
}

test('disables the dropdown until the file info request resolves', async () => {
  let resolveRequest
  getJobFileInfo.mockReturnValue(
    new Promise((resolve) => {
      resolveRequest = resolve
    }),
  )
  render(<FilesMenu projectName="My project" />)
  expect(screen.getByTestId('files-menu')).toHaveAttribute(
    'data-disabled',
    'true',
  )
  await act(async () => resolveRequest({files, first_segment: 100, last_segment: 200}))
  expect(screen.getByTestId('files-menu')).toHaveAttribute(
    'data-disabled',
    'false',
  )
})

test('stores the parsed file info and lists the go-to-current-segment and file items', async () => {
  await loadFiles()

  expect(CatToolActions.storeFilesInfo).toHaveBeenCalledWith(files, 100, 200)
  expect(screen.getByText('Go to current segment')).toBeInTheDocument()
  expect(screen.getByText('alpha.docx')).toBeInTheDocument()
  expect(screen.getByText('beta.xliff')).toBeInTheDocument()
})

test('go-to-current-segment is disabled until a segment is opened', async () => {
  await loadFiles()

  const goToCurrent = screen
    .getByText('Go to current segment')
    .closest('[data-disabled]')
  expect(goToCurrent).toHaveAttribute('data-disabled', 'true')

  fireEvent.click(screen.getByText('Go to current segment'))
  expect(SegmentActions.scrollToCurrentSegment).not.toHaveBeenCalled()
})

test('enables the go-to-current-segment item once the menu opens on an active segment', async () => {
  SegmentStore.getCurrentSegment.mockReturnValue({
    sid: 5,
    opened: true,
    id_file: 1,
  })
  await loadFiles()

  fireEvent.click(screen.getByTestId('open-menu'))
  expect(CatToolActions.closeSubHeader).toHaveBeenCalledTimes(1)

  const goToCurrent = screen
    .getByText('Go to current segment')
    .closest('[data-disabled]')
  expect(goToCurrent).toHaveAttribute('data-disabled', 'false')

  fireEvent.click(screen.getByText('Go to current segment'))
  expect(SegmentActions.scrollToCurrentSegment).toHaveBeenCalledTimes(1)

  fireEvent.click(screen.getByTestId('close-menu'))
  const afterClose = screen
    .getByText('Go to current segment')
    .closest('[data-disabled]')
  expect(afterClose).toHaveAttribute('data-disabled', 'true')
})

test('does not activate the current segment when opening the menu without an active segment', async () => {
  SegmentStore.getCurrentSegment.mockReturnValue({sid: 5, opened: false})
  await loadFiles()

  fireEvent.click(screen.getByTestId('open-menu'))

  const goToCurrent = screen
    .getByText('Go to current segment')
    .closest('[data-disabled]')
  expect(goToCurrent).toHaveAttribute('data-disabled', 'true')
})

test('jumps straight to a file first segment when it is already known', async () => {
  await loadFiles()

  fireEvent.click(screen.getByText('alpha.docx'))

  expect(SegmentActions.openSegment).toHaveBeenCalledWith(100)
  expect(getFileSegments).not.toHaveBeenCalled()
})

test('fetches the first segment of a file before opening it when unknown', async () => {
  getFileSegments.mockResolvedValue({first_segment: 250})
  await loadFiles()

  await act(async () => {
    fireEvent.click(screen.getByText('beta.xliff'))
  })

  expect(getFileSegments).toHaveBeenCalledWith({
    idJob: '7',
    password: 'pw',
    file_id: 2,
    file_type: undefined,
  })
  expect(SegmentActions.openSegment).toHaveBeenCalledWith(250)
})
