import React, {useContext} from 'react'
import {Button, BUTTON_SIZE} from '../common/Button/Button'
import {OnBoardingContext, socialUrls} from './OnBoarding'
const SocialButtons = () => {
  const {socialLogin} = useContext(OnBoardingContext)

  return (
    <div className="login-social-buttons">
      {socialUrls.googleUrl && (
        <Button
          size={BUTTON_SIZE.ICON_STANDARD}
          onClick={() => socialLogin(socialUrls.googleUrl)}
        >
          <img alt="Google login" src="/img/icons/social/google.svg" />
        </Button>
      )}
      {socialUrls.linkedIn && (
        <Button
          size={BUTTON_SIZE.ICON_STANDARD}
          onClick={() => socialLogin(socialUrls.linkedIn)}
        >
          <img alt="Google login" src="/img/icons/social/linkedIn.svg" />
        </Button>
      )}
      {socialUrls.microsoft && (
        <Button
          size={BUTTON_SIZE.ICON_STANDARD}
          onClick={() => socialLogin(socialUrls.microsoft)}
        >
          <img alt="Google login" src="/img/icons/social/microsoft.svg" />
        </Button>
      )}
      {socialUrls.github && (
        <Button
          size={BUTTON_SIZE.ICON_STANDARD}
          onClick={() => socialLogin(socialUrls.github)}
        >
          <img alt="Google login" src="/img/icons/social/github.svg" />
        </Button>
      )}
      {socialUrls.meta && (
        <Button
          size={BUTTON_SIZE.ICON_STANDARD}
          onClick={() => socialLogin(socialUrls.meta)}
        >
          <img alt="Google login" src="/img/icons/social/meta.svg" />
        </Button>
      )}
    </div>
  )
}

export default SocialButtons
