import React, {useContext, useEffect, useRef} from 'react'
import {CharacterCounterRules} from '../OtherTab/CharacterCounterRules'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {updateJobMetadata} from '../../../../api/updateJobMetadata'
import {Tagging} from '../OtherTab/Tagging'
import CatToolActions from '../../../../actions/CatToolActions'
import SegmentActions from '../../../../actions/SegmentActions'

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
          currentProjectTemplate?.characterCounterMode ||
        previousCurrentProjectTemplate.current.subfilteringHandlers !==
          currentProjectTemplate?.subfilteringHandlers)
    ) {
      updateJobMetadata({
        characterCounterCountTags:
          currentProjectTemplate.characterCounterCountTags,
        characterCounterMode: currentProjectTemplate.characterCounterMode,
        subfilteringHandlers: currentProjectTemplate.subfilteringHandlers,
      })
      if (
        previousCurrentProjectTemplate.current.subfilteringHandlers !==
        currentProjectTemplate?.subfilteringHandlers
      ) {
        SegmentActions.removeAllSegments()
        CatToolActions.onRender()
      }
    }

    previousCurrentProjectTemplate.current = {
      characterCounterCountTags:
        currentProjectTemplate?.characterCounterCountTags,
      characterCounterMode: currentProjectTemplate?.characterCounterMode,
      subfilteringHandlers: currentProjectTemplate?.subfilteringHandlers,
    }
  }, [
    currentProjectTemplate?.characterCounterCountTags,
    currentProjectTemplate?.characterCounterMode,
    currentProjectTemplate?.subfilteringHandlers,
    tmKeys,
  ])

  return (
    <div className="editor-settings-options-box settings-panel-contentwrapper-tab-background">
      <div className="settings-panel-contentwrapper-tab-subcategories">
        <h2>Character counter settings</h2>
        <CharacterCounterRules />
        <Tagging />
      </div>
    </div>
  )
}
