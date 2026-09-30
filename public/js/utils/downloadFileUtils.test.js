jest.mock('js-cookie', () => ({
  get: jest.fn(),
  remove: jest.fn(),
}))
jest.mock('../actions/CatToolActions', () => ({
  addNotification: jest.fn(),
}))
jest.mock('./commonUtils', () => ({
  isSafari: false,
}))
jest.mock('../actions/ModalsActions', () => ({
  showModalComponent: jest.fn(),
  onCloseModal: jest.fn(),
}))
jest.mock(
  '../components/modals/ConfirmMessageModal',
  () => 'ConfirmMessageModal',
)
jest.mock('../api/downloadFileGDrive', () => ({
  downloadFileGDrive: jest.fn(),
}))
jest.mock('../api/downloadFile', () => ({
  downloadFile: jest.fn(),
}))

import Cookies from 'js-cookie'
import CatToolActions from '../actions/CatToolActions'
import CommonUtils from './commonUtils'
import ModalsActions from '../actions/ModalsActions'
import {downloadFileGDrive} from '../api/downloadFileGDrive'
import {downloadFile} from '../api/downloadFile'
import DownloadFileUtils from './downloadFileUtils'

const flush = async () => {
  await Promise.resolve()
  await Promise.resolve()
}

beforeEach(() => {
  CommonUtils.isSafari = false
  window.googleDriveWindows = undefined
  global.config = {...global.config, support_mail: 'support@matecat.com'}
})

test('downloadFile invokes the callback after a successful download', async () => {
  downloadFile.mockResolvedValueOnce()
  const callback = jest.fn()

  DownloadFileUtils.downloadFile(1, 'pass', true, callback)
  await flush()

  expect(downloadFile).toHaveBeenCalledWith({
    idJob: 1,
    password: 'pass',
    checkErrors: true,
  })
  expect(callback).toHaveBeenCalled()
})

test('downloadFile shows an error notification and still invokes the callback on failure', async () => {
  downloadFile.mockRejectedValueOnce(new Error('boom'))
  const callback = jest.fn()

  DownloadFileUtils.downloadFile(1, 'pass', true, callback)
  await flush()

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({title: 'Error', type: 'error'}),
  )
  expect(callback).toHaveBeenCalled()
})

test('showDownloadErrorMessage notifies with the configured support mail', () => {
  DownloadFileUtils.showDownloadErrorMessage()

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({
      title: 'Error',
      type: 'error',
      text: expect.stringContaining('support@matecat.com'),
    }),
  )
})

test('downloadGDriveFile shows a "Download fail" modal when no urls are returned', async () => {
  downloadFileGDrive.mockResolvedValueOnce({urls: []})
  const callback = jest.fn()

  DownloadFileUtils.downloadGDriveFile(undefined, 5, 'pass', true, callback)
  await flush()

  expect(downloadFileGDrive).toHaveBeenCalled()
  expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
    'ConfirmMessageModal',
    expect.objectContaining({successText: 'Ok'}),
    'Download fail',
  )
  const [, props] = ModalsActions.showModalComponent.mock.calls[0]
  props.successCallback()
  expect(ModalsActions.onCloseModal).toHaveBeenCalled()
  expect(callback).toHaveBeenCalled()
})

test('downloadGDriveFile opens a new window per url when none are already open', async () => {
  const openedWindow = {closed: false, location: null, focus: jest.fn()}
  window.open = jest.fn(() => openedWindow)
  downloadFileGDrive.mockResolvedValueOnce({
    urls: [{localId: 1, alternateLink: 'https://drive/file1'}],
  })
  const callback = jest.fn()

  DownloadFileUtils.downloadGDriveFile(1, 5, 'pass', true, callback)
  await flush()

  expect(window.open).toHaveBeenCalledWith('https://drive/file1')
  expect(window.googleDriveWindows.window1).toBe(openedWindow)
  expect(callback).toHaveBeenCalled()
})

test('downloadGDriveFile reuses an already open window for the same url', async () => {
  const existingWindow = {closed: false, location: {}, focus: jest.fn()}
  window.googleDriveWindows = {window2: existingWindow}
  downloadFileGDrive.mockResolvedValueOnce({
    urls: [{localId: 2, alternateLink: 'https://drive/file2'}],
  })

  DownloadFileUtils.downloadGDriveFile(0, 5, 'pass', true, undefined)
  await flush()

  expect(existingWindow.location.href).toBe('https://drive/file2')
  expect(existingWindow.focus).toHaveBeenCalled()
})

test('downloadGDriveFile updates the pre-opened window reference on Safari', async () => {
  CommonUtils.isSafari = true
  const safariWindowReference = {}
  window.open = jest.fn(() => safariWindowReference)
  downloadFileGDrive.mockResolvedValueOnce({
    urls: [{localId: 3, alternateLink: 'https://drive/file3'}],
  })

  DownloadFileUtils.downloadGDriveFile(1, 5, 'pass', true, undefined)
  await flush()

  expect(safariWindowReference.location).toBe('https://drive/file3')
})

test('downloadGDriveFile shows the error modal and clears the cookie when the request fails', async () => {
  Cookies.get.mockReturnValueOnce({message: 'Something failed'})
  downloadFileGDrive.mockRejectedValueOnce(new Error('network error'))
  const callback = jest.fn()

  DownloadFileUtils.downloadGDriveFile(1, 5, 'pass', true, callback)
  await flush()

  expect(callback).toHaveBeenCalled()
  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({title: 'Error', type: 'error'}),
  )
  expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
    'ConfirmMessageModal',
    expect.objectContaining({text: 'Something failed'}),
    'Download fail',
  )
  const [, props] = ModalsActions.showModalComponent.mock.calls[0]
  props.successCallback()
  expect(ModalsActions.onCloseModal).toHaveBeenCalled()
  expect(Cookies.remove).toHaveBeenCalled()
})

test('downloadGDriveFile fails silently when there is no error cookie', async () => {
  Cookies.get.mockReturnValueOnce(undefined)
  downloadFileGDrive.mockRejectedValueOnce(new Error('network error'))

  DownloadFileUtils.downloadGDriveFile(1, 5, 'pass', true, undefined)
  await flush()

  expect(ModalsActions.showModalComponent).not.toHaveBeenCalled()
  expect(Cookies.remove).not.toHaveBeenCalled()
})
