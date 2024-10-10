import React, {createContext, useContext, useEffect, useRef} from 'react'
import {SettingsPanelContext} from '../../../SettingsPanelContext'
import CatToolActions from '../../../../../actions/CatToolActions'
import ModalsActions from '../../../../../actions/ModalsActions'
import {ConfirmDeleteResourceProjectTemplates} from '../../../../modals/ConfirmDeleteResourceProjectTemplates'
import {getFiltersParamsTemplates} from '../../../../../api/getFiltersParamsTemplates'
import {SubTemplates} from '../../SubTemplates'
import {SCHEMA_KEYS} from '../../../../../hooks/useProjectTemplates'
import {createFiltersParamsTemplate} from '../../../../../api/createFiltersParamsTemplate/createFiltersParamsTemplate'
import {updateFiltersParamsTemplate} from '../../../../../api/updateFiltersParamsTemplate/updateFiltersParamsTemplate'
import {deleteFiltersParamsTemplate} from '../../../../../api/deleteFiltersParamsTemplate/deleteFiltersParamsTemplate'
import {AccordionGroupFiltersParams} from './AccordionGroupFiltersParams'
import defaultFiltersParams from '../../defaultTemplates/filterParams.json'

export const FILTERS_PARAMS_SCHEMA_KEYS = {
  id: 'id',
  uid: 'uid',
  name: 'name',
  xml: 'xml',
  yaml: 'yaml',
  json: 'json',
  msWord: 'ms_word',
  msExcel: 'ms_excel',
  msPowerpoint: 'ms_powerpoint',
  createdAt: 'created_at',
  modifiedAt: 'modified_at',
}

const getFilteredSchemaCreateUpdate = (template) => {
  /* eslint-disable no-unused-vars */
  const {
    id,
    uid,
    isTemporary,
    isSelected,
    created_at,
    modified_at,
    ...filtered
  } = template
  /* eslint-enable no-unused-vars */
  return filtered
}
const getFilteredSchemaToCompare = (template) => {
  /* eslint-disable no-unused-vars */
  const {
    id,
    uid,
    isTemporary,
    isSelected,
    name,
    created_at,
    modified_at,
    ...filtered
  } = template

  return filtered
  /* eslint-enable no-unused-vars */
}

export const FiltersParamsContext = createContext({})

export const FiltersParams = () => {
  const {
    currentProjectTemplate,
    modifyingCurrentTemplate: modifyingCurrentProjectTemplate,
    fileImportFiltersParamsTemplates,
    portalTarget,
  } = useContext(SettingsPanelContext)

  const {templates, setTemplates, currentTemplate, modifyingCurrentTemplate} =
    fileImportFiltersParamsTemplates

  const currentTemplateId = currentTemplate?.id
  const currentProjectTemplateFiltersId =
    currentProjectTemplate.filtersTemplateId
  const prevCurrentProjectTemplateFiltersId = useRef()

  const saveErrorCallback = (error) => {
    let message = 'There was an error saving your data. Please retry!'
    CatToolActions.addNotification({
      title: 'Error saving data',
      type: 'error',
      text: message,
      position: 'br',
    })
  }

  const getModalTryingSaveIdenticalSettingsTemplate = (templatesInvolved) =>
    new Promise((resolve, reject) => {
      ModalsActions.showModalComponent(
        ConfirmDeleteResourceProjectTemplates,
        {
          projectTemplatesInvolved: templatesInvolved,
          successCallback: () => resolve(),
          cancelCallback: () => reject(),
          content:
            'The extraction parameters you are trying to save has identical settings to the following extraction parameters:',
          footerContent:
            'Please confirm that you want to save a extraction parameters with the same settings as an existing extraction parameters',
        },
        'Extraction parameters',
      )
    })

  // retrieve filters params templates
  useEffect(() => {
    if (templates.length) return

    let cleanup = false

    if (config.isLoggedIn === 1 && !config.is_cattool) {
      getFiltersParamsTemplates().then((templates) => {
        const items = [defaultFiltersParams, ...templates.items]
        if (!cleanup) {
          const selectedTemplateId =
            items.find(({id}) => id === currentProjectTemplateFiltersId)?.id ??
            0

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
  }, [setTemplates, templates.length, currentProjectTemplateFiltersId])

  // Select QF template when curren project template change
  useEffect(() => {
    setTemplates((prevState) =>
      prevState.map((template) => ({
        ...template,
        isSelected: template.id === currentProjectTemplateFiltersId,
      })),
    )
  }, [currentProjectTemplateFiltersId, setTemplates])

  // Modify current project template qa model template id when qf template id change
  useEffect(() => {
    if (
      typeof currentTemplateId === 'number' &&
      currentTemplateId !== prevCurrentProjectTemplateFiltersId.current &&
      currentProjectTemplateFiltersId ===
        prevCurrentProjectTemplateFiltersId.current
    )
      modifyingCurrentProjectTemplate((prevTemplate) => ({
        ...prevTemplate,
        filtersTemplateId: currentTemplateId,
      }))

    prevCurrentProjectTemplateFiltersId.current =
      currentProjectTemplateFiltersId
  }, [
    currentTemplateId,
    currentProjectTemplateFiltersId,
    modifyingCurrentProjectTemplate,
  ])

  return (
    <FiltersParamsContext.Provider
      value={{templates, currentTemplate, modifyingCurrentTemplate}}
    >
      {templates.length > 0 && (
        <div className="settings-panel-box">
          <div className="file-import-tab settings-panel-contentwrapper-tab-background">
            <div className="file-import-tab-header">
              <h2>Extraction parameters</h2>
              <p>
                Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nunc
                vulputate libero et velit interdum, ac aliquet odio mattis.
              </p>
              <SubTemplates
                {...{
                  templates,
                  setTemplates,
                  currentTemplate,
                  modifyingCurrentTemplate,
                  portalTarget,
                  schema: FILTERS_PARAMS_SCHEMA_KEYS,
                  propConnectProjectTemplate: SCHEMA_KEYS.filtersTemplateId,
                  getFilteredSchemaCreateUpdate,
                  getFilteredSchemaToCompare,
                  getModalTryingSaveIdenticalSettingsTemplate,
                  createApi: createFiltersParamsTemplate,
                  updateApi: updateFiltersParamsTemplate,
                  deleteApi: deleteFiltersParamsTemplate,
                  saveErrorCallback,
                }}
              />
            </div>
            <AccordionGroupFiltersParams />
          </div>
        </div>
      )}
    </FiltersParamsContext.Provider>
  )
}
