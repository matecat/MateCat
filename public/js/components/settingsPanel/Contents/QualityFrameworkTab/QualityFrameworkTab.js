import React, {useEffect, useRef, useContext, createContext} from 'react'
import {SubTemplates} from '../SubTemplates'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {
  getQualityFrameworkTemplateDefault,
  getQualityFrameworkTemplates,
} from '../../../../api/getQualityFrameworkTemplates/getQualityFrameworkTemplates'
import {EptThreshold} from './EptThreshold'
import {createQualityFrameworkTemplate} from '../../../../api/createQualityFrameworkTemplate/createQualityFrameworkTemplate'
import {updateQualityFrameworkTemplate} from '../../../../api/updateQualityFrameworkTemplate/updateQualityFrameworkTemplate'
import {deleteQualityFrameworkTemplate} from '../../../../api/deleteQualityFrameworkTemplate/deleteQualityFrameworkTemplate'
import {CategoriesSeveritiesTable} from './CategoriesSeveritiesTable'
import {SCHEMA_KEYS} from '../../../../hooks/useProjectTemplates'
import CatToolActions from '../../../../actions/CatToolActions'
import ModalsActions from '../../../../actions/ModalsActions'
import {ConfirmDeleteResourceProjectTemplates} from '../../../modals/ConfirmDeleteResourceProjectTemplates'

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
const getFilteredSchemaToCompare = (template) => {
  /* eslint-disable no-unused-vars */
  const {
    id,
    uid,
    isTemporary,
    isSelected,
    label,
    createdAt,
    deletedAt,
    modifiedAt,
    ...filtered
  } = template

  const {id: idPassfail, id_template, ...filteredPassfail} = filtered.passfail

  return {
    ...filtered,
    categories: filtered.categories.map(
      ({id, id_parent, id_template, ...filteredCategories}) => {
        return {
          ...filteredCategories,
          severities: filteredCategories.severities.map(
            ({id, id_category, ...filteredSeverities}) => filteredSeverities,
          ),
        }
      },
    ),
    passfail: {
      ...filteredPassfail,
      thresholds: filteredPassfail.thresholds.map(
        ({id, id_passfail, ...filteredThresholds}) => filteredThresholds,
      ),
    },
  }
  /* eslint-enable no-unused-vars */
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

  const getModalTryingSaveIdenticalSettingsTemplate = (templatesInvolved) =>
    new Promise((resolve, reject) => {
      ModalsActions.showModalComponent(
        ConfirmDeleteResourceProjectTemplates,
        {
          projectTemplatesInvolved: templatesInvolved,
          successCallback: () => resolve(),
          cancelCallback: () => reject(),
          content:
            'The quality framework you are trying to save has identical settings to the following quality framework:',
          footerContent:
            'Please confirm that you want to save a quality framework with the same settings as an existing quality framework',
        },
        'Quality framework',
      )
    })

  // retrieve QF templates
  useEffect(() => {
    if (templates.length) return

    let cleanup = false

    if (!config.is_cattool) {
      Promise.all([
        getQualityFrameworkTemplateDefault(),
        getQualityFrameworkTemplates(),
      ]).then(([templateDefault, templates]) => {
        // sort by name
        templates.items.sort((a, b) =>
          a.label.toLowerCase() > b.label.toLowerCase() ? 1 : -1,
        )
        const items = [templateDefault, ...templates.items]
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
        <div className="settings-panel-box settings-panel-box-quality-framework-tab">
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
              getFilteredSchemaToCompare,
              getModalTryingSaveIdenticalSettingsTemplate,
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
