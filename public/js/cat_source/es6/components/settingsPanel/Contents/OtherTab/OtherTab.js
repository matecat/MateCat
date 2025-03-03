import React from 'react'
import {Team} from './Team'
import {SourceLanguage} from './SourceLanguage'
import {TargetLanguages} from './TargetLanguages'
import {Subject} from './Subject'

export const OtherTab = () => {
  return (
    <div className="other-options-box settings-panel-contentwrapper-tab-background">
      <Team />
      <SourceLanguage />
      <TargetLanguages />
      <Subject />
    </div>
  )
}
OtherTab.propTypes = {}
