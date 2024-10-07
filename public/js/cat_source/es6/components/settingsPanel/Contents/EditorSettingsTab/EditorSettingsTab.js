import React from 'react'
import {SpeechToText} from './SpeechToText'
import {SpacePlaceholder} from './SpacePlaceholder'

export const EditorSettingsTab = () => {
  return (
    <div className="editor-settings-options-box settings-panel-contentwrapper-tab-background">
      <SpeechToText />
      <SpacePlaceholder />
    </div>
  )
}
