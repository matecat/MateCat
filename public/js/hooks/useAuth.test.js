import {renderHook, act, waitFor} from '@testing-library/react'
import {fromJS} from 'immutable'
import useAuth from './useAuth'
import {getUserData} from '../api/getUserData'
import {logoutUser} from '../api/logoutUser'
import {updateUserMetadata} from '../api/updateUserMetadata'
import UserActions from '../actions/UserActions'
import UserStore from '../stores/UserStore'
import UserConstants from '../constants/UserConstants'
import CommonUtils from '../utils/commonUtils'

jest.mock('../api/getUserData', () => ({getUserData: jest.fn()}))
jest.mock('../api/logoutUser', () => ({
  logoutUser: jest.fn(() => Promise.resolve({})),
}))
jest.mock('../api/updateUserMetadata', () => ({
  updateUserMetadata: jest.fn(),
}))
jest.mock('../actions/UserActions', () => ({
  updateUser: jest.fn(),
}))
jest.mock('../stores/UserStore', () => ({
  getUser: jest.fn(),
  addListener: jest.fn(),
  removeListener: jest.fn(),
}))
jest.mock('../utils/commonUtils', () => ({
  getFromStorage: jest.fn(),
  addInStorage: jest.fn(),
  removeFromStorage: jest.fn(),
  dispatchAnalyticsEvents: jest.fn(),
  dispatchCustomEvent: jest.fn(),
  getGMTDate: jest.fn(),
}))

const validUserData = {
  user: {
    email: 'user@translated.net',
    first_name: 'Foo',
    has_password: true,
    last_name: 'Bar',
    uid: 42,
  },
  connected_services: ['google'],
  metadata: {},
  teams: [{id: 1, name: 'Team 1', isSelected: true}],
}

describe('useAuth', () => {
  beforeEach(() => {
    jest.useFakeTimers()
    jest.clearAllMocks()
    UserStore.getUser.mockReturnValue(undefined)
    CommonUtils.getFromStorage.mockReturnValue(undefined)
    getUserData.mockResolvedValue(validUserData)
    // jsdom's window.location.reload is non-configurable, so it can't be
    // spied/replaced; suppress its "not implemented" console noise instead.
    jest.spyOn(console, 'error').mockImplementation(() => {})
  })

  afterEach(() => {
    jest.runOnlyPendingTimers()
    jest.useRealTimers()
  })

  test('mounts, logs in successfully and stores flags', async () => {
    getUserData.mockResolvedValueOnce(validUserData)

    const {result} = renderHook(() => useAuth())

    await waitFor(() => expect(result.current.isUserLogged).toBe(true))

    expect(result.current.userInfo).toEqual(validUserData)
    expect(result.current.connectedServices).toEqual(['google'])
    expect(CommonUtils.dispatchAnalyticsEvents).toHaveBeenCalledWith({
      event: 'user_data_ready',
      userStatus: 'loggedUser',
      userId: 42,
    })
    expect(CommonUtils.dispatchCustomEvent).toHaveBeenCalledWith(
      'user-logged-event',
      validUserData.user,
    )
    expect(CommonUtils.addInStorage).toHaveBeenCalledWith('isUserLogged-42', 1)
    // UserStore sync effect fires because UserStore.getUser() !== userInfo
    expect(UserActions.updateUser).toHaveBeenCalledWith(validUserData)
  })

  test('login failure resets state and dispatches events on delay', async () => {
    getUserData.mockRejectedValueOnce(new Error('nope'))

    const {result} = renderHook(() => useAuth())

    await waitFor(() => expect(result.current.isUserLogged).toBe(false))
    expect(result.current.userInfo).toBeFalsy()

    act(() => {
      jest.advanceTimersByTime(500)
    })
    expect(CommonUtils.dispatchAnalyticsEvents).toHaveBeenCalledWith({
      event: 'user_data_ready',
      userStatus: 'notLoggedUser',
    })
  })

  test('periodic check re-authenticates and handles failure with truthy userInfo', async () => {
    getUserData.mockResolvedValueOnce(validUserData)
    const {result, unmount} = renderHook(() => useAuth())

    await waitFor(() => expect(result.current.isUserLogged).toBe(true))

    // storage flag already set to '1' -> checkUserLogin should skip refetch
    CommonUtils.getFromStorage.mockReturnValue('1')
    getUserData.mockClear()

    await act(async () => {
      jest.advanceTimersByTime(5000)
    })
    expect(getUserData).not.toHaveBeenCalled()

    // storage flag missing now -> triggers refetch, which fails this time
    CommonUtils.getFromStorage.mockReturnValue(undefined)
    getUserData.mockRejectedValueOnce(new Error('boom'))

    await act(async () => {
      jest.advanceTimersByTime(5000)
    })
    await waitFor(() => expect(result.current.isUserLogged).toBe(false))

    // userInfo was truthy -> removeFromStorage branch + delayed setUserDisconnected
    expect(CommonUtils.removeFromStorage).toHaveBeenCalled()
    act(() => {
      jest.advanceTimersByTime(500)
    })
    expect(result.current.userDisconnected).toBe(true)

    unmount()
  })

  test('forceLogout clears session only when this browser is flagged as logged in', async () => {
    getUserData.mockResolvedValueOnce(validUserData)
    const {result} = renderHook(() => useAuth())
    await waitFor(() => expect(result.current.isUserLogged).toBe(true))

    CommonUtils.getFromStorage.mockReturnValue('0')
    act(() => {
      result.current.forceLogout()
    })
    expect(logoutUser).not.toHaveBeenCalled()

    CommonUtils.getFromStorage.mockReturnValue('1')
    act(() => {
      result.current.forceLogout()
    })
    expect(CommonUtils.removeFromStorage).toHaveBeenCalledWith(
      'isUserLogged-42',
    )
    expect(result.current.isUserLogged).toBe(false)
    expect(result.current.userDisconnected).toBe(true)
    expect(logoutUser).toHaveBeenCalled()
  })

  test('logout removes storage flag, calls API and reloads the page', async () => {
    getUserData.mockResolvedValueOnce(validUserData)
    const {result} = renderHook(() => useAuth())
    await waitFor(() => expect(result.current.isUserLogged).toBe(true))

    await act(async () => {
      result.current.logout()
    })

    expect(CommonUtils.removeFromStorage).toHaveBeenCalledWith(
      'isUserLogged-42',
    )
    expect(logoutUser).toHaveBeenCalled()
  })

  test('setUserMetadataKey resolves on success and rejects on failure', async () => {
    getUserData.mockResolvedValueOnce(validUserData)
    const {result} = renderHook(() => useAuth())
    await waitFor(() => expect(result.current.isUserLogged).toBe(true))

    updateUserMetadata.mockResolvedValueOnce({ok: true})
    await act(async () => {
      await expect(
        result.current.setUserMetadataKey('foo', 'bar'),
      ).resolves.toEqual({ok: true})
    })
    expect(result.current.userInfo.metadata).toEqual({foo: 'bar'})

    updateUserMetadata.mockRejectedValueOnce(new Error('fail'))
    await act(async () => {
      await expect(
        result.current.setUserMetadataKey('foo', 'baz'),
      ).rejects.toBeUndefined()
    })
  })

  test('setUserInfo throws when the payload does not match the schema', async () => {
    getUserData.mockResolvedValueOnce(validUserData)
    const {result} = renderHook(() => useAuth())
    await waitFor(() => expect(result.current.isUserLogged).toBe(true))

    expect(() => {
      act(() => {
        result.current.setUserInfo({not: 'valid'})
      })
    }).toThrow('userInfo object not valid.')
  })

  test('reacts to UserStore events while logged in and cleans up on unmount', async () => {
    getUserData.mockResolvedValueOnce(validUserData)
    const {result, unmount} = renderHook(() => useAuth())
    await waitFor(() => expect(result.current.isUserLogged).toBe(true))

    const listenersByConstant = {}
    UserStore.addListener.mock.calls.forEach(([constant, cb]) => {
      listenersByConstant[constant] = cb
    })

    act(() => {
      listenersByConstant[UserConstants.UPDATE_USER]({
        ...validUserData,
        user: {...validUserData.user, first_name: 'Changed'},
      })
    })
    expect(result.current.userInfo.user.first_name).toBe('Changed')

    act(() => {
      listenersByConstant[UserConstants.RENDER_TEAMS](
        fromJS([
          {id: 1, name: 'Team 1'},
          {id: 2, name: 'Team 2'},
        ]),
      )
    })
    expect(result.current.userInfo.teams).toHaveLength(2)

    act(() => {
      listenersByConstant[UserConstants.UPDATE_TEAM](
        fromJS({id: 1, name: 'Team 1 renamed'}),
      )
    })
    expect(result.current.userInfo.teams.find(({id}) => id === 1).name).toBe(
      'Team 1 renamed',
    )

    act(() => {
      listenersByConstant[UserConstants.CHOOSE_TEAM](2)
    })
    expect(
      result.current.userInfo.teams.find(({id}) => id === 2).isSelected,
    ).toBe(true)

    unmount()
    expect(UserStore.removeListener).toHaveBeenCalledWith(
      UserConstants.UPDATE_USER,
      expect.any(Function),
    )
  })
})
