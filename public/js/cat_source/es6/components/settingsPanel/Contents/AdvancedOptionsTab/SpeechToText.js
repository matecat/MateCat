import React, {useState} from 'react'
import Switch from '../../../common/Switch'
import ModalsActions from '../../../../actions/ModalsActions'
import Speech2TextFeature from '../../../../utils/speech2text'

import AlertModal from '../../../modals/AlertModal'
import PropTypes from 'prop-types'
import {toggleSpeechToText} from '../../../../api/toggleSpeechToText'

export const SpeechToText = ({setSpeechToTextActive = () => {}}) => {
  const isCattool = config.is_cattool
  const [dictationOption, setDictationOption] = useState(() => {
    if (isCattool) {
      return Speech2TextFeature.enabled()
    } else {
      return config.defaults.speech2text
    }
  })
  // const disabled = !('webkitSpeechRecognition' in window)
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
  const onChange = (selected) => {
    if (selected) {
      setDictationOption(true)
      toggleSpeechToText({enabled: true}).then(() => {
        setSpeechToTextActive(true)
        isCattool && Speech2TextFeature.enable()
        Speech2TextFeature.init()
        Speech2TextFeature.loadRecognition()
      })
    } else {
      setDictationOption(false)
      toggleSpeechToText({enabled: false}).then(() => {
        setSpeechToTextActive(false)
        isCattool && Speech2TextFeature.disable()
      })
    }
  }
  return (
    <div className="options-box s2t-box">
      <h3>Dictation</h3>
      <p>
        <span className="option-s2t-box-chrome-label">
          Available on Chrome.{' '}
        </span>
        Improved accessibility thanks to a speech-to-text component to dictate
        your translations instead of typing them.
      </p>
      <Switch
        active={dictationOption}
        onChange={onChange}
        disabled={disabled}
        onClick={clickOnDisabled}
      />
    </div>
  )
}

SpeechToText.propTypes = {
  setSpeechToTextActive: PropTypes.func,
}