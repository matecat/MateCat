import React, {useContext, useState} from 'react'
import Switch from '../../../common/Switch'
import CommonUtils from '../../../../utils/commonUtils'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper/ApplicationWrapperContext'
import UserStore from '../../../../stores/UserStore'

const METADATA_KEY = 'ai_assistant'

export const AiAssistant = () => {
  const {userInfo, setUserMetadataKey} = useContext(ApplicationWrapperContext)

  const [isActive, setIsActive] = useState(
    userInfo.metadata[METADATA_KEY] === 1,
  )

  const onChange = (isActive) => {
    setIsActive(isActive)

    setUserMetadataKey(METADATA_KEY, isActive ? 1 : 0)

    //Track Event
    const userInfo = UserStore.getUser()
    const message = {
      user: userInfo.user.uid,
      page: location.href,
      onHighlight: isActive,
    }
    CommonUtils.dispatchTrackingEvents('AiAssistantSwitch', message)
  }
  return (
    <div className="options-box ai-assistant">
      <div className="option-description">
        <h3>Automatic AI assistant</h3>
        <p>
          By default, a button to activate the AI assistant appears under the
          source segment when you highlight a word. If you set this option to
          active, the AI assistant will activate automatically when a word is
          highlighted. The AI assistant can be activated for a maximum or 3
          words, 6 Chinese characters or 10 Japanese characters.
        </p>
      </div>
      <div className="options-box-value">
        <Switch
          onChange={onChange}
          active={isActive}
          testId="switch-ai-assistant"
        />
      </div>
    </div>
  )
}
