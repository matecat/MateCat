import React, {useContext} from 'react'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper'
import {
  POPOVER_ALIGN,
  POPOVER_VERTICAL_ALIGN,
  Popover,
} from '../common/Popover/Popover'
import {
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
  Button,
} from '../common/Button/Button'
import CommonUtils from '../../utils/commonUtils'
import {logoutUser} from '../../api/logoutUser'
import IconUserLogout from '../icons/IconUserLogout'

export const UserMenu = () => {
  const {isUserLogged, userInfo} = useContext(ApplicationWrapperContext)

  const openLoginModal = () => APP.openLoginModal()

  const loggedRender = () => {
    const {metadata, user} = userInfo
    const avatarImg = metadata[`${metadata.oauth_provider}_picture`]

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

    return (
      <Popover
        toggleButtonProps={{
          type: BUTTON_TYPE.PRIMARY,
          mode: BUTTON_MODE.GHOST,
          size: BUTTON_SIZE.ICON_STANDARD,
          children: avatarImg ? (
            <img
              className="user-menu-popover-avatar"
              src={`${avatarImg}?sz=80`}
              title="Personal settings"
              alt="Profile picture"
            />
          ) : (
            <div
              className="ui user circular image ui-user-top-image"
              data-testid="user-menu-metadata"
              title="Personal settings"
            >
              {CommonUtils.getUserShortName(userInfo.user)}
            </div>
          ),
        }}
        align={POPOVER_ALIGN.RIGHT}
        verticalAlign={POPOVER_VERTICAL_ALIGN.BOTTOM}
      >
        <div className="user-menu-popover-content">
          <div className="user-info">
            <img
              className="user-avatar"
              src={`${avatarImg}?sz=80`}
              title="Personal settings"
              alt="Profile picture"
            />
            <div className="user-name-and-email">
              <div>{`${user.first_name} ${user.last_name}`}</div>
              <div>{user.email}</div>
            </div>
          </div>
          <hr />
          <ul>
            <li>
              <Button
                mode={BUTTON_MODE.LINK}
                size={BUTTON_SIZE.LINK_MEDIUM}
                onClick={openManage}
              >
                My Projects
              </Button>
            </li>
            <li>
              <Button
                mode={BUTTON_MODE.LINK}
                size={BUTTON_SIZE.LINK_MEDIUM}
                onClick={openPreferencesModal}
              >
                Profile
              </Button>
            </li>
            <li>
              <Button
                mode={BUTTON_MODE.LINK}
                size={BUTTON_SIZE.LINK_MEDIUM}
                onClick={logoutUserFn}
              >
                Logout
              </Button>
            </li>
          </ul>
        </div>
      </Popover>
    )
  }

  return isUserLogged ? (
    loggedRender()
  ) : (
    <div className="ui user-nolog label" onClick={openLoginModal} title="Login">
      <IconUserLogout width={40} height={40} color={'#fff'} />
    </div>
  )
}
