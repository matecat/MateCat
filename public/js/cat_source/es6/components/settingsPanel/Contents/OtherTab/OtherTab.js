import React from 'react'
import {Team} from './Team'
import {SourceLanguage} from './SourceLanguage'
import {TargetLanguages} from './TargetLanguages'
import {Subject} from './Subject'

export const OtherTab = () => {
  return (
    <div className="other-options-box settings-panel-contentwrapper-tab-background">
      {/* {config.is_cattool && <SpacePlaceholder />}

      {config.is_cattool && <CharacterCounter />}

      {config.is_cattool && config.isOpenAiEnabled && <AiAssistant />} */}

      <Team />
      <SourceLanguage />
      <TargetLanguages />
      <Subject />
    </div>
  )
}
OtherTab.propTypes = {}
