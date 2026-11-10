import React, {useContext} from 'react'
import {SpeechToText} from './SpeechToText'
import {SpacePlaceholder} from './SpacePlaceholder'
import {GuessTag} from './GuessTag'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {Lexiqa} from './Lexiqa'
import {CharacterCounter} from './CharacterCounter'
import {AiAssistant} from './AiAssistant'
import {CrossLanguagesMatches} from './CrossLanguagesMatches'
import {EditorEngine} from './EditorEngine'
import {isNewEditorEnabled} from '../../../editor/matecat/editorEngine'

export const EditorSettingsTab = () => {
  const {sourceLang, targetLangs} = useContext(SettingsPanelContext)

  return (
    <div className="editor-settings-options-box settings-panel-contentwrapper-tab-background">
      {isNewEditorEnabled() && <EditorEngine />}
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
