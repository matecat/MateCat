import React, {useContext, useEffect, useRef} from 'react'
import {CharacterCounterRules} from '../OtherTab/CharacterCounterRules'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {updateJobKeys} from '../../../../api/updateJobKeys'
import CatToolActions from '../../../../actions/CatToolActions'

export const EditorOtherTab = () => {
  const {currentProjectTemplate} = useContext(SettingsPanelContext)

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
  ])

  return (
    <div className="editor-settings-options-box settings-panel-contentwrapper-tab-background">
      <CharacterCounterRules />
    </div>
  )
}
