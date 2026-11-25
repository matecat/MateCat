jest.mock('js-cookie', () => ({
  get: jest.fn(),
  remove: jest.fn(),
}))
jest.mock('../actions/CatToolActions', () => ({
  addNotification: jest.fn(),
  removeNotification: jest.fn(),
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
jest.mock('../api/downloadFromUrl', () => ({
  downloadFromUrl: jest.fn(),
}))

import Cookies from 'js-cookie'
import CatToolActions from '../actions/CatToolActions'
import CommonUtils from './commonUtils'
import ModalsActions from '../actions/ModalsActions'
import {downloadFileGDrive} from '../api/downloadFileGDrive'
import {downloadFile} from '../api/downloadFile'
import {downloadFromUrl} from '../api/downloadFromUrl'
import DownloadFileUtils from './downloadFileUtils'

const flush = async () => {
  await Promise.resolve()
  await Promise.resolve()
}

beforeEach(() => {
  jest.clearAllMocks()
  CommonUtils.isSafari = false
  window.googleDriveWindows = undefined
  global.config = {...global.config, support_mail: 'support@matecat.com'}
})

test('downloadFile invokes the callback after a successful download', async () => {
  downloadFile.mockResolvedValueOnce()
  const callback = jest.fn()

  DownloadFileUtils.downloadFile(1, 'pass', true, callback)
  await flush()

  expect(downloadFile).toHaveBeenCalledWith(
    expect.objectContaining({
      idJob: 1,
      password: 'pass',
      checkErrors: true,
    }),
  )
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

test('downloadFile keeps a progress notification open until the download settles', async () => {
  let finish
  downloadFile.mockReturnValueOnce(new Promise((resolve) => (finish = resolve)))
  const callback = jest.fn()

  DownloadFileUtils.downloadFile(7, 'pass', true, callback)

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({
      uid: 'download-job-7',
      position: 'tr',
      autoDismiss: false,
      dismissable: false,
    }),
  )
  expect(CatToolActions.removeNotification).not.toHaveBeenCalled()

  finish()
  await flush()
  await flush()

  expect(CatToolActions.removeNotification).toHaveBeenCalledWith({
    uid: 'download-job-7',
  })
  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({type: 'success'}),
  )
  expect(callback).toHaveBeenCalled()
})

test('cancelling a download aborts the request without reporting an error', async () => {
  downloadFile.mockImplementationOnce(
    ({signal}) =>
      new Promise((resolve, reject) =>
        signal.addEventListener('abort', () =>
          reject(new DOMException('Aborted', 'AbortError')),
        ),
      ),
  )
  const callback = jest.fn()

  DownloadFileUtils.downloadFile(7, 'pass', true, callback)
  const {text} = CatToolActions.addNotification.mock.calls[0][0]
  text.props.onCancel()
  await flush()
  await flush()

  expect(CatToolActions.addNotification).not.toHaveBeenCalledWith(
    expect.objectContaining({type: 'error'}),
  )
  expect(CatToolActions.removeNotification).toHaveBeenCalledWith({
    uid: 'download-job-7',
  })
  expect(callback).toHaveBeenCalled()
})

test('leaving the page asks for confirmation only while a download is pending', async () => {
  let finish
  downloadFile.mockReturnValueOnce(new Promise((resolve) => (finish = resolve)))
  const leave = () => {
    const event = new Event('beforeunload', {cancelable: true})
    window.dispatchEvent(event)
    return event.defaultPrevented
  }

  DownloadFileUtils.downloadFile(7, 'pass', true, jest.fn())
  expect(leave()).toBe(true)

  finish()
  await flush()
  await flush()
  expect(leave()).toBe(false)
})

test('downloadUrl fetches the file behind the same progress notification', async () => {
  downloadFromUrl.mockResolvedValueOnce()

  DownloadFileUtils.downloadUrl('/api/v2/tmx/7/pass')
  await flush()
  await flush()

  expect(downloadFromUrl).toHaveBeenCalledWith(
    expect.objectContaining({url: '/api/v2/tmx/7/pass'}),
  )
  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({
      uid: 'download-/api/v2/tmx/7/pass',
      title: 'Download in progress',
    }),
  )
  expect(CatToolActions.removeNotification).toHaveBeenCalledWith({
    uid: 'download-/api/v2/tmx/7/pass',
  })
})

test('a second click on a running download does not start it again', async () => {
  let finish
  downloadFromUrl.mockReturnValue(new Promise((resolve) => (finish = resolve)))

  DownloadFileUtils.downloadUrl('/api/v2/original/7/pass')
  DownloadFileUtils.downloadUrl('/api/v2/original/7/pass')
  expect(downloadFromUrl).toHaveBeenCalledTimes(1)

  finish()
  await flush()
  await flush()
  DownloadFileUtils.downloadUrl('/api/v2/original/7/pass')
  expect(downloadFromUrl).toHaveBeenCalledTimes(2)
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
