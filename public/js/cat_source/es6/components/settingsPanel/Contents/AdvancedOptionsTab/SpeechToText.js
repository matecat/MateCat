import React, {useEffect, useState} from 'react'
import Switch from '../../../common/Switch'
import ModalsActions from '../../../../actions/ModalsActions'
import Speech2TextFeature from '../../../../utils/speech2text'

import AlertModal from '../../../modals/AlertModal'
import PropTypes from 'prop-types'
import {toggleSpeechToText} from '../../../../api/toggleSpeechToText'

export const SpeechToText = ({
  speechToTextActive,
  setSpeechToTextActive = () => {},
}) => {
  const isCattool = config.is_cattool
  const [dictationOption, setDictationOption] = useState(() => {
    if (isCattool) {
      return Speech2TextFeature.enabled()
    } else {
      return speechToTextActive
    }
  })
  // const disabled = !('webkitSpeechRecognition' in window)
  const disabled = !('webkitSpeechRecognition' in window)

  useEffect(() => {
    setDictationOption(() => {
      if (isCattool) {
        return Speech2TextFeature.enabled()
      } else {
        return speechToTextActive
      }
    })
  }, [speechToTextActive, isCattool])

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
      if (isCattool) {
        toggleSpeechToText({enabled: true}).then(() => {
          setSpeechToTextActive(true)
          Speech2TextFeature.enable()
          Speech2TextFeature.init()
          Speech2TextFeature.loadRecognition()
        })
      } else {
        setSpeechToTextActive(true)
      }
    } else {
      setDictationOption(false)
      if (isCattool) {
        toggleSpeechToText({enabled: false}).then(() => {
          Speech2TextFeature.disable()
        })
      } else {
        setSpeechToTextActive(false)
      }
    }
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
          active={dictationOption}
          onChange={onChange}
          disabled={disabled}
          onClick={clickOnDisabled}
          testId={'switch-speechtotext'}
        />
      </div>
    </div>
  )
}

SpeechToText.propTypes = {
  setSpeechToTextActive: PropTypes.func,
}
