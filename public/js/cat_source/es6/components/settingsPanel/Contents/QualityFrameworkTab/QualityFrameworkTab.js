import React, {useEffect, useRef, useContext, createContext} from 'react'
import useTemplates from '../../../../hooks/useTemplates'
import {SubTemplates} from '../SubTemplates'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {getQualityFrameworkTemplates} from '../../../../api/getQualityFrameworkTemplates/getQualityFrameworkTemplates'
import {EptThreshold} from './EptThreshold'
import {createQualityFrameworkTemplate} from '../../../../api/createQualityFrameworkTemplate/createQualityFrameworkTemplate'
import {updateQualityFrameworkTemplate} from '../../../../api/updateQualityFrameworkTemplate/updateQualityFrameworkTemplate'
import {deleteQualityFrameworkTemplate} from '../../../../api/deleteQualityFrameworkTemplate/deleteQualityFrameworkTemplate'

const QF_SCHEMA_KEYS = {
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

const getMemoTemplates = (() => {
  let _templates = []

  return () =>
    new Promise((resolve) => {
      if (!_templates.length) {
        // fetch templates
        getQualityFrameworkTemplates().then(({items}) => {
          _templates = items
          resolve(items)
        })
      } else {
        resolve(_templates)
      }
    })
})()

export const QualityFrameworkTab = () => {
  const {
    currentProjectTemplate,
    modifyingCurrentTemplate: modifyingCurrentProjectTemplate,
  } = useContext(SettingsPanelContext)

  const {templates, setTemplates, currentTemplate, modifyingCurrentTemplate} =
    useTemplates(QF_SCHEMA_KEYS)

  const currentTemplateId = currentTemplate?.id
  const currentProjectTemplateQaId = currentProjectTemplate.qaModelTemplateId
  const prevCurrentProjectTemplateQaId = useRef()

  // select QF template when curren project template change
  if (
    prevCurrentProjectTemplateQaId.current !== currentProjectTemplateQaId &&
    currentTemplateId !== currentProjectTemplateQaId &&
    templates.length
  ) {
    setTemplates((prevState) =>
      prevState.map((template) => ({
        ...template,
        isSelected: template.id === currentProjectTemplateQaId,
      })),
    )
  }

  prevCurrentProjectTemplateQaId.current = currentProjectTemplateQaId

  // retrieve QF templates
  useEffect(() => {
    let cleanup = false

    if (config.isLoggedIn === 1 && !config.is_cattool) {
      getMemoTemplates().then((templates) => {
        if (!cleanup) {
          setTemplates(
            templates.map((template) => ({
              ...template,
              isSelected:
                template.id === prevCurrentProjectTemplateQaId.current,
            })),
          )
        }
      })
    } else {
      // not logged in
    }

    return () => (cleanup = true)
  }, [setTemplates])

  // Modify current project template qa model template id when qf template id change
  useEffect(() => {
    if (
      typeof currentTemplateId === 'number' &&
      currentTemplateId !== prevCurrentProjectTemplateQaId.current
    )
      modifyingCurrentProjectTemplate((prevTemplate) => ({
        ...prevTemplate,
        qaModelTemplateId: currentTemplateId,
      }))
  }, [currentTemplateId, modifyingCurrentProjectTemplate])

  return (
    <QualityFrameworkTabContext.Provider
      value={{currentTemplate, modifyingCurrentTemplate}}
    >
      {templates.length > 0 && (
        <div className="quality-framework-box">
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
          </div>
        </div>
      )}
    </QualityFrameworkTabContext.Provider>
  )
}
