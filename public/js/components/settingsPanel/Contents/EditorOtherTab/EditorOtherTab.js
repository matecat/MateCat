import React, {useContext, useEffect, useRef} from 'react'
import {CharacterCounterRules} from '../OtherTab/CharacterCounterRules'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {updateJobMetadata} from '../../../../api/updateJobMetadata'
import {Tagging} from '../OtherTab/Tagging'
import {MandatoryIssues} from '../OtherTab/MandatoryIssues'
import CatToolStore from '../../../../stores/CatToolStore'
import CatToolActions from '../../../../actions/CatToolActions'
import CatToolConstants from '../../../../constants/CatToolConstants'

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
          currentProjectTemplate?.subfilteringHandlers ||
        previousCurrentProjectTemplate.current.mandatoryIssues !==
          currentProjectTemplate?.mandatoryIssues)
    ) {
      updateJobMetadata({
        characterCounterCountTags:
          currentProjectTemplate.characterCounterCountTags,
        characterCounterMode: currentProjectTemplate.characterCounterMode,
        subfilteringHandlers: currentProjectTemplate.subfilteringHandlers,
        mandatoryIssues: currentProjectTemplate.mandatoryIssues,
      })
        .then(() => {
          // Fall back to an empty shape rather than bailing out: the initial metadata request
          // may still be in flight, and dropping the update here would leave the editor acting
          // on the pre-change settings until a reload.
          const jobMetadata = CatToolStore.getJobMetadata() ?? {
            job: {},
            project: {},
            files: [],
          }

          const updatedJobMetadata = {
            ...jobMetadata,
            job: {
              ...jobMetadata.job,
              character_counter_count_tags:
                currentProjectTemplate.characterCounterCountTags,
              character_counter_mode:
                currentProjectTemplate.characterCounterMode,
              subfiltering_handlers:
                currentProjectTemplate.subfilteringHandlers,
              mandatory_issues: currentProjectTemplate.mandatoryIssues,
            },
          }
          CatToolStore.setJobMetadata(updatedJobMetadata)
          CatToolStore.emitChange(CatToolConstants.GET_JOB_METADATA, {
            jobMetadata: updatedJobMetadata,
          })
        })
        .catch(() => {
          CatToolActions.addNotification({
            title: 'Error saving settings',
            type: 'error',
            text: 'Your editor settings could not be saved. Please retry!',
            position: 'br',
          })
        })
    }

    previousCurrentProjectTemplate.current = {
      characterCounterCountTags:
        currentProjectTemplate?.characterCounterCountTags,
      characterCounterMode: currentProjectTemplate?.characterCounterMode,
      subfilteringHandlers: currentProjectTemplate?.subfilteringHandlers,
      mandatoryIssues: currentProjectTemplate?.mandatoryIssues,
    }
  }, [
    currentProjectTemplate?.characterCounterCountTags,
    currentProjectTemplate?.characterCounterMode,
    currentProjectTemplate?.subfilteringHandlers,
    currentProjectTemplate?.mandatoryIssues,
    tmKeys,
  ])

  return (
    <div className="editor-settings-options-box settings-panel-contentwrapper-tab-background">
      <div className="settings-panel-contentwrapper-tab-subcategories">
        <h2>General settings</h2>
        <Tagging />
        <MandatoryIssues />
      </div>
      <div className="settings-panel-contentwrapper-tab-subcategories">
        <h2>Character counter settings</h2>
        <CharacterCounterRules />
      </div>
    </div>
  )
}
