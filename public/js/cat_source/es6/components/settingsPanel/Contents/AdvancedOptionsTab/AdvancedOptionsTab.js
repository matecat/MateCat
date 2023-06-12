import React, {useContext, useState} from 'react'
import PropTypes from 'prop-types'
import Switch from '../../../common/Switch'
import {SpeechToText} from './SpeechToText'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {GuessTag} from './GuessTag'
import {Lexiqa} from './Lexiqa'
import {CrossLanguagesMatches} from './CrossLanguagesMatches'
import {CharacterCounter} from './CharacterCounter'
import {AiAssistant} from './AiAssistant'
import {SegmentationRule} from './SegmentationRule'

export const AdvancedOptionsTab = () => {
  const {
    setSpeechToTextActive,
    guessTagActive,
    setGuessTagActive,
    sourceLang,
    targetLangs,
    lexiqaActive,
    setLexiqaActive,
    multiMatchLangs,
    setMultiMatchLangs,
    segmentationRule,
    setSegmentationRule,
  } = useContext(SettingsPanelContext)
  return (
    <div className="advanced-options-box">
      {/*<h2>Advanced Options</h2>*/}

      <SpeechToText setSpeechToTextActive={setSpeechToTextActive} />

      {config.show_tag_projection && (
        <GuessTag
          setGuessTagActive={setGuessTagActive}
          guessTagActive={guessTagActive}
          sourceLang={sourceLang}
          targetLangs={targetLangs}
        />
      )}

      <Lexiqa
        lexiqaActive={lexiqaActive}
        setLexiqaActive={setLexiqaActive}
        sourceLang={sourceLang}
        targetLangs={targetLangs}
      />

      {config.is_cattool && <CharacterCounter />}

      {config.is_cattool && config.isOpenAiEnabled && <AiAssistant />}

      <CrossLanguagesMatches
        multiMatchLangs={multiMatchLangs}
        setMultiMatchLangs={setMultiMatchLangs}
      />

      <SegmentationRule
        segmentationRule={segmentationRule}
        setSegmentationRule={setSegmentationRule}
      />
    </div>
  )
}
AdvancedOptionsTab.propTypes = {}
