import React, {useContext} from 'react'
import {Button, BUTTON_SIZE, BUTTON_TYPE, BUTTON_MODE} from '../common/Button/Button'
import {OnBoardingContext, socialUrls} from './OnBoardingContext'
import Google from '../../../img/icons/social/Google'
import LinkedIn from '../../../img/icons/social/LinkedIn'
import Github from '../../../img/icons/social/Github'
import Microsoft from '../../../img/icons/social/Microsoft'
import Meta from '../../../img/icons/social/Meta'
const SocialButtons = () => {
  const {socialLogin} = useContext(OnBoardingContext)

  return (
    <div className="login-social-buttons">
      {socialUrls.googleUrl && (
        <Button
          size={BUTTON_SIZE.ICON_STANDARD}
          type={BUTTON_TYPE.ICON}
          mode={BUTTON_MODE.OUTLINE}
          onClick={() => socialLogin(socialUrls.googleUrl)}
        >
          <Google size={24} />
        </Button>
      )}
      {socialUrls.linkedIn && (
        <Button
          size={BUTTON_SIZE.ICON_STANDARD}
          type={BUTTON_TYPE.ICON}
          mode={BUTTON_MODE.OUTLINE}
          onClick={() => socialLogin(socialUrls.linkedIn)}
        >
          <LinkedIn size={24} />
        </Button>
      )}
      {socialUrls.microsoft && (
        <Button
          size={BUTTON_SIZE.ICON_STANDARD}
          type={BUTTON_TYPE.ICON}
          mode={BUTTON_MODE.OUTLINE}
          onClick={() => socialLogin(socialUrls.microsoft)}
        >
          <Microsoft size={24} />
        </Button>
      )}
      {socialUrls.github && (
        <Button
          size={BUTTON_SIZE.ICON_STANDARD}
          type={BUTTON_TYPE.ICON}
          mode={BUTTON_MODE.OUTLINE}
          onClick={() => socialLogin(socialUrls.github)}
        >
          <Github size={24} />
        </Button>
      )}
      {socialUrls.meta && (
        <Button
          size={BUTTON_SIZE.ICON_STANDARD}
          type={BUTTON_TYPE.ICON}
          mode={BUTTON_MODE.OUTLINE}
          onClick={() => socialLogin(socialUrls.meta)}
        >
          <Meta size={24} />
        </Button>
      )}
    </div>
  )
}

export default SocialButtons
