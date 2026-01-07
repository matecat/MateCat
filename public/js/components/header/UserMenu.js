import React, {useContext} from 'react'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'
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
import ModalsActions from '../../actions/ModalsActions'

export const UserMenu = () => {
  const {isUserLogged, userInfo, logout} = useContext(ApplicationWrapperContext)

  const loggedRender = () => {
    const {metadata, user} = userInfo
    const avatarImg = metadata
      ? (metadata[`${metadata.oauth_provider}_picture`] ?? null)
      : null

    const openPreferencesModal = () => ModalsActions.openPreferencesModal()

    const logoutUserFn = () => {
      logout()
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
              src={`${avatarImg}`}
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
            {avatarImg ? (
              <img
                className="user-avatar"
                src={`${avatarImg}`}
                title="Personal settings"
                alt="Profile picture"
              />
            ) : (
              <div className="ui user circular image ui-user-top-image user-avatar">
                {CommonUtils.getUserShortName(userInfo.user)}
              </div>
            )}
            <div className="user-name-and-email">
              <div>{`${user.first_name} ${user.last_name}`}</div>
              <div>{user.email}</div>
            </div>
          </div>
          <hr />
          <ul>
            <li>
              <a className="item" href="/manage">
                My Projects
              </a>
            </li>
            <li>
              <div className="item" onClick={openPreferencesModal}>
                Profile
              </div>
            </li>
            <li>
              <div className="item" onClick={logoutUserFn}>
                Logout
              </div>
            </li>
          </ul>
        </div>
      </Popover>
    )
  }

  return (
    typeof isUserLogged === 'boolean' &&
    (isUserLogged ? (
      loggedRender()
    ) : (
      <div className="header-buttons">
        <Button
          className={'header-button-signin'}
          mode={BUTTON_MODE.OUTLINE}
          onClick={ModalsActions.openLoginModal}
        >
          Sign In
        </Button>
        <Button
          className={'header-button-signup'}
          onClick={ModalsActions.openRegisterModal}
        >
          Sign Up
        </Button>
      </div>
    ))
  )
}
