import React, {useContext} from 'react'
import {Button, BUTTON_SIZE} from '../common/Button/Button'
import {OnBoardingContext, socialUrls} from './OnBoarding'
const SocialButtons = () => {
  const {socialLogin} = useContext(OnBoardingContext)

  return (
    <div className="login-social-buttons">
      <Button
        size={BUTTON_SIZE.ICON_STANDARD}
        onClick={() => socialLogin(socialUrls.googleUrl)}
      >
        <img alt="Google login" src="/public/img/icons/social/google.svg" />
      </Button>
      <Button
        size={BUTTON_SIZE.ICON_STANDARD}
        onClick={() => socialLogin(socialUrls.linkedIn)}
      >
        <img alt="Google login" src="/public/img/icons/social/linkedIn.svg" />
      </Button>
      <Button
        size={BUTTON_SIZE.ICON_STANDARD}
        onClick={() => socialLogin(socialUrls.microsoft)}
      >
        <img alt="Google login" src="/public/img/icons/social/microsoft.svg" />
      </Button>
      <Button
        size={BUTTON_SIZE.ICON_STANDARD}
        onClick={() => socialLogin(socialUrls.github)}
      >
        <img alt="Google login" src="/public/img/icons/social/github.svg" />
      </Button>
      <Button
        size={BUTTON_SIZE.ICON_STANDARD}
        onClick={() => socialLogin(socialUrls.meta)}
      >
        <img alt="Google login" src="/public/img/icons/social/meta.svg" />
      </Button>
    </div>
  )
}

export default SocialButtons
