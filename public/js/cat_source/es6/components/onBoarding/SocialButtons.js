import React from 'react'
import {Button, BUTTON_SIZE} from '../common/Button/Button'
const SocialButtons = () => {
  return (
    <div className="login-social-buttons">
      <Button size={BUTTON_SIZE.ICON_STANDARD}>
        <img alt="Google login" src="/public/img/icons/social/google.svg" />
      </Button>
      <Button size={BUTTON_SIZE.ICON_STANDARD}>
        <img alt="Google login" src="/public/img/icons/social/linkedIn.svg" />
      </Button>
      <Button size={BUTTON_SIZE.ICON_STANDARD}>
        <img alt="Google login" src="/public/img/icons/social/microsoft.svg" />
      </Button>
      <Button size={BUTTON_SIZE.ICON_STANDARD}>
        <img alt="Google login" src="/public/img/icons/social/github.svg" />
      </Button>
      <Button size={BUTTON_SIZE.ICON_STANDARD}>
        <img alt="Google login" src="/public/img/icons/social/meta.svg" />
      </Button>
    </div>
  )
}

export default SocialButtons
