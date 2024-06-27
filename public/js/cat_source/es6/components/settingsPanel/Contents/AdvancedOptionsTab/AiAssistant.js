import React, {useState} from 'react'
import Switch from '../../../common/Switch'
import SegmentUtils from '../../../../utils/segmentUtils'
import CommonUtils from '../../../../utils/commonUtils'

export const AiAssistant = () => {
  const [active, setActive] = useState(SegmentUtils.isAiAssistantAuto())
  const onChange = (selected) => {
    setActive(selected)
    //Track Event
    const message = {
      user:
        config.isLoggedIn && APP.USER.STORE.user
          ? APP.USER.STORE.user.uid
          : undefined,
      page: location.href,
      onHighlight: selected,
    }
    CommonUtils.dispatchTrackingEvents('AiAssistantSwitch', message)

    SegmentUtils.setAiAssistantOptionValue(selected)
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
          active={active}
          testId="switch-ai-assistant"
        />
      </div>
    </div>
  )
}
