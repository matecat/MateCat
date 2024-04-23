import {useCallback, useEffect, useState} from 'react'
import Cookies from 'js-cookie'
import {getUserData} from '../api/getUserData'
import UserActions from '../actions/UserActions'
import UserStore from '../stores/UserStore'
import UserConstants from '../constants/UserConstants'
import CommonUtils from '../utils/commonUtils'
export const USER_LOGIN_COOKIE = 'matecat_login_v6'
function useAuth() {
  const [isUserLogged, setIsUserLogged] = useState(false)
  const [userInfo, setUserInfo] = useState()
  const [connectedServices, setConnectedServices] = useState()
  const [userDisconnected, setUserDisconnected] = useState(false)

  const parseJWT = (jwt) => {
    try {
      return JSON.parse(atob(jwt.split('.')[1])).context
    } catch (e) {
      console.log('Errore parsing user Token', e)
    }
  }

  const checkUserCookie = useCallback(() => {
    const userCookie = Cookies.get(USER_LOGIN_COOKIE)
    if (userCookie) {
      let userToken = parseJWT(userCookie)
      if (
        userToken &&
        (!userInfo || userInfo.user.uid !== userToken.user.uid)
      ) {
        getUserData().then(function (data) {
          UserActions.updateUser(data)
          setUserInfo(data)
          setIsUserLogged(true)
          setConnectedServices(data.connected_services)
          CommonUtils.dispatchCustomEvent('userDataLoaded', data)
        })
      }
    } else {
      isUserLogged && setTimeout(() => setUserDisconnected(true), 500)
      setIsUserLogged(false)
      setConnectedServices()
      setUserInfo()
      UserActions.updateUser()
    }
  }, [userInfo])

  useEffect(() => {
    checkUserCookie()
    let interval
    if (userInfo) {
      interval = setInterval(checkUserCookie, 100)
    } else if (interval) {
      clearInterval(interval)
    }
    return () => clearInterval(interval)
  }, [checkUserCookie, userInfo])
  useEffect(() => {
    const updateUser = () => {
      setUserInfo(userInfo)
      if (userInfo) {
        setIsUserLogged(true)
      } else {
        setUserDisconnected(false)
      }
    }
    UserStore.addListener(UserConstants.UPDATE_USER, updateUser)
    return () => {
      UserStore.removeListener(UserConstants.UPDATE_USER, updateUser)
    }
  }, [])
  return {
    isUserLogged,
    userInfo,
    connectedServices,
    userDisconnected,
  }
}

export default useAuth
