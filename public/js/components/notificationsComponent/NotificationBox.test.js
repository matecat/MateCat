import React from 'react'
import {render, screen, act} from '@testing-library/react'

import NotificationBox from './NotificationBox'
import CatToolStore from '../../stores/CatToolStore'
import CatToolConstants from '../../constants/CatToolConstants'

// NotificationBox subscribes directly to the real CatToolStore EventEmitter
// singleton (see public/js/stores/CatToolStore.js: `assign({}, EventEmitter.prototype, {...})`).
// Triggering `CatToolStore.emit(...)` invokes the listeners registered via
// `addListener` synchronously, so no jest.mock of the store is needed here -
// matching the pattern already used in SegmentsFilter.test.js.

const addNotification = (notification) => {
  act(() => {
    CatToolStore.emit(CatToolConstants.ADD_NOTIFICATION, notification)
  })
}

const removeNotification = (notification) => {
  act(() => {
    CatToolStore.emit(CatToolConstants.REMOVE_NOTIFICATION, notification)
  })
}

const removeAllNotifications = () => {
  act(() => {
    CatToolStore.emit(CatToolConstants.REMOVE_ALL_NOTIFICATION)
  })
}

describe('NotificationBox', () => {
  afterEach(() => {
    jest.useRealTimers()
  })

  test('renders an empty wrapper when there are no notifications', () => {
    const {container} = render(<NotificationBox />)
    expect(
      container.querySelector('.notifications-wrapper-inside'),
    ).toBeInTheDocument()
    expect(
      container.querySelector('.notification-item'),
    ).not.toBeInTheDocument()
  })

  test('registers store listeners on mount and unregisters them on unmount', () => {
    const addSpy = jest.spyOn(CatToolStore, 'addListener')
    const removeSpy = jest.spyOn(CatToolStore, 'removeListener')

    const {unmount} = render(<NotificationBox />)

    expect(addSpy).toHaveBeenCalledWith(
      CatToolConstants.ADD_NOTIFICATION,
      expect.any(Function),
    )
    expect(addSpy).toHaveBeenCalledWith(
      CatToolConstants.REMOVE_NOTIFICATION,
      expect.any(Function),
    )
    expect(addSpy).toHaveBeenCalledWith(
      CatToolConstants.REMOVE_ALL_NOTIFICATION,
      expect.any(Function),
    )

    unmount()

    expect(removeSpy).toHaveBeenCalledWith(
      CatToolConstants.ADD_NOTIFICATION,
      expect.any(Function),
    )
    expect(removeSpy).toHaveBeenCalledWith(
      CatToolConstants.REMOVE_NOTIFICATION,
      expect.any(Function),
    )
    expect(removeSpy).toHaveBeenCalledWith(
      CatToolConstants.REMOVE_ALL_NOTIFICATION,
      expect.any(Function),
    )

    addSpy.mockRestore()
    removeSpy.mockRestore()
  })

  test('adds a notification and renders it in its position group', () => {
    render(<NotificationBox />)

    addNotification({
      uid: 'n1',
      title: 'A title',
      text: 'Some text',
      position: 'tr',
      type: 'success',
      autoDismiss: false,
    })

    expect(screen.getByText('A title')).toBeInTheDocument()
    expect(screen.getByText('Some text')).toBeInTheDocument()
    expect(
      document.querySelector('.notifications-position-tr'),
    ).toBeInTheDocument()
    expect(document.querySelector('#not-1')).toBeInTheDocument()
  })

  test('defaults to bl position and info type when not provided', () => {
    render(<NotificationBox />)

    addNotification({
      uid: 'n2',
      title: 'Default title',
      text: 'Default text',
      autoDismiss: false,
    })

    expect(
      document.querySelector('.notifications-position-bl'),
    ).toBeInTheDocument()
    expect(document.querySelector('#not-3')).toBeInTheDocument()
    expect(
      document.querySelector('.notification-type-info'),
    ).toBeInTheDocument()
  })

  test('replaces an existing notification that shares the same uid', () => {
    render(<NotificationBox />)

    addNotification({
      uid: 'dup',
      title: 'First version',
      text: 'first',
      autoDismiss: false,
    })
    addNotification({
      uid: 'dup',
      title: 'Second version',
      text: 'second',
      autoDismiss: false,
    })

    expect(screen.queryByText('First version')).not.toBeInTheDocument()
    expect(screen.getByText('Second version')).toBeInTheDocument()
    expect(document.querySelectorAll('.notification-item')).toHaveLength(1)
  })

  test('assigns an incrementing internal uid when none is provided', () => {
    render(<NotificationBox />)

    addNotification({title: 'No uid one', text: 'one', autoDismiss: false})
    addNotification({title: 'No uid two', text: 'two', autoDismiss: false})

    expect(screen.getByText('No uid one')).toBeInTheDocument()
    expect(screen.getByText('No uid two')).toBeInTheDocument()
    expect(document.querySelectorAll('.notification-item')).toHaveLength(2)
  })

  test('REMOVE_NOTIFICATION marks a visible notification for removal and it disappears', () => {
    jest.useFakeTimers()
    render(<NotificationBox />)

    addNotification({
      uid: 'to-remove',
      title: 'Removable',
      text: 'removable text',
      autoDismiss: false,
    })

    // let the item finish its mount transition to become visible
    act(() => {
      jest.advanceTimersByTime(50)
    })

    removeNotification({uid: 'to-remove'})

    // hideNotification's internal removal timeout
    act(() => {
      jest.advanceTimersByTime(1000)
    })

    expect(screen.queryByText('Removable')).not.toBeInTheDocument()
  })

  test('REMOVE_NOTIFICATION on an unknown uid does not affect other notifications', () => {
    jest.useFakeTimers()
    render(<NotificationBox />)

    addNotification({
      uid: 'keep-me',
      title: 'Keep me',
      text: 'keep',
      autoDismiss: false,
    })

    act(() => {
      jest.advanceTimersByTime(50)
    })

    removeNotification({uid: 'does-not-exist'})

    act(() => {
      jest.advanceTimersByTime(1000)
    })

    expect(screen.getByText('Keep me')).toBeInTheDocument()
  })

  test('REMOVE_ALL_NOTIFICATION marks every visible notification for removal', () => {
    jest.useFakeTimers()
    render(<NotificationBox />)

    addNotification({
      uid: 'a',
      title: 'Alpha',
      text: 'alpha text',
      position: 'tl',
      autoDismiss: false,
    })
    addNotification({
      uid: 'b',
      title: 'Beta',
      text: 'beta text',
      position: 'br',
      autoDismiss: false,
    })

    act(() => {
      jest.advanceTimersByTime(50)
    })

    removeAllNotifications()

    act(() => {
      jest.advanceTimersByTime(1000)
    })

    expect(screen.queryByText('Alpha')).not.toBeInTheDocument()
    expect(screen.queryByText('Beta')).not.toBeInTheDocument()
  })
})
