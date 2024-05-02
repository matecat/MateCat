import React, {useEffect, useRef, useContext, createContext} from 'react'
import {SubTemplates} from '../SubTemplates'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {getQualityFrameworkTemplates} from '../../../../api/getQualityFrameworkTemplates/getQualityFrameworkTemplates'
import {EptThreshold} from './EptThreshold'
import {createQualityFrameworkTemplate} from '../../../../api/createQualityFrameworkTemplate/createQualityFrameworkTemplate'
import {updateQualityFrameworkTemplate} from '../../../../api/updateQualityFrameworkTemplate/updateQualityFrameworkTemplate'
import {deleteQualityFrameworkTemplate} from '../../../../api/deleteQualityFrameworkTemplate/deleteQualityFrameworkTemplate'
import {CategoriesSeveritiesTable} from './CategoriesSeveritiesTable'
import {SCHEMA_KEYS} from '../../../../hooks/useProjectTemplates'
import CatToolActions from '../../../../actions/CatToolActions'

export const QF_SCHEMA_KEYS = {
  id: 'id',
  uid: 'uid',
  name: 'label',
  version: 'version',
  categories: 'categories',
  passfail: 'passfail',
  createdAt: 'createdAt',
  deletedAt: 'deletedAt',
  modifiedAt: 'modifiedAt',
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
    portalTarget,
  } = useContext(SettingsPanelContext)

  const {templates, setTemplates, currentTemplate, modifyingCurrentTemplate} =
    qualityFrameworkTemplates

  const currentTemplateId = currentTemplate?.id
  const currentProjectTemplateQaId = currentProjectTemplate.qaModelTemplateId
  const prevCurrentProjectTemplateQaId = useRef()

  const saveErrorCallback = (error) => {
    let message = 'There was an error saving your data. Please retry!'
    CatToolActions.addNotification({
      title: 'Error saving data',
      type: 'error',
      text: message,
      position: 'br',
    })
  }

  // retrieve QF templates
  useEffect(() => {
    if (templates.length) return

    let cleanup = false

    if (config.isLoggedIn === 1 && !config.is_cattool) {
      getQualityFrameworkTemplates().then(({items}) => {
        if (!cleanup) {
          const selectedTemplateId =
            items.find(({id}) => id === currentProjectTemplateQaId)?.id ?? 0

          setTemplates(
            items.map((template) => ({
              ...template,
              isSelected: template.id === selectedTemplateId,
            })),
          )
        }
      })
    } else {
      // not logged in
    }

    return () => (cleanup = true)
  }, [setTemplates, templates.length, currentProjectTemplateQaId])

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
              portalTarget,
              schema: QF_SCHEMA_KEYS,
              propConnectProjectTemplate: SCHEMA_KEYS.qaModelTemplateId,
              getFilteredSchemaCreateUpdate,
              createApi: createQualityFrameworkTemplate,
              updateApi: updateQualityFrameworkTemplate,
              deleteApi: deleteQualityFrameworkTemplate,
              saveErrorCallback,
            }}
          />
          <div className="quality-framework-tab settings-panel-contentwrapper-tab-background">
            <EptThreshold />
            <CategoriesSeveritiesTable />
          </div>
        </div>
      )}
    </QualityFrameworkTabContext.Provider>
  )
}
