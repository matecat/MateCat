import {useCallback, useEffect, useRef, useState} from 'react'
import {getUserData} from '../api/getUserData'
import UserActions from '../actions/UserActions'
import UserStore from '../stores/UserStore'
import UserConstants from '../constants/UserConstants'
import CommonUtils from '../utils/commonUtils'
import commonUtils from '../utils/commonUtils'
import {isEqual} from 'lodash'
import {logoutUser} from '../api/logoutUser'
import {updateUserMetadata} from '../api/updateUserMetadata'
import {flushSync} from 'react-dom'

const USER_INFO_SCHEMA = {
  user: {
    email: 'email',
    first_name: 'first_name',
    has_password: 'has_password',
    last_name: 'last_name',
    uid: 'uid',
  },
  connected_services: 'connected_services',
  metadata: 'metadata',
  teams: 'teams',
}

const localStorageUserIsLoggedInThisBrowser = 'isUserLogged-'

function useAuth() {
  const [userInfo, setStateUserInfo] = useState(false)
  const [connectedServices, setConnectedServices] = useState()
  const [userDisconnected, setUserDisconnected] = useState(false)
  const [isUserLogged, setIsUserLogged] = useState()

  const setUserInfo = useCallback((value) => {
    setStateUserInfo((prevState) => {
      const result = typeof value === 'function' ? value(prevState) : value

      const validate = (obj, schemaCompare) => {
        const keys = Object.keys(obj)
        const keysCompare = Object.keys(schemaCompare)

        if (
          keys.filter((value) => keysCompare.some((key) => key === value))
            .length !== keysCompare.length
        )
          return false

        const iterations = Object.entries(schemaCompare)
          .map(([key, value]) => ({key, value}))
          .filter(({value}) => typeof value !== 'string')

        if (!iterations) {
          return true
        } else {
          return iterations
            .map(({key, value}) => {
              return validate(obj[key], value)
            })
            .every((value) => value)
        }
      }

      if (result && !validate(result, USER_INFO_SCHEMA))
        throw new Error('userInfo object not valid.')

      return result
    })
  }, [])

  const checkUserLogin = useRef()
  checkUserLogin.current = () => {
    if (
      !isUserLogged ||
      commonUtils.getFromStorage(
        localStorageUserIsLoggedInThisBrowser + userInfo.user.uid,
      ) !== '1'
    ) {
      getUserData()
        .then((data) => {
          const event = {
            event: 'user_data_ready',
            userStatus: 'loggedUser',
            userId: data.user.uid,
          }
          CommonUtils.dispatchAnalyticsEvents(event)
          CommonUtils.dispatchCustomEvent('user-logged-event', data.user)
          setIsUserLogged(true)
          setUserInfo(data)
          setConnectedServices(data.connected_services)
          commonUtils.addInStorage(
            localStorageUserIsLoggedInThisBrowser + data.user.uid,
            1,
          )
        })
        .catch((e) => {
          const event = {
            event: 'user_data_ready',
            userStatus: 'notLoggedUser',
          }
          setTimeout(() => CommonUtils.dispatchAnalyticsEvents(event), 500)
          userInfo &&
            commonUtils.removeFromStorage(
              localStorageUserIsLoggedInThisBrowser + userInfo.user.uid,
            )
          setUserInfo()
          setIsUserLogged(false)
          setConnectedServices()
          userInfo && setTimeout(() => setUserDisconnected(true), 500)
        })
    }
  }

  const forceLogout = useCallback(() => {
    // This branch condition allows checking if the user logged out from another browser
    // to avoid to call logout more than once.
    // Logout MUST be invoked only once, otherwise XSFR token set in server side disappears, and the next login will fail.
    // If the user logged out from THIS browser, the session storage is already clean since
    // this is a reaction to a message dispatched (via SSE) from a previous logout event.
    if (
      commonUtils.getFromStorage(
        localStorageUserIsLoggedInThisBrowser + userInfo?.user?.uid,
      ) === '1'
    ) {
      // localStorage.removeItem(key) is atomic.
      //
      // Immediately clean the session and not in the .then() promise, this avoid race conditions
      // between get/set storage value when the checkUserLogin() is called.
      commonUtils.removeFromStorage(
        localStorageUserIsLoggedInThisBrowser + userInfo.user.uid,
      )
      setIsUserLogged(false)
      setUserDisconnected(true)
      setUserInfo()
      setConnectedServices()
      logoutUser().then(() => {})
    }
  }, [setUserInfo, userInfo?.user?.uid])

  const logout = () => {
    // localStorage.removeItem(key) is atomic.
    //
    // Immediately clean the session and not in the .then() promise, this avoid race conditions
    // between get/set storage value when the checkUserLogin() is called.
    commonUtils.removeFromStorage(
      localStorageUserIsLoggedInThisBrowser + userInfo.user.uid,
    )
    logoutUser().then(() => {
      window.location.reload()
    })
  }

  const setUserMetadataKey = useCallback(
    async (key, value) =>
      new Promise((resolve, reject) => {
        updateUserMetadata(key, value)
          .then((data) => {
            flushSync(() =>
              setUserInfo((prevState) => ({
                ...prevState,
                metadata: {...prevState.metadata, [key]: value},
              })),
            )

            resolve(data)
          })
          .catch(() => reject())
      }),
    [setUserInfo],
  )

  useEffect(() => {
    checkUserLogin.current()
  }, [])

  // Check user cookie is already valid
  useEffect(() => {
    let interval

    if (isUserLogged) {
      interval = setInterval(() => {
        checkUserLogin.current()
      }, 5000)

      setUserDisconnected(false)
    }

    return () => clearInterval(interval)
  }, [isUserLogged])

  // Sync UserStore with state userInfo
  useEffect(() => {
    if (!isEqual(UserStore.getUser(), userInfo)) {
      UserActions.updateUser(userInfo)

      // Trick for hubspot
      if (userInfo) CommonUtils.dispatchCustomEvent('userDataLoaded', userInfo)
    }
  }, [userInfo])

  // Sync state userInfo with UserStore
  useEffect(() => {
    if (!isUserLogged) return

    const updateUser = (updatedUserInfo) =>
      setUserInfo((prevState) =>
        !isEqual(updatedUserInfo, prevState) ? updatedUserInfo : prevState,
      )

    const updateTeams = (data) => {
      const dataJs = data.toJS()
      setUserInfo((prevState) => {
        const teams = Array.isArray(dataJs)
          ? dataJs.map((team) => ({
              ...team,
              isSelected: prevState.teams.find(({id}) => id === team.id)
                ?.isSelected,
            }))
          : prevState.teams.map((team) =>
              team.id === dataJs.id
                ? {...dataJs, isSelected: team.isSelected}
                : team,
            )

        const updatedUserInfo = {...prevState, teams}
        return !isEqual(updatedUserInfo, prevState)
          ? updatedUserInfo
          : prevState
      })
    }

    const selectTeam = (teamId) =>
      setUserInfo((prevState) => {
        const teams = prevState.teams.map((team) => ({
          ...team,
          isSelected: team.id === teamId,
        }))

        const updatedUserInfo = {...prevState, teams}
        return !isEqual(updatedUserInfo, prevState)
          ? updatedUserInfo
          : prevState
      })

    UserStore.addListener(UserConstants.UPDATE_USER, updateUser)
    UserStore.addListener(UserConstants.RENDER_TEAMS, updateTeams)
    UserStore.addListener(UserConstants.UPDATE_TEAM, updateTeams)
    UserStore.addListener(UserConstants.UPDATE_TEAMS, updateTeams)
    UserStore.addListener(UserConstants.CHOOSE_TEAM, selectTeam)

    return () => {
      UserStore.removeListener(UserConstants.UPDATE_USER, updateUser)
      UserStore.removeListener(UserConstants.RENDER_TEAMS, updateTeams)
      UserStore.removeListener(UserConstants.UPDATE_TEAM, updateTeams)
      UserStore.removeListener(UserConstants.UPDATE_TEAMS, updateTeams)
      UserStore.removeListener(UserConstants.CHOOSE_TEAM, selectTeam)
    }
  }, [isUserLogged, setUserInfo])

  return {
    isUserLogged,
    userInfo,
    connectedServices,
    userDisconnected,
    setUserInfo,
    logout,
    forceLogout,
    setUserMetadataKey,
  }
}

export default useAuth
