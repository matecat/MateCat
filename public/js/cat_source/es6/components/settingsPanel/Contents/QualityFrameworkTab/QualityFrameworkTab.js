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

export const QualityFrameworkTabContext = createContext({})

export const QualityFrameworkTab = () => {
  const {currentProjectTemplate} = useContext(SettingsPanelContext)

  const {templates, setTemplates, currentTemplate, modifyingCurrentTemplate} =
    useTemplates(QF_SCHEMA_KEYS)

  const currentProjectTemplateQaId =
    currentProjectTemplate.qaModelTemplateId ?? 0
  const qaModelTemplateId = useRef()

  if (
    qaModelTemplateId.current !== currentProjectTemplateQaId &&
    templates.length
  ) {
    // select right QF template when curren project template change
  }

  qaModelTemplateId.current = currentProjectTemplateQaId

  useEffect(() => {
    let cleanup = false

    if (config.isLoggedIn === 1 && !config.is_cattool) {
      getQualityFrameworkTemplates().then(({items}) => {
        if (!cleanup) {
          setTemplates(
            items.map((template) => ({
              ...template,
              isSelected: template.id === qaModelTemplateId.current,
            })),
          )
        }
      })
    } else {
      // not logged in
    }

    return () => (cleanup = true)
  }, [setTemplates])

  return (
    <QualityFrameworkTabContext.Provider
      value={{currentTemplate, modifyingCurrentTemplate}}
    >
      <div className="quality-framework-box">
        <SubTemplates
          {...{
            templates,
            setTemplates,
            currentTemplate,
            modifyingCurrentTemplate,
            schema: QF_SCHEMA_KEYS,
            createApi: createQualityFrameworkTemplate,
            updateApi: updateQualityFrameworkTemplate,
            deleteApi: deleteQualityFrameworkTemplate,
          }}
        />
        <div className="settings-panel-contentwrapper-tab-background">
          <EptThreshold />
        </div>
      </div>
    </QualityFrameworkTabContext.Provider>
  )
}
