import React, {useEffect, useRef} from 'react'
import IconUserLogout from '../icons/IconUserLogout'
import {logoutUser} from '../../api/logoutUser'
import CatToolStore from '../../stores/CatToolStore'
import CatToolConstants from '../../constants/CatToolConstants'
import CatToolActions from '../../actions/CatToolActions'

export const UserMenu = ({user, userLogged}) => {
  const dropdownProfile = useRef()
  const showPopup = useRef(true)

  const openLoginModal = () => {
    APP.openLoginModal()
  }

  const openManage = () => {
    document.location.href = '/manage'
  }

  const openPreferencesModal = () => {
    APP.openPreferencesModal()
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
    const initMyProjectsPopup = () => {
      if (showPopup.current) {
        const tooltipTex =
          "<h4 class='header'>Manage your projects</h4>" +
          "<div class='content'>" +
          '<p>Click here, then "My projects" to retrieve and manage all the projects you have created in Matecat.</p>' +
          "<a class='close-popup-teams'>Got it!</a>" +
          '</div>'
        $(dropdownProfile.current)
          .popup({
            on: 'click',
            onHidden: () => removePopup(),
            html: tooltipTex,
            closable: false,
            onCreate: () => onCreatePopup(),
            className: {
              popup: 'ui popup user-menu-tooltip',
            },
          })
          .popup('show')
        showPopup.cuurent = false
      }
    }

    const removePopup = () => {
      $(dropdownProfile.current).popup('destroy')
      CatToolActions.setPopupUserMenuCookie()
      return true
    }

    const onCreatePopup = () => {
      $('.close-popup-teams').on('click', () => {
        $(dropdownProfile.current).popup('hide')
      })
    }
    if ($(dropdownProfile.current).length) {
      $(dropdownProfile.current).dropdown()
    }
    CatToolStore.addListener(
      CatToolConstants.SHOW_PROFILE_MESSAGE_TOOLTIP,
      initMyProjectsPopup,
    )
    return () => {
      CatToolStore.removeListener(
        CatToolConstants.SHOW_PROFILE_MESSAGE_TOOLTIP,
        initMyProjectsPopup,
      )
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
