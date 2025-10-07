import React from 'react'
import {Team} from './Team'
import {SourceLanguage} from './SourceLanguage'
import {TargetLanguages} from './TargetLanguages'
import {Subject} from './Subject'
import {CharacterCounterRules} from './CharacterCounterRules'
import {Tagging} from './Tagging'

export const OtherTab = () => {
  return (
    <div className="other-options-box settings-panel-contentwrapper-tab-background">
      <div className="settings-panel-contentwrapper-tab-subcategories">
        <h2>General settings</h2>
        <Team />
        <SourceLanguage />
        <TargetLanguages />
        <Subject />
        <Tagging />
      </div>
      <div className="settings-panel-contentwrapper-tab-subcategories">
        <h2>Character counter settings</h2>
        <CharacterCounterRules />
      </div>
    </div>
  )
}
OtherTab.propTypes = {}
