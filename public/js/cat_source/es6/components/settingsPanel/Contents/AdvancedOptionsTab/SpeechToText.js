import React, {useState} from 'react'
import Switch from '../../../common/Switch'
import ModalsActions from '../../../../actions/ModalsActions'
import Speech2TextFeature from '../../../../utils/speech2text'

import AlertModal from '../../../modals/AlertModal'
import PropTypes from 'prop-types'

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
      isCattool && Speech2TextFeature.enable()
      setDictationOption(true)
      setSpeechToTextActive(true)
    } else {
      isCattool && Speech2TextFeature.disable()
      setDictationOption(false)
      setSpeechToTextActive(false)
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
