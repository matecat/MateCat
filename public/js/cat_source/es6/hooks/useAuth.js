import {useCallback, useEffect, useState} from 'react'
import Cookies from 'js-cookie'
import {getUserData} from '../api/getUserData'
import UserActions from '../actions/UserActions'
export const USER_LOGIN_COOKIE = 'matecat_login_v6'
function useAuth() {
  const [isUserLogged, setIsUserLogged] = useState(false)
  const [userInfo, setUserInfo] = useState()
  const [connectedServices, setConnectedServices] = useState()

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
      if (userToken && (!userInfo || userInfo.uid !== userToken.user.uid)) {
        getUserData().then(function (data) {
          $(document).trigger('userDataLoaded', data)
          setIsUserLogged(true)
          setUserInfo(data.user)
          setConnectedServices(data.connected_services)
          //TODO ?? Teams hanno uno store ma lo user noooo ??
          UserActions.updateUser(data)
          console.log('# User connected', data)
        })
      }
    } else {
      console.log('# User disconnected')
      setIsUserLogged(false)
      setConnectedServices()
      setUserInfo()
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
  return {
    isUserLogged,
    userInfo,
    connectedServices,
  }
}

export default useAuth
