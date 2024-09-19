import {useCallback, useEffect, useRef, useState} from 'react'
import Cookies from 'js-cookie'
import {getUserData} from '../api/getUserData'
import UserActions from '../actions/UserActions'
import UserStore from '../stores/UserStore'
import UserConstants from '../constants/UserConstants'
import CommonUtils from '../utils/commonUtils'
import {isEqual} from 'lodash'
import {logoutUser} from '../api/logoutUser'

export const USER_LOGIN_COOKIE = 'matecat_login_v6'

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

function useAuth() {
  const [userInfo, setStateUserInfo] = useState(false)
  const [connectedServices, setConnectedServices] = useState()
  const [userDisconnected, setUserDisconnected] = useState(false)

  const isUserLogged =
    typeof userInfo === 'boolean' && !userInfo
      ? undefined
      : typeof userInfo === 'object'

  console.log('isUserLogged', isUserLogged)

  const parseJWT = (jwt) => {
    try {
      return JSON.parse(atob(jwt.split('.')[1])).context
    } catch (e) {
      console.log('Errore parsing user Token', e)
      return null
    }
  }

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

  const checkUserCookie = useRef()
  checkUserCookie.current = () => {
    const userCookie = Cookies.get(USER_LOGIN_COOKIE)

    if (userCookie) {
      const userToken = parseJWT(userCookie)
      if (
        userToken &&
        (!userInfo || userInfo.user.uid !== userToken.user.uid)
      ) {
        getUserData().then(function (data) {
          const event = {
            event: 'user_data_ready',
            userStatus: 'loggedUser',
            userId: data.user.uid,
          }
          CommonUtils.dispatchAnalyticsEvents(event)
          setUserInfo(data)
          setConnectedServices(data.connected_services)
        })
      }
    } else {
      if (isUserLogged) setTimeout(() => setUserDisconnected(true), 500)
      setUserInfo()
      setConnectedServices()
      const event = {
        event: 'user_data_ready',
        userStatus: 'notLoggedUser',
      }
      setTimeout(() => CommonUtils.dispatchAnalyticsEvents(event), 500)
    }
  }

  // Check user cookie is already valid
  useEffect(() => {
    checkUserCookie.current()

    let interval

    if (userInfo) {
      interval = setInterval(checkUserCookie.current, 1000)
      setUserDisconnected(false)
    } else if (interval) {
      clearInterval(interval)
    }

    return () => clearInterval(interval)
  }, [userInfo])

  // Sync UserStore with state userInfo
  useEffect(() => {
    if (!isEqual(UserStore.getUser(), userInfo)) {
      UserActions.updateUser(userInfo)

      // Trick for hubspot
      if (userInfo) CommonUtils.dispatchCustomEvent('userDataLoaded', userInfo)
    }
    console.log('[ useAuth ] -> userInfo', userInfo)
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
  }
}

export default useAuth
