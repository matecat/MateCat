import React, {useContext, useState} from 'react'
import Switch from '../../../common/Switch'
import {SpeechToText} from './SpeechToText'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import PropTypes from 'prop-types'
import {SettingsPanel} from '../../SettingsPanel'
import {GuessTag} from './GuessTag'

export const AdvancedOptionsTab = () => {
  const {setSpeechToTextActive, setGuessTagActive} =
    useContext(SettingsPanelContext)
  return (
    <div className="advanced-options-box">
      {/*<h2>Advanced Options</h2>*/}

      <SpeechToText setSpeechToTextActive={setSpeechToTextActive} />

      {config.show_tag_projection && (
        <GuessTag setGuessTagActive={setGuessTagActive} />
      )}

      {/*  Lexiqa
    TODO: check lexiqa licence active
  */}
      <div className="options-box qa-box">
        <h3>
          QA by <img src="/public/img/lexiqa-new-2.png" />
        </h3>
        <p>
          <span className="option-qa-box-languages">
            Not available for:
            <span className="option-notsupported-languages"></span>.
            <br />
          </span>
          Linguistic QA with automated checks for punctuation, numerals, links,
          symbols, etc.
          <span className="tooltip-lexiqa">Supported languages</span>
        </p>
        {/*<p >
        Request your license key at <a href="https://www.lexiqa.net">https://www.lexiqa.net</a>
      </p>*/}
        <Switch />
      </div>

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

      {/*DQF
    TODO:
     - check if enabled dqf_enabled
     - show onli for logged user?
     - check parameters in login modal
  */}
      <div className="options-box dqf-box">
        <h3>DQF </h3>
        <p>
          Enable this option to use DQF in Matecat.
          <span className="dqf-settings">Open Settings</span>
        </p>
        {/*<p tal:condition="php: empty(logged_user)">*/}
        {/*  Login and set your DQF credentials to use DQF in Matecat.*/}
        {/*</p>*/}

        <Switch />
      </div>
    </div>
  )
}
AdvancedOptionsTab.propTypes = {}
