jest.mock('../stores/AppDispatcher', () => ({
  dispatch: jest.fn(),
}))

jest.mock('../constants/CatToolConstants', () => ({
  ADD_NOTIFICATION: 'ADD_NOTIFICATION',
  REMOVE_NOTIFICATION: 'REMOVE_NOTIFICATION',
  REMOVE_ALL_NOTIFICATION: 'REMOVE_ALL_NOTIFICATION',
}))

import {
  addNotification,
  removeNotification,
  removeAllNotifications,
} from './notificationActions'
import AppDispatcher from '../stores/AppDispatcher'

describe('notificationActions', () => {
  beforeEach(() => {
    jest.clearAllMocks()
  })

  test('addNotification dispatches ADD_NOTIFICATION and returns dispatch result', () => {
    AppDispatcher.dispatch.mockReturnValueOnce('dispatched')
    const notification = {title: 'hello'}

    const result = addNotification(notification)

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'ADD_NOTIFICATION',
      notification,
    })
    expect(result).toBe('dispatched')
  })

  test('removeNotification dispatches REMOVE_NOTIFICATION', () => {
    const notification = {id: 1}

    removeNotification(notification)

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'REMOVE_NOTIFICATION',
      notification,
    })
  })

  test('removeAllNotifications dispatches REMOVE_ALL_NOTIFICATION', () => {
    removeAllNotifications()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'REMOVE_ALL_NOTIFICATION',
    })
  })
})
