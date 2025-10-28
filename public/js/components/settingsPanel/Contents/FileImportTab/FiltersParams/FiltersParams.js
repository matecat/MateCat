import React, {
  createContext,
  useContext,
  useEffect,
  useRef,
  useState,
} from 'react'
import {SettingsPanelContext} from '../../../SettingsPanelContext'
import CatToolActions from '../../../../../actions/CatToolActions'
import ModalsActions from '../../../../../actions/ModalsActions'
import {ConfirmDeleteResourceProjectTemplates} from '../../../../modals/ConfirmDeleteResourceProjectTemplates'
import {SubTemplates} from '../../SubTemplates'
import {SCHEMA_KEYS} from '../../../../../hooks/useProjectTemplates'
import {createFiltersParamsTemplate} from '../../../../../api/createFiltersParamsTemplate/createFiltersParamsTemplate'
import {updateFiltersParamsTemplate} from '../../../../../api/updateFiltersParamsTemplate/updateFiltersParamsTemplate'
import {deleteFiltersParamsTemplate} from '../../../../../api/deleteFiltersParamsTemplate/deleteFiltersParamsTemplate'
import {AccordionGroupFiltersParams} from './AccordionGroupFiltersParams'

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
  dita: 'dita',
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

  const [currentProjectTemplateChanged, setCurrentProjectTemplateChanged] =
    useState(false)

  const currentTemplateId = currentTemplate?.id
  const currentProjectTemplateFiltersId =
    currentProjectTemplate.filtersTemplateId
  const prevCurrentProjectTemplateFiltersId = useRef()

  const prevCurrentProjectTemplateId = useRef()

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

  // Modify current project template filters template id when filters template id change
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

  useEffect(() => {
    if (typeof currentProjectTemplate?.id === 'undefined') return

    if (currentProjectTemplate?.id !== prevCurrentProjectTemplateId.current)
      setCurrentProjectTemplateChanged(Symbol())

    prevCurrentProjectTemplateId.current = currentProjectTemplate.id
  }, [currentProjectTemplate?.id])

  return (
    <FiltersParamsContext.Provider
      value={{
        templates,
        currentTemplate,
        currentProjectTemplateChanged,
        modifyingCurrentTemplate,
      }}
    >
      {templates.length > 0 && (
        <div className="settings-panel-box">
          <div className="file-import-tab settings-panel-contentwrapper-tab-background">
            <div className="file-import-tab-header">
              <h2>Extraction parameters</h2>
              <p>
                Set specific import preferences for JSON, XML, Word, Excel,
                YAML, and PowerPoint files.{' '}
                <a
                  href="https://guides.matecat.com/file-import#:~:text=and%20bullet%20points.-,Extraction%20parameters,-For%20some%20file"
                  target="_blank"
                >
                  More details
                </a>
                .
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
