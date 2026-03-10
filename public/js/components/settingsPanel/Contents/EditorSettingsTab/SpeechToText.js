import React, {useContext, useState} from 'react'
import Switch from '../../../common/Switch'
import ModalsActions from '../../../../actions/ModalsActions'
import Speech2TextFeature from '../../../../utils/speech2text'

import AlertModal from '../../../modals/AlertModal'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper/ApplicationWrapperContext'

const METADATA_KEY = 'dictation'

export const SpeechToText = () => {
  const {userInfo, setUserMetadataKey} = useContext(ApplicationWrapperContext)

  const [isActive, setIsActive] = useState(
    userInfo.metadata[METADATA_KEY] === 1,
  )

  const disabled = !('webkitSpeechRecognition' in window)

  const clickOnDisabled = () => {
    if (disabled) {
      ModalsActions.showModalComponent(
        AlertModal,
        {
          text: 'This options is only available on your browser.',
          buttonText: 'Continue',
        },
        'Option not available',
      )
    }
  }
  const onChange = (isActive) => {
    setIsActive(isActive)
    setUserMetadataKey(METADATA_KEY, isActive ? 1 : 0).then(() => {
      if (isActive) {
        Speech2TextFeature.enable()
        Speech2TextFeature.init()
        Speech2TextFeature.loadRecognition()
      } else {
        Speech2TextFeature.disable()
      }
    })
  }
  return (
    <div className="options-box s2t-box">
      <div className="option-description">
        <h3>Dictation</h3>
        <p>
          <span className="option-s2t-box-chrome-label">
            Available on Chrome.{' '}
          </span>
          Improved accessibility thanks to a speech-to-text component to dictate
          your translations instead of typing them.
        </p>
      </div>
      <div className="options-box-value">
        <Switch
          active={isActive}
          onChange={onChange}
          disabled={disabled}
          onClick={clickOnDisabled}
          testId="switch-speechtotext"
        />
      </div>
    </div>
  )
}
