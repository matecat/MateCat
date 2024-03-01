import React, {createContext, useContext, useEffect, useRef} from 'react'
import {SubTemplates} from '../SubTemplates'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import useTemplates from '../../../../hooks/useTemplates'
import {getBillingModelTemplates} from '../../../../api/getBillingModelTemplates/getBillingModelTemplates'
import {createBillingModelTemplate} from '../../../../api/createBillingModelTemplate/createBillingModelTemplate'
import {updateBillingModelTemplate} from '../../../../api/updateBillingModelTemplate/updateBillingModelTemplate'
import {deleteBillingModelTemplate} from '../../../../api/deleteBillingModelTemplate/deleteBillingModelTemplate'

const BILLING_SCHEMA_KEYS = {
  id: 'id',
  uid: 'uid',
  name: 'payable_rate_template_name',
  version: 'version',
  breakdowns: 'breakdowns',
  createdAt: 'createdAt',
  modifiedAt: 'modifiedAt',
}

const getFilteredSchemaCreateUpdate = (template) => {
  /* eslint-disable no-unused-vars */
  const {
    id,
    uid,
    version,
    createdAt,
    modifiedAt,
    isTemporary,
    isSelected,
    ...filtered
  } = template
  /* eslint-enable no-unused-vars */
  return filtered
}

export const AnalysisTabContext = createContext({})

export const AnalysisTab = () => {
  const {
    currentProjectTemplate,
    modifyingCurrentTemplate: modifyingCurrentProjectTemplate,
  } = useContext(SettingsPanelContext)

  const {templates, setTemplates, currentTemplate, modifyingCurrentTemplate} =
    useTemplates(BILLING_SCHEMA_KEYS)

  const currentTemplateId = currentTemplate?.id
  const currentProjectTemplateBillingId =
    currentProjectTemplate.payableRateTemplateId
  const prevCurrentProjectTemplateBillingId = useRef()

  // select billing model template when curren project template change
  if (
    prevCurrentProjectTemplateBillingId.current !==
      currentProjectTemplateBillingId &&
    currentTemplateId !== currentProjectTemplateBillingId &&
    templates.length
  ) {
    setTemplates((prevState) =>
      prevState.map((template) => ({
        ...template,
        isSelected: template.id === currentProjectTemplateBillingId,
      })),
    )
  }

  prevCurrentProjectTemplateBillingId.current = currentProjectTemplateBillingId

  // retrieve billing model templates
  useEffect(() => {
    let cleanup = false

    if (config.isLoggedIn === 1 && !config.is_cattool) {
      getBillingModelTemplates().then(({items}) => {
        if (!cleanup) {
          setTemplates(
            items.map((template) => ({
              ...template,
              isSelected:
                template.id === prevCurrentProjectTemplateBillingId.current,
            })),
          )
        }
      })
    } else {
      // not logged in
    }

    return () => (cleanup = true)
  }, [setTemplates])

  // Modify current project template billing model template id when qf template id change
  useEffect(() => {
    if (
      typeof currentTemplateId === 'number' &&
      currentTemplateId !== prevCurrentProjectTemplateBillingId.current
    )
      modifyingCurrentProjectTemplate((prevTemplate) => ({
        ...prevTemplate,
        payableRateTemplateId: currentTemplateId,
      }))
  }, [currentTemplateId, modifyingCurrentProjectTemplate])

  return (
    <AnalysisTabContext.Provider
      value={{currentTemplate, modifyingCurrentTemplate}}
    >
      <div className="analysis-box">
        <SubTemplates
          {...{
            templates,
            setTemplates,
            currentTemplate,
            modifyingCurrentTemplate,
            schema: BILLING_SCHEMA_KEYS,
            getFilteredSchemaCreateUpdate,
            createApi: createBillingModelTemplate,
            updateApi: updateBillingModelTemplate,
            deleteApi: deleteBillingModelTemplate,
          }}
        />
      </div>
    </AnalysisTabContext.Provider>
  )
}
