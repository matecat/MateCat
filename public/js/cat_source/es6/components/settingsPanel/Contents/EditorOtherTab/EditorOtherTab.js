import React, {useContext, useEffect, useRef} from 'react'
import {CharacterCounterRules} from '../OtherTab/CharacterCounterRules'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {updateJobKeys} from '../../../../api/updateJobKeys'
import CatToolActions from '../../../../actions/CatToolActions'
import {getTmDataStructureToSendServer} from '../TranslationMemoryGlossaryTab/TranslationMemoryGlossaryTab'

export const EditorOtherTab = () => {
  const {currentProjectTemplate, tmKeys} = useContext(SettingsPanelContext)

  const previousCurrentProjectTemplate = useRef()

  useEffect(() => {
    if (
      config.is_cattool &&
      typeof previousCurrentProjectTemplate.current !== 'undefined' &&
      (previousCurrentProjectTemplate.current.characterCounterCountTags !==
        currentProjectTemplate?.characterCounterCountTags ||
        previousCurrentProjectTemplate.current.characterCounterMode !==
          currentProjectTemplate?.characterCounterMode)
    ) {
      updateJobKeys({
        dataTm: getTmDataStructureToSendServer({tmKeys}),
        characterCounterCountTags:
          currentProjectTemplate.characterCounterCountTags,
        characterCounterMode: currentProjectTemplate.characterCounterMode,
      }).then(() => CatToolActions.onTMKeysChangeStatus())
    }

    previousCurrentProjectTemplate.current = {
      characterCounterCountTags:
        currentProjectTemplate?.characterCounterCountTags,
      characterCounterMode: currentProjectTemplate?.characterCounterMode,
    }
  }, [
    currentProjectTemplate?.characterCounterCountTags,
    currentProjectTemplate?.characterCounterMode,
    tmKeys,
  ])

  return (
    <div className="editor-settings-options-box settings-panel-contentwrapper-tab-background">
      <div className="settings-panel-contentwrapper-tab-subcategories">
        <h2>Character counter</h2>
        <CharacterCounterRules />
      </div>
    </div>
  )
}
