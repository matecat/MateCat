import React, {useContext, useEffect, useRef} from 'react'
import {MachineTranslationTab} from '../settingsPanel/Contents/MachineTranslationTab'
import {SettingsPanelContext} from '../settingsPanel/SettingsPanelContext'
import CatToolStore from '../../stores/CatToolStore'
import {updateJobMetadata} from '../../api/updateJobMetadata'
import CatToolConstants from '../../constants/CatToolConstants'

export const EditorMachineTranslationTab = (props) => {
  const {currentProjectTemplate} = useContext(SettingsPanelContext)

  const previousCurrentProjectTemplate = useRef()

  useEffect(() => {
    const propsExtra = [
      'deepl_formality',
      'deepl_id_glossary',
      'deepl_engine_type',
      'lara_style',
      'lara_style_guideline_id',
      'lara_glossaries',
      'mmt_glossaries',
      'mmt_ignore_glossary_case',
      'intento_provider',
      'intento_routing',
    ]

    const mtExtraCurrentTemplate = propsExtra.reduce((acc, cur) => {
      const value = currentProjectTemplate.mt?.extra[cur]
      if (typeof value !== 'undefined') return {...acc, [cur]: value}
      return acc
    }, {})
    const wasExtraPropChanges = propsExtra.some(
      (prop) =>
        typeof currentProjectTemplate.mt?.extra[prop] !== 'undefined' &&
        typeof previousCurrentProjectTemplate.current?.mtExtra !==
          'undefined' &&
        currentProjectTemplate.mt?.extra[prop] !==
          previousCurrentProjectTemplate.current?.mtExtra[prop],
    )
    if (
      config.is_cattool &&
      typeof previousCurrentProjectTemplate.current !== 'undefined' &&
      (previousCurrentProjectTemplate.current.mtQualityValueInEditor !==
        currentProjectTemplate?.mtQualityValueInEditor ||
        wasExtraPropChanges)
    ) {
      updateJobMetadata({
        mtQualityValueInEditor: currentProjectTemplate.mtQualityValueInEditor,
        mtExtra: {
          ...(Object.keys(mtExtraCurrentTemplate).some(
            (value) =>
              value === 'intento_provider' || value === 'intento_routing',
          ) && {intento_provider: undefined, intento_routing: undefined}),
          ...mtExtraCurrentTemplate,
        },
      }).then(() => {
        const jobMetadata = CatToolStore.getJobMetadata()
        if (!jobMetadata) return

        const updatedJobMetadata = {
          ...jobMetadata,
          job: {
            ...jobMetadata.job,
            mt_quality_value_in_editor:
              currentProjectTemplate.mtQualityValueInEditor,
            mt_extra: mtExtraCurrentTemplate,
          },
        }
        CatToolStore.setJobMetadata(updatedJobMetadata)
        CatToolStore.emitChange(CatToolConstants.GET_JOB_METADATA, {
          jobMetadata: CatToolStore.getJobMetadata(),
        })
      })
    }

    previousCurrentProjectTemplate.current = {
      mtQualityValueInEditor: currentProjectTemplate?.mtQualityValueInEditor,
      mtExtra: mtExtraCurrentTemplate,
    }
  }, [
    currentProjectTemplate?.mtQualityValueInEditor,
    currentProjectTemplate?.mt?.extra,
  ])

  return <MachineTranslationTab {...props} />
}
