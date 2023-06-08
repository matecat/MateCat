import React, {useContext, useState} from 'react'
import PropTypes from 'prop-types'
import Switch from '../../../common/Switch'
import {SpeechToText} from './SpeechToText'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {GuessTag} from './GuessTag'
import {Lexiqa} from './Lexiqa'

export const AdvancedOptionsTab = () => {
  const {
    setSpeechToTextActive,
    guessTagActive,
    setGuessTagActive,
    sourceLang,
    targetLangs,
    lexiqaActive,
    setLexiqaActive,
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

      {config.is_cattool && (
        <div className="options-box charscounter">
          <h3>Character counter</h3>
          <p>
            Enabling this option makes a counter appear that counts the number
            of characters in the target section of each segment.
          </p>
          <Switch />
        </div>
      )}

      {/*TODO: Select con le linge*/}
      <div className="options-box multi-match">
        <h3>Cross-language Matches</h3>
        <p>
          Get translation suggestions in other target languages you know as
          reference.
        </p>
        <select
          name="multi-match-1"
          id="multi-match-1"
          title="Primary language suggestion"
        >
          <option value="">Primary language suggestion</option>
          {/*<option tal:repeat="lang languages_array_obj" tal:content="lang/name" tal:attributes="value lang/code"/>*/}
        </select>
        <select
          name="multi-match-2"
          id="multi-match-2"
          disabled={true}
          title="Secondary language suggestion"
        >
          <option value="">Secondary language suggestion</option>
          {/*<option tal:repeat="lang languages_array_obj" tal:content="lang/name" tal:attributes="value lang/code">Patent
        </option>*/}
        </select>
      </div>

      {/* Segmentation Rule
      TODO: disabled in cattool + Select
    */}
      <div className="options-box seg_rule">
        <h3>Segmentation Rules</h3>
        <p>
          Select how sentences are split according to specific types of content.
        </p>
        <select name="segm_rule" id="segm_rule">
          <option value="">General</option>
          <option value="patent">Patent</option>
          <option value="paragraph">Paragraph (beta)</option>
        </select>
      </div>
    </div>
  )
}
AdvancedOptionsTab.propTypes = {}
