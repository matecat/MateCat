import React, {useContext, useEffect, useRef} from 'react'
import {MachineTranslationTab} from '../settingsPanel/Contents/MachineTranslationTab'
import {SettingsPanelContext} from '../settingsPanel/SettingsPanelContext'
import CatToolStore from '../../stores/CatToolStore'
import {updateJobMetadata} from '../../api/updateJobMetadata'
import CatToolConstants from '../../constants/CatToolConstants'

export const EditorMachineTranslationTab = (props) => {
  const {currentProjectTemplate, modifyingCurrentTemplate} =
    useContext(SettingsPanelContext)

  const previousCurrentProjectTemplate = useRef()
  const wasCheckIntentoDoubleKey = useRef()

  // workaround intento double key select (intento_provider, intento_routing)
  useEffect(() => {
    if (
      wasCheckIntentoDoubleKey.current ||
      typeof currentProjectTemplate.mt?.extra === 'undefined'
    )
      return

    const jobMetadata = CatToolStore.getJobMetadata()
    if (!jobMetadata) return

    modifyingCurrentTemplate((prevTemplate) => {
      const mtExtraFiltered = Object.entries(prevTemplate.mt.extra)
        .filter(
          ([key]) => key !== 'intento_provider' && key !== 'intento_routing',
        )
        .reduce((acc, cur) => ({...acc, [cur[0]]: cur[1]}), {})

      const getIntentoSelectID = () => {
        if (
          typeof jobMetadata.job.mt_extra?.intento_provider === 'string' &&
          jobMetadata.job.mt_extra?.intento_provider !== ''
        ) {
          return {intento_provider: jobMetadata.job.mt_extra.intento_provider}
        } else if (
          typeof jobMetadata.job.mt_extra?.intento_routing === 'string' &&
          jobMetadata.job.mt_extra?.intento_routing !== ''
        ) {
          return {intento_routing: jobMetadata.job.mt_extra.intento_routing}
        } else if (
          typeof jobMetadata.project.mt_extra?.intento_provider === 'string' &&
          jobMetadata.project.mt_extra?.intento_provider !== ''
        ) {
          return {
            intento_provider: jobMetadata.project.mt_extra.intento_provider,
          }
        } else if (
          typeof jobMetadata.project.mt_extra?.intento_routing === 'string' &&
          jobMetadata.project.mt_extra?.intento_routing !== ''
        ) {
          return {intento_routing: jobMetadata.project.mt_extra.intento_routing}
        }
      }

      return {
        ...prevTemplate,
        mt: {
          ...prevTemplate.mt,
          extra: {
            ...mtExtraFiltered,
            ...getIntentoSelectID(),
          },
        },
      }
    })

    wasCheckIntentoDoubleKey.current = true
  }, [currentProjectTemplate])

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
          // workaround intento double key select (intento_provider, intento_routing)
          ...(Object.keys(mtExtraCurrentTemplate).some(
            (value) =>
              value === 'intento_provider' || value === 'intento_routing',
          ) && {intento_provider: undefined, intento_routing: undefined}),
          //
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
      mtExtra: currentProjectTemplate.mt.extra,
    }
  }, [
    currentProjectTemplate?.mtQualityValueInEditor,
    currentProjectTemplate?.mt?.extra,
  ])

  return <MachineTranslationTab {...props} />
}
