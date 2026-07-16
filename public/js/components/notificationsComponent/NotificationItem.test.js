import React from 'react'
import {render, screen, act} from '@testing-library/react'
import userEvent from '@testing-library/user-event'

import NotificationItem from './NotificationItem'

const renderItem = (props = {}) => {
  const onRemove = jest.fn()
  const utils = render(
    <NotificationItem
      uid={1}
      title="A title"
      text="Some text"
      onRemove={onRemove}
      {...props}
    />,
  )
  return {...utils, onRemove}
}

describe('NotificationItem', () => {
  afterEach(() => {
    jest.useRealTimers()
  })

  test('renders title and text', () => {
    renderItem({title: 'Hello', text: 'World'})
    expect(screen.getByText('Hello')).toBeInTheDocument()
    expect(screen.getByText('World')).toBeInTheDocument()
  })

  test('renders the success icon when type is success', () => {
    const {container} = renderItem({type: 'success'})
    expect(
      container.querySelector('.notification-type-success'),
    ).toBeInTheDocument()
  })

  test('renders the info icon by default', () => {
    const {container} = renderItem({})
    expect(
      container.querySelector('.notification-type-info'),
    ).toBeInTheDocument()
  })

  test('renders dismiss button when dismissable is true', () => {
    renderItem({dismissable: true})
    expect(screen.getByRole('button')).toBeInTheDocument()
  })

  test('does not render dismiss button when dismissable is false', () => {
    renderItem({dismissable: false})
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  test('clicking dismiss button calls onRemove after the removal delay', async () => {
    jest.useFakeTimers()
    const user = userEvent.setup({advanceTimers: jest.advanceTimersByTime})
    const {onRemove} = renderItem({dismissable: true, autoDismiss: false})

    await user.click(screen.getByRole('button'))

    act(() => {
      jest.advanceTimersByTime(1000)
    })

    expect(onRemove).toHaveBeenCalledWith(1)
  })

  test('calls closeCallback when dismissed', async () => {
    jest.useFakeTimers()
    const user = userEvent.setup({advanceTimers: jest.advanceTimersByTime})
    const closeCallback = jest.fn()
    renderItem({closeCallback, autoDismiss: false})

    await user.click(screen.getByRole('button'))

    expect(closeCallback).toHaveBeenCalledTimes(1)
  })

  test('calls openCallback on mount', () => {
    const openCallback = jest.fn()
    renderItem({openCallback})
    expect(openCallback).toHaveBeenCalledTimes(1)
  })

  test('auto dismisses after the given timer and calls onRemove', () => {
    jest.useFakeTimers()
    const {onRemove} = renderItem({autoDismiss: true, timer: 700})

    // becomes visible
    act(() => {
      jest.advanceTimersByTime(50)
    })

    // auto-dismiss timer fires
    act(() => {
      jest.advanceTimersByTime(700)
    })

    // removal setTimeout fires
    act(() => {
      jest.advanceTimersByTime(1000)
    })

    expect(onRemove).toHaveBeenCalledWith(1)
  })

  test('does not auto dismiss when autoDismiss is false', () => {
    jest.useFakeTimers()
    const {onRemove} = renderItem({autoDismiss: false, timer: 700})

    act(() => {
      jest.advanceTimersByTime(50)
    })
    act(() => {
      jest.advanceTimersByTime(700)
    })
    act(() => {
      jest.advanceTimersByTime(1000)
    })

    expect(onRemove).not.toHaveBeenCalled()
  })

  test('hides notification when remove prop becomes true after becoming visible', () => {
    jest.useFakeTimers()
    const onRemove = jest.fn()
    const {rerender} = render(
      <NotificationItem
        uid={2}
        title="A title"
        text="Some text"
        autoDismiss={false}
        onRemove={onRemove}
        remove={false}
      />,
    )

    // become visible first
    act(() => {
      jest.advanceTimersByTime(50)
    })

    rerender(
      <NotificationItem
        uid={2}
        title="A title"
        text="Some text"
        autoDismiss={false}
        onRemove={onRemove}
        remove={true}
      />,
    )

    act(() => {
      jest.advanceTimersByTime(1000)
    })

    expect(onRemove).toHaveBeenCalledWith(2)
  })

  test('does not trigger hide when remove is true but not yet visible', () => {
    jest.useFakeTimers()
    const onRemove = jest.fn()
    render(
      <NotificationItem
        uid={3}
        title="A title"
        text="Some text"
        autoDismiss={false}
        onRemove={onRemove}
        remove={true}
      />,
    )

    act(() => {
      jest.advanceTimersByTime(1000)
    })

    expect(onRemove).not.toHaveBeenCalled()
  })

  test.each([
    ['bl', 'bottom'],
    ['bc', 'bottom'],
    ['br', 'bottom'],
    ['tl', 'top'],
    ['tc', 'top'],
    ['tr', 'top'],
  ])('applies the %s position style using the %s css property', (position) => {
    const {container} = renderItem({position, autoDismiss: false})
    const el = container.querySelector('.notification-item')
    expect(el).toBeInTheDocument()
  })
})
