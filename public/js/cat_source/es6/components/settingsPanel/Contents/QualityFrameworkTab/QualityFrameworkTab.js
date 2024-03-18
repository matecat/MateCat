import React, {useEffect, useRef, useContext, createContext} from 'react'
import {SubTemplates} from '../SubTemplates'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {getQualityFrameworkTemplates} from '../../../../api/getQualityFrameworkTemplates/getQualityFrameworkTemplates'
import {EptThreshold} from './EptThreshold'
import {createQualityFrameworkTemplate} from '../../../../api/createQualityFrameworkTemplate/createQualityFrameworkTemplate'
import {updateQualityFrameworkTemplate} from '../../../../api/updateQualityFrameworkTemplate/updateQualityFrameworkTemplate'
import {deleteQualityFrameworkTemplate} from '../../../../api/deleteQualityFrameworkTemplate/deleteQualityFrameworkTemplate'
import {CategoriesSeveritiesTable} from './CategoriesSeveritiesTable'

export const QF_SCHEMA_KEYS = {
  id: 'id',
  uid: 'uid',
  name: 'label',
  version: 'version',
  categories: 'categories',
  passfail: 'passfail',
}

const getFilteredSchemaCreateUpdate = (template) => {
  const {id, uid, isTemporary, isSelected, ...filtered} = template // eslint-disable-line
  return filtered
}

export const QualityFrameworkTabContext = createContext({})

export const QualityFrameworkTab = () => {
  const {
    currentProjectTemplate,
    modifyingCurrentTemplate: modifyingCurrentProjectTemplate,
    qualityFrameworkTemplates,
  } = useContext(SettingsPanelContext)

  const {templates, setTemplates, currentTemplate, modifyingCurrentTemplate} =
    qualityFrameworkTemplates

  const currentTemplateId = currentTemplate?.id
  const currentProjectTemplateQaId = currentProjectTemplate.qaModelTemplateId
  const prevCurrentProjectTemplateQaId = useRef()

  // retrieve QF templates
  useEffect(() => {
    if (templates.length) return

    let cleanup = false

    if (config.isLoggedIn === 1 && !config.is_cattool) {
      getQualityFrameworkTemplates().then(({items}) => {
        if (!cleanup) {
          setTemplates(
            items.map((template) => ({
              ...template,
              isSelected: template.id === currentProjectTemplateQaId,
            })),
          )
        }
      })
    } else {
      // not logged in
    }

    return () => (cleanup = true)
  }, [setTemplates, templates, currentProjectTemplateQaId])

  // Select QF template when curren project template change
  useEffect(() => {
    setTemplates((prevState) =>
      prevState.map((template) => ({
        ...template,
        isSelected: template.id === currentProjectTemplateQaId,
      })),
    )
  }, [currentProjectTemplateQaId, setTemplates])

  // Modify current project template qa model template id when qf template id change
  useEffect(() => {
    if (
      typeof currentTemplateId === 'number' &&
      currentTemplateId !== prevCurrentProjectTemplateQaId.current &&
      currentProjectTemplateQaId === prevCurrentProjectTemplateQaId.current
    )
      modifyingCurrentProjectTemplate((prevTemplate) => ({
        ...prevTemplate,
        qaModelTemplateId: currentTemplateId,
      }))

    prevCurrentProjectTemplateQaId.current = currentProjectTemplateQaId
  }, [
    currentTemplateId,
    currentProjectTemplateQaId,
    modifyingCurrentProjectTemplate,
  ])

  return (
    <QualityFrameworkTabContext.Provider
      value={{templates, currentTemplate, modifyingCurrentTemplate}}
    >
      {templates.length > 0 && (
        <div className="settings-panel-box">
          <SubTemplates
            {...{
              templates,
              setTemplates,
              currentTemplate,
              modifyingCurrentTemplate,
              schema: QF_SCHEMA_KEYS,
              getFilteredSchemaCreateUpdate,
              createApi: createQualityFrameworkTemplate,
              updateApi: updateQualityFrameworkTemplate,
              deleteApi: deleteQualityFrameworkTemplate,
            }}
          />
          <div className="settings-panel-contentwrapper-tab-background">
            <EptThreshold />
            <CategoriesSeveritiesTable />
          </div>
        </div>
      )}
    </QualityFrameworkTabContext.Provider>
  )
}