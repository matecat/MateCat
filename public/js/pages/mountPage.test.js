import React from 'react'
import {render, screen} from '@testing-library/react'

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
import {rootErrorOptions} from '../utils/rootErrorOptions'

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

    expect(mockCreateRoot).toHaveBeenCalledWith(rootElement, rootErrorOptions)
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

  test('a page that crashes while rendering is replaced by the error fallback', () => {
    jest.spyOn(console, 'error').mockImplementation(() => {})
    const rootElement = document.createElement('div')
    const CrashingPage = () => {
      throw new Error('page crashed')
    }

    const handler = captureLatestDOMContentLoadedHandler(() =>
      mountPage({Component: CrashingPage, rootElement}),
    )
    handler()
    render(mockRender.mock.calls[0][0])
    console.error.mockRestore()

    expect(
      screen.getByRole('heading', {name: 'Something went wrong'}),
    ).toBeInTheDocument()
    expect(screen.queryByTestId('application-wrapper')).not.toBeInTheDocument()
    expect(screen.queryByTestId('modal-window')).not.toBeInTheDocument()
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
    expect(mockCreateRoot).toHaveBeenNthCalledWith(
      2,
      notifWrapper,
      rootErrorOptions,
    )
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
