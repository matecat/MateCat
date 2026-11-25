import React from 'react'
import {render} from '@testing-library/react'

const mockRender = jest.fn()
const mockCreateRoot = jest.fn(() => ({render: mockRender}))

jest.mock('react-dom/client', () => ({
  createRoot: (...args) => mockCreateRoot(...args),
}))
jest.mock('../components/common/ApplicationWrapper', () => ({
  ApplicationWrapper: ({children}) => (
    <div data-testid="application-wrapper">{children}</div>
  ),
}))
jest.mock('../components/modals/ModalWindow', () => ({
  ModalWindow: () => <div data-testid="modal-window" />,
}))
jest.mock('../components/notificationsComponent/NotificationBox', () => () => (
  <div data-testid="notification-box" />
))

import {mountPage} from './mountPage'

// Captures and returns only the DOMContentLoaded handler just registered by
// the mountPage() call under test, so tests don't trigger stale listeners
// left over (and never removed) by previous tests sharing the same document.
const captureLatestDOMContentLoadedHandler = (fn) => {
  const spy = jest.spyOn(document, 'addEventListener')
  fn()
  const call = spy.mock.calls
    .reverse()
    .find(([eventName]) => eventName === 'DOMContentLoaded')
  spy.mockRestore()
  return call[1]
}

afterEach(() => {
  jest.clearAllMocks()
  document.body.innerHTML = ''
})

describe('mountPage', () => {
  test('does not mount anything before DOMContentLoaded fires', () => {
    const rootElement = document.createElement('div')
    captureLatestDOMContentLoadedHandler(() =>
      mountPage({Component: () => null, rootElement}),
    )
    expect(mockCreateRoot).not.toHaveBeenCalled()
  })

  test('mounts Component and ModalWindow into rootElement on DOMContentLoaded', () => {
    const rootElement = document.createElement('div')
    const TestComponent = () => <div data-testid="test-component" />

    const handler = captureLatestDOMContentLoadedHandler(() =>
      mountPage({Component: TestComponent, rootElement}),
    )
    handler()

    expect(mockCreateRoot).toHaveBeenCalledWith(rootElement)
    expect(mockRender).toHaveBeenCalledTimes(1)

    const renderedElement = mockRender.mock.calls[0][0]
    const {container} = render(renderedElement)
    expect(
      container.querySelector('[data-testid="application-wrapper"]'),
    ).toBeInTheDocument()
    expect(
      container.querySelector('[data-testid="test-component"]'),
    ).toBeInTheDocument()
    expect(
      container.querySelector('[data-testid="modal-window"]'),
    ).toBeInTheDocument()
  })

  test('mounts NotificationBox when a .notifications-wrapper element exists', () => {
    const rootElement = document.createElement('div')
    const notifWrapper = document.createElement('div')
    notifWrapper.className = 'notifications-wrapper'
    document.body.appendChild(notifWrapper)

    const handler = captureLatestDOMContentLoadedHandler(() =>
      mountPage({Component: () => null, rootElement}),
    )
    handler()

    expect(mockCreateRoot).toHaveBeenCalledTimes(2)
    expect(mockCreateRoot).toHaveBeenNthCalledWith(2, notifWrapper)
    expect(mockRender).toHaveBeenCalledTimes(2)
  })

  test('does not mount NotificationBox when no .notifications-wrapper element exists', () => {
    const rootElement = document.createElement('div')

    const handler = captureLatestDOMContentLoadedHandler(() =>
      mountPage({Component: () => null, rootElement}),
    )
    handler()

    expect(mockCreateRoot).toHaveBeenCalledTimes(1)
    expect(mockRender).toHaveBeenCalledTimes(1)
  })
})
