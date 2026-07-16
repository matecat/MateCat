import React from 'react'
import {render} from '@testing-library/react'
import SocketListener from './SocketListener'
import useSocketLayer, {ConnectionStates} from '../hooks/useSocketLayer'
import CatToolActions from '../actions/CatToolActions'
import SegmentActions from '../actions/SegmentActions'
import UserActions from '../actions/UserActions'
import {ApplicationWrapperContext} from '../components/common/ApplicationWrapper/ApplicationWrapperContext'

jest.mock('../hooks/useSocketLayer', () => ({
  __esModule: true,
  default: jest.fn(),
  ConnectionStates: {
    CONNECTING: 'CONNECTING',
    OPEN: 'OPEN',
    CLOSED: 'CLOSED',
    ERROR: 'ERROR',
  },
}))

jest.mock('../actions/CatToolActions')
jest.mock('../actions/SegmentActions')
jest.mock('../actions/UserActions')

describe('SocketListener', () => {
  const forceLogout = jest.fn()
  let eventHandlers

  const renderComponent = (props = {}) => {
    useSocketLayer.mockReturnValue({
      connectionState: ConnectionStates.OPEN,
      connectionError: null,
      closeConnection: jest.fn(),
      eventData: {},
    })

    render(
      <ApplicationWrapperContext.Provider value={{forceLogout}}>
        <SocketListener isAuthenticated userId={42} {...props} />
      </ApplicationWrapperContext.Provider>,
    )

    const lastCall =
      useSocketLayer.mock.calls[useSocketLayer.mock.calls.length - 1]
    eventHandlers = lastCall[3]
  }

  beforeEach(() => {
    renderComponent()
  })

  test('passes the eventHandlers map to useSocketLayer', () => {
    expect(typeof eventHandlers.disconnected).toBe('function')
    expect(useSocketLayer).toHaveBeenCalledWith(
      expect.objectContaining({
        path: '/sse/channel/updates/socket.io',
      }),
      expect.objectContaining({userId: '42', jobId: config.id_job}),
      true,
      expect.any(Object),
    )
  })

  test('disconnected marks the client as disconnected', () => {
    eventHandlers.disconnected()

    expect(CatToolActions.clientConnected).toHaveBeenCalledTimes(1)
    expect(CatToolActions.clientConnected).toHaveBeenCalledWith(false)
  })

  test('reconnected notifies the client reconnection', () => {
    eventHandlers.reconnected()

    expect(CatToolActions.clientReconnect).toHaveBeenCalledTimes(1)
  })

  test('force_reload triggers a forced reload via UserActions', () => {
    eventHandlers.force_reload()

    expect(UserActions.forceReload).toHaveBeenCalledTimes(1)
  })

  test('quota_exceeded shows the lara quota exceeded warning', () => {
    eventHandlers.quota_exceeded()

    expect(CatToolActions.showLaraQuotaExceeded).toHaveBeenCalledTimes(1)
  })

  test('concordance forwards the result to SegmentActions', () => {
    const data = {id_segment: 7, matches: ['a']}
    eventHandlers.concordance(data)

    expect(SegmentActions.setConcordanceResult).toHaveBeenCalledWith(7, data)
  })

  test('logout calls forceLogout from ApplicationWrapperContext', () => {
    jest.spyOn(console, 'log').mockImplementation(() => {})

    eventHandlers.logout({reason: 'session_expired'})

    expect(forceLogout).toHaveBeenCalledTimes(1)
  })

  describe('ack', () => {
    const originalBuildNumber = config.build_number

    afterEach(() => {
      config.build_number = originalBuildNumber
    })

    test('connects the client and skips the update notification when the version matches', () => {
      config.build_number = 42

      eventHandlers.ack({clientId: 'client-abc', serverVersion: 42})

      expect(config.id_client).toBe('client-abc')
      expect(CatToolActions.clientConnected).toHaveBeenCalledWith('client-abc')
      expect(CatToolActions.addNotification).not.toHaveBeenCalled()
    })

    test('shows an update notification when the server version differs', () => {
      config.build_number = 42

      eventHandlers.ack({clientId: 'client-abc', serverVersion: 43})

      expect(CatToolActions.addNotification).toHaveBeenCalledWith(
        expect.objectContaining({
          title: 'New update available!',
          type: 'warning',
        }),
      )
    })
  })
})
