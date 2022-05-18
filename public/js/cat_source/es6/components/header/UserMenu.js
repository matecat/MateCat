import React, {useRef, useEffect} from 'react'
import IconUserLogout from '../icons/IconUserLogout'
import {logoutUser} from '../../api/logoutUser'

export const UserMenu = ({user, userLogged}) => {
  const dropdownProfile = useRef()

  const openLoginModal = () => {
    APP.openLoginModal()
  }

  const openManage = () => {
    document.location.href = '/manage'
  }

  const openPreferencesModal = () => {
    $('#modal').trigger('openpreferences')
  }

  const logoutUserFn = () => {
    logoutUser().then(() => {
      if ($('body').hasClass('manage')) {
        location.href = config.hostpath + config.basepath
      } else {
        window.location.reload()
      }
    })
  }

  useEffect(() => {
    if ($(dropdownProfile.current).length) {
      $(dropdownProfile.current).dropdown()
    }
  }, [])

  return (
    <div
      className="ui dropdown"
      ref={dropdownProfile}
      id="profile-menu"
      data-testid="user-menu"
    >
      {userLogged ? (
        <>
          {user?.metadata && user.metadata.gplus_picture ? (
            <img
              className="ui mini circular image ui-user-top-image"
              src={user.metadata.gplus_picture + '?sz=80'}
              title="Personal settings"
              alt="Profile picture"
            />
          ) : (
            <div
              className="ui user circular image ui-user-top-image"
              data-testid="user-menu-metadata"
              title="Personal settings"
            >
              {config.userShortName}
            </div>
          )}
          <div className="menu">
            <div
              className="item"
              data-value="Manage"
              id="manage-item"
              onClick={openManage}
            >
              My Projects
            </div>
            <div
              className="item"
              data-value="profile"
              id="profile-item"
              onClick={openPreferencesModal}
              data-testid="profile-item"
            >
              Profile
            </div>
            <div
              className="item"
              data-value="logout"
              id="logout-item"
              data-testid="logout-item"
              onClick={logoutUserFn}
            >
              Logout
            </div>
          </div>
        </>
      ) : (
        <div
          className="ui user-nolog label"
          onClick={openLoginModal}
          title="Login"
        >
          {/*<i className="icon-user22"/>*/}
          <IconUserLogout width={40} height={40} color={'#fff'} />
        </div>
      )}
    </div>
  )
}
