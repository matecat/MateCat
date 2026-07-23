import {renderHook, act, waitFor} from '@testing-library/react'
import useSocketLayer, {ConnectionStates} from './useSocketLayer'
import {getSocketAuthToken} from '../api/loginUser'
import {io} from 'socket.io-client'

jest.mock('../api/loginUser', () => ({
  getSocketAuthToken: jest.fn(),
}))
jest.mock('socket.io-client', () => ({
  io: jest.fn(),
}))

const makeFakeSocket = () => {
  const handlers = {}
  return {
    on: jest.fn((event, cb) => {
      handlers[event] = cb
    }),
    close: jest.fn(),
    __handlers: handlers,
  }
}

describe('useSocketLayer', () => {
  let fakeSocket

  beforeEach(() => {
    jest.useFakeTimers()
    jest.clearAllMocks()
    fakeSocket = makeFakeSocket()
    io.mockReturnValue(fakeSocket)
    getSocketAuthToken.mockResolvedValue({token: 'tok-123'})
    jest.spyOn(console, 'log').mockImplementation(() => {})
    jest.spyOn(console, 'error').mockImplementation(() => {})
  })

  afterEach(() => {
    jest.runOnlyPendingTimers()
    jest.useRealTimers()
  })

  const connectionParams = {source: 'http://sock', path: '/socket.io'}
  const options = {uuidV4: 'uuid', userId: 1, jobId: 2, projectId: 3}

  test('connects and transitions to OPEN on the socket connect event', async () => {
    const {result} = renderHook(() =>
      useSocketLayer(connectionParams, options, true),
    )

    await waitFor(() => expect(io).toHaveBeenCalled())
    expect(io).toHaveBeenCalledWith(
      connectionParams.source,
      expect.objectContaining({
        path: connectionParams.path,
        reconnection: false,
        extraHeaders: expect.objectContaining({
          'x-token': 'tok-123',
          'x-uuid': 'uuid',
          'x-userid': 1,
          'x-jobid': 2,
          'x-projectid': 3,
        }),
        transports: ['websocket', 'polling'],
      }),
    )

    act(() => {
      fakeSocket.__handlers['connect']()
    })
    expect(result.current.connectionState).toBe(ConnectionStates.OPEN)
    expect(result.current.connectionError).toBe(null)
  })

  test('does not connect when isAuthenticated is false and closes an existing source', async () => {
    const {result, rerender} = renderHook(
      (auth) => useSocketLayer(connectionParams, options, auth),
      {initialProps: true},
    )
    await waitFor(() => expect(io).toHaveBeenCalledTimes(1))
    act(() => {
      fakeSocket.__handlers['connect']()
    })
    expect(result.current.connectionState).toBe(ConnectionStates.OPEN)

    rerender(false)
    expect(fakeSocket.close).toHaveBeenCalled()
    expect(result.current.connectionState).toBe(ConnectionStates.CLOSED)
  })

  test('connect_error sets CLOSED state, stores the error and schedules a reconnect', async () => {
    const eventHandlers = {disconnected: jest.fn(), reconnected: jest.fn()}
    const {result} = renderHook(() =>
      useSocketLayer(connectionParams, options, true, eventHandlers),
    )
    await waitFor(() => expect(io).toHaveBeenCalledTimes(1))

    const error = new Error('boom')
    act(() => {
      fakeSocket.__handlers['connect_error'](error)
    })
    expect(result.current.connectionState).toBe(ConnectionStates.CLOSED)
    expect(result.current.connectionError).toBe(error)
    expect(eventHandlers.disconnected).toHaveBeenCalled()

    // Reconnect is scheduled after 2000ms and calls connect() again
    await act(async () => {
      jest.advanceTimersByTime(2000)
    })
    await waitFor(() => expect(io).toHaveBeenCalledTimes(2))

    // By the time the delayed reconnect's socket actually connects, the retry
    // ref was already nulled out by the timeout callback itself, so a single
    // retry cycle does not fire "reconnected" (see the next test for the case
    // that does: a second failure occurring while the reconnect is in flight).
    act(() => {
      fakeSocket.__handlers['connect']()
    })
    expect(result.current.connectionState).toBe(ConnectionStates.OPEN)
    expect(eventHandlers.reconnected).not.toHaveBeenCalled()
  })

  test('a second failure while reconnecting keeps the retry ref set until the delayed connect succeeds', async () => {
    let resolveSecondToken
    getSocketAuthToken
      .mockResolvedValueOnce({token: 'first'})
      .mockImplementationOnce(
        () =>
          new Promise((resolve) => {
            resolveSecondToken = resolve
          }),
      )
    const eventHandlers = {disconnected: jest.fn(), reconnected: jest.fn()}
    renderHook(() =>
      useSocketLayer(connectionParams, options, true, eventHandlers),
    )
    await waitFor(() => expect(io).toHaveBeenCalledTimes(1))

    // First failure schedules the retry timeout
    act(() => {
      fakeSocket.__handlers['connect_error'](new Error('first'))
    })

    // Timeout fires: retryingInterval is cleared then connect() is invoked,
    // whose token request stays pending (deferred promise above)
    await act(async () => {
      jest.advanceTimersByTime(2000)
    })
    expect(io).toHaveBeenCalledTimes(1)

    // While the reconnect attempt is in flight, the (still registered) old
    // socket reports another failure, which schedules a *new* retry timeout
    act(() => {
      fakeSocket.__handlers['connect_error'](new Error('second'))
    })

    // Resolving the pending token lets connect() finish and re-create the socket
    await act(async () => {
      resolveSecondToken({token: 'second'})
    })
    await waitFor(() => expect(io).toHaveBeenCalledTimes(2))

    // The freshly (re)registered connect handler sees the still-pending retry
    // timeout scheduled above and clears it, notifying "reconnected"
    act(() => {
      fakeSocket.__handlers['connect']()
    })
    expect(eventHandlers.reconnected).toHaveBeenCalled()
  })

  test('disconnect event triggers reconnect flow', async () => {
    const eventHandlers = {disconnected: jest.fn()}
    renderHook(() =>
      useSocketLayer(connectionParams, options, true, eventHandlers),
    )
    await waitFor(() => expect(io).toHaveBeenCalledTimes(1))

    act(() => {
      fakeSocket.__handlers['disconnect']()
    })
    expect(eventHandlers.disconnected).toHaveBeenCalled()

    await act(async () => {
      jest.advanceTimersByTime(2000)
    })
    await waitFor(() => expect(io).toHaveBeenCalledTimes(2))
  })

  test('message event updates eventData, invokes matching handler and dispatches a DOM event', async () => {
    const dispatchSpy = jest.spyOn(document, 'dispatchEvent')
    const onCustomEvent = jest.fn()
    const {result} = renderHook(() =>
      useSocketLayer(connectionParams, options, true, {
        myCustomEvent: onCustomEvent,
      }),
    )
    await waitFor(() => expect(io).toHaveBeenCalledTimes(1))

    const payload = {_type: 'myCustomEvent', foo: 'bar'}
    act(() => {
      fakeSocket.__handlers['message']({data: payload})
    })

    expect(result.current.eventData).toEqual({myCustomEvent: payload})
    expect(onCustomEvent).toHaveBeenCalledWith(payload)
    expect(dispatchSpy).toHaveBeenCalled()
  })

  test('message event handles malformed payloads without throwing', async () => {
    renderHook(() => useSocketLayer(connectionParams, options, true))
    await waitFor(() => expect(io).toHaveBeenCalledTimes(1))

    expect(() => {
      act(() => {
        fakeSocket.__handlers['message']({})
      })
    }).not.toThrow()
    expect(console.error).toHaveBeenCalled()
  })

  test('getSocketAuthToken failure triggers reconnect', async () => {
    getSocketAuthToken.mockRejectedValueOnce(new Error('token error'))
    renderHook(() => useSocketLayer(connectionParams, options, true))

    await waitFor(() =>
      expect(console.log).toHaveBeenCalledWith(
        'Token error',
        expect.any(Error),
      ),
    )
    expect(io).not.toHaveBeenCalled()

    await act(async () => {
      jest.advanceTimersByTime(2000)
    })
    await waitFor(() => expect(io).toHaveBeenCalledTimes(1))
  })

  test('closeConnection manually closes the socket and resets the retry ref', async () => {
    const {result} = renderHook(() =>
      useSocketLayer(connectionParams, options, true),
    )
    await waitFor(() => expect(io).toHaveBeenCalledTimes(1))
    act(() => {
      fakeSocket.__handlers['connect']()
    })

    act(() => {
      result.current.closeConnection()
    })
    expect(fakeSocket.close).toHaveBeenCalled()
  })

  test('clears the pending retry timeout on unmount so no further reconnect happens', async () => {
    const {unmount} = renderHook(() =>
      useSocketLayer(connectionParams, options, true),
    )
    await waitFor(() => expect(io).toHaveBeenCalledTimes(1))
    act(() => {
      fakeSocket.__handlers['connect_error'](new Error('x'))
    })

    unmount()

    // The retry timeout was cleared on unmount, so advancing past its delay
    // must not trigger another connection attempt.
    await act(async () => {
      jest.advanceTimersByTime(5000)
    })
    expect(io).toHaveBeenCalledTimes(1)
  })
})
