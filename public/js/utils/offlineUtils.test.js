jest.mock('../actions/segmentClassActions', () => ({
  addClassToSegment: jest.fn(),
  removeClassToSegment: jest.fn(),
}))
jest.mock('../api/checkConnectionPing', () => ({
  checkConnectionPing: jest.fn(),
}))
jest.mock('../actions/notificationActions', () => ({
  addNotification: jest.fn(),
  removeAllNotifications: jest.fn(),
}))
jest.mock('../stores/SegmentStore', () => ({
  getSegmentById: jest.fn(),
}))
jest.mock('../setTranslationUtil', () => ({
  execSetTranslationTail: jest.fn(),
}))

import {
  addClassToSegment,
  removeClassToSegment,
} from '../actions/segmentClassActions'
import {checkConnectionPing} from '../api/checkConnectionPing'
import {
  addNotification,
  removeAllNotifications,
} from '../actions/notificationActions'
import SegmentStore from '../stores/SegmentStore'
import {execSetTranslationTail} from '../setTranslationUtil'
import OfflineUtils from './offlineUtils'

const flush = async () => {
  await Promise.resolve()
  await Promise.resolve()
  await Promise.resolve()
}

beforeEach(() => {
  OfflineUtils.offline = false
  OfflineUtils.offlineCacheSize = 20
  OfflineUtils.offlineCacheRemaining = 20
  OfflineUtils.checkingConnection = false
  OfflineUtils.currentConnectionCountdown = null
  checkConnectionPing.mockResolvedValue()
})

afterEach(() => {
  jest.clearAllTimers()
  jest.useRealTimers()
})

// ensure the module's dynamic `import('../setTranslationUtil')` has resolved
beforeAll(async () => {
  await flush()
})

test('startOfflineMode shows a warning and resets the cache when the connection check succeeds', async () => {
  checkConnectionPing.mockResolvedValueOnce()

  OfflineUtils.startOfflineMode()
  await flush()

  expect(OfflineUtils.offline).toBe(false)
  expect(OfflineUtils.offlineCacheRemaining).toBe(OfflineUtils.offlineCacheSize)
  expect(addNotification).toHaveBeenCalledWith(
    expect.objectContaining({uid: 'offline-counter', type: 'warning'}),
  )
})

test('startOfflineMode is a no-op when already offline', () => {
  OfflineUtils.offline = true

  OfflineUtils.startOfflineMode()

  expect(checkConnectionPing).not.toHaveBeenCalled()
})

test('startOfflineMode switches to offline mode and polls the connection when the check fails', async () => {
  jest.useFakeTimers()
  checkConnectionPing.mockRejectedValueOnce(new Error('down'))

  OfflineUtils.startOfflineMode()
  await flush()

  expect(OfflineUtils.offline).toBe(true)
  expect(OfflineUtils.checkingConnection).toBeTruthy()

  checkConnectionPing.mockResolvedValueOnce()
  jest.advanceTimersByTime(5000)
  await flush()

  expect(checkConnectionPing).toHaveBeenCalledTimes(2)
  expect(OfflineUtils.offline).toBe(false)
  expect(execSetTranslationTail).toHaveBeenCalled()
})

test('endOfflineMode does nothing when not currently offline', () => {
  OfflineUtils.offline = false

  OfflineUtils.endOfflineMode()

  expect(addNotification).not.toHaveBeenCalled()
})

test('endOfflineMode notifies, clears intervals, and resets state when offline', () => {
  OfflineUtils.offline = true
  OfflineUtils.checkingConnection = 123
  OfflineUtils.currentConnectionCountdown = 456

  OfflineUtils.endOfflineMode()

  expect(OfflineUtils.offline).toBe(false)
  expect(addNotification).toHaveBeenCalledWith(
    expect.objectContaining({uid: 'offline-back', type: 'success'}),
  )
  const [[notification]] = addNotification.mock.calls
  notification.openCallback()
  expect(removeAllNotifications).toHaveBeenCalled()
  expect(OfflineUtils.currentConnectionCountdown).toBeNull()
  expect(OfflineUtils.checkingConnection).toBe(false)
})

test('failedConnection delegates to startOfflineMode', () => {
  const startSpy = jest.spyOn(OfflineUtils, 'startOfflineMode')

  OfflineUtils.failedConnection()

  expect(startSpy).toHaveBeenCalled()
  startSpy.mockRestore()
})

test('checkConnection silently swallows a failed ping', async () => {
  checkConnectionPing.mockRejectedValueOnce(new Error('still down'))

  expect(() => OfflineUtils.checkConnection()).not.toThrow()
  await flush()

  expect(execSetTranslationTail).not.toHaveBeenCalled()
})

test('decrementOfflineCacheRemaining shows the remaining count while offline', () => {
  OfflineUtils.offline = true
  OfflineUtils.offlineCacheRemaining = 5

  OfflineUtils.decrementOfflineCacheRemaining()

  expect(OfflineUtils.offlineCacheRemaining).toBe(4)
  expect(addNotification).toHaveBeenCalledWith(
    expect.objectContaining({uid: 'offline-counter', type: 'warning'}),
  )
})

test('decrementOfflineCacheRemaining does nothing when online', () => {
  OfflineUtils.offline = false
  OfflineUtils.offlineCacheRemaining = 5

  OfflineUtils.decrementOfflineCacheRemaining()

  expect(OfflineUtils.offlineCacheRemaining).toBe(5)
  expect(addNotification).not.toHaveBeenCalled()
})

test('incrementOfflineCacheRemaining increases the counter by one', () => {
  OfflineUtils.offlineCacheRemaining = 5

  OfflineUtils.incrementOfflineCacheRemaining()

  expect(OfflineUtils.offlineCacheRemaining).toBe(6)
})

test('changeStatusOffline swaps status classes when the segment exists', () => {
  SegmentStore.getSegmentById.mockReturnValue({sid: 10})

  OfflineUtils.changeStatusOffline(10)

  expect(removeClassToSegment).toHaveBeenCalledWith(10, 'status-draft')
  expect(removeClassToSegment).toHaveBeenCalledWith(10, 'status-approved')
  expect(removeClassToSegment).toHaveBeenCalledWith(10, 'status-new')
  expect(removeClassToSegment).toHaveBeenCalledWith(10, 'status-rejected')
  expect(removeClassToSegment).toHaveBeenCalledWith(10, 'status-fixed')
  expect(removeClassToSegment).toHaveBeenCalledWith(10, 'status-rebutted')
  expect(addClassToSegment).toHaveBeenCalledWith(10, 'status-translated')
})

test('changeStatusOffline does nothing when the segment is not found', () => {
  SegmentStore.getSegmentById.mockReturnValue(undefined)

  OfflineUtils.changeStatusOffline(999)

  expect(removeClassToSegment).not.toHaveBeenCalled()
  expect(addClassToSegment).not.toHaveBeenCalled()
})
