import React, {useContext} from 'react'
import {SpeechToText} from './SpeechToText'
import {SpacePlaceholder} from './SpacePlaceholder'
import {GuessTag} from './GuessTag'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {Lexiqa} from './Lexiqa'
import {CharacterCounter} from './CharacterCounter'
import {AiAssistant} from './AiAssistant'
import {CrossLanguagesMatches} from './CrossLanguagesMatches'

export const EditorSettingsTab = () => {
  const {sourceLang, targetLangs} = useContext(SettingsPanelContext)

  return (
    <div className="editor-settings-options-box settings-panel-contentwrapper-tab-background">
      <SpeechToText />
      <SpacePlaceholder />
      {config.show_tag_projection && (
        <GuessTag {...{sourceLang, targetLangs}} />
      )}
      <Lexiqa {...{sourceLang, targetLangs}} />
      <CharacterCounter />
      {config.isOpenAiEnabled && <AiAssistant />}
      <CrossLanguagesMatches />
    </div>
  )
}
