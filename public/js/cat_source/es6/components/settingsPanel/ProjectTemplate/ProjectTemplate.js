import React, {useCallback, useContext, useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelContext} from '../SettingsPanelContext'
import {
  SCHEMA_KEYS,
  isStandardTemplate,
} from '../../../hooks/useProjectTemplates'
import {updateProjectTemplate} from '../../../api/updateProjectTemplate'
import CreateProjectStore from '../../../stores/CreateProjectStore'
import NewProjectConstants from '../../../constants/NewProjectConstants'
import {flushSync} from 'react-dom'
import {ProjectTemplateContext} from './ProjectTemplateContext'
import {TemplateNameInput} from './TemplateNameInput'
import {MoreMenu} from './MoreMenu'
import {CreateUpdateControl} from './CreateUpdateControl'
import {TemplateSelect} from './TemplateSelect'

export const TEMPLATE_MODIFIERS = {
  CREATE: 'create',
  UPDATE: 'update',
}

export const ProjectTemplate = ({portalTarget}) => {
  const {
    projectTemplates,
    setProjectTemplates,
    currentProjectTemplate,
    modifyingCurrentTemplate,
  } = useContext(SettingsPanelContext)

  const [templateModifier, setTemplateModifier] = useState()
  const [templateName, setTemplateName] = useState('')
  const [isRequestInProgress, setIsRequestInProgress] = useState(false)

  const isStandardTemplateBool = isStandardTemplate(currentProjectTemplate)

  const isModifyingTemplate = projectTemplates.some(
    ({isTemporary}) => isTemporary,
  )

  const updateTemplate = useCallback(
    (updatedTemplate = currentProjectTemplate) => {
      /* eslint-disable no-unused-vars */
      const {
        created_at,
        id,
        uid,
        modified_at,
        isTemporary,
        isSelected,
        ...modifiedTemplate
      } = {
        ...updatedTemplate,
        name: updatedTemplate.name,
      }
      /* eslint-enable no-unused-vars */
      setIsRequestInProgress(true)

      updateProjectTemplate({
        id: updatedTemplate.id,
        template: modifiedTemplate,
      })
        .then((template) => {
          setProjectTemplates((prevState) =>
            prevState
              .filter(({isTemporary}) => !isTemporary)
              .map((templateItem) =>
                templateItem.id === template.id
                  ? {...template, isSelected: true}
                  : templateItem,
              ),
          )
        })
        .catch((error) => console.log(error))
        .finally(() => {
          setIsRequestInProgress(false)
          setTemplateModifier()
        })
    },
    [currentProjectTemplate, setProjectTemplates],
  )

  // Notify to server when user deleted a tmKey, MT or MT glossary from templates and sync project templates state
  useEffect(() => {
    const updateProjectTemplatesAction = ({
      templates,
      modifiedPropsCurrentProjectTemplate,
    }) => {
      const promiseTemplates = templates
        .filter(({isTemporary, id}) => !isTemporary && !isStandardTemplate(id))
        .map(
          (template) =>
            new Promise((resolve, reject) => {
              /* eslint-disable no-unused-vars */
              const {
                created_at,
                id,
                uid,
                modified_at,
                isTemporary,
                isSelected,
                ...modifiedTemplate
              } = template
              //     /* eslint-enable no-unused-vars */

              updateProjectTemplate({
                id: template.id,
                template: modifiedTemplate,
              })
                .then((template) => resolve(template))
                .catch((error) => reject())
            }),
        )

      Promise.all(promiseTemplates).then((values) => {
        flushSync(() =>
          setProjectTemplates((prevState) =>
            prevState.map((template) => {
              const update = values.find(
                ({id} = {}) => id === template.id && !template.isTemporary,
              )
              return {...template, ...(update && {...update})}
            }),
          ),
        )

        const currentOriginalTemplate = values.find(
          ({id, isTemporary}) =>
            id === currentProjectTemplate.id && !isTemporary,
        )

        modifyingCurrentTemplate((prevTemplate) => ({
          ...prevTemplate,
          ...(templates.find(
            ({isTemporary, id}) => isTemporary && !isStandardTemplate(id),
          )
            ? {
                ...modifiedPropsCurrentProjectTemplate,
                ...(currentOriginalTemplate && {
                  modifiedAt: currentOriginalTemplate.modified_at,
                }),
              }
            : currentOriginalTemplate),
        }))
      })
    }

    CreateProjectStore.addListener(
      NewProjectConstants.UPDATE_PROJECT_TEMPLATES,
      updateProjectTemplatesAction,
    )

    return () =>
      CreateProjectStore.removeListener(
        NewProjectConstants.UPDATE_PROJECT_TEMPLATES,
        updateProjectTemplatesAction,
      )
  }, [currentProjectTemplate.id, setProjectTemplates, modifyingCurrentTemplate])

  useEffect(() => {
    setTemplateModifier()
    setTemplateName('')
  }, [currentProjectTemplate])

  const setCurrentProjectTemplateAsDefault = () => {
    flushSync(() =>
      setProjectTemplates((prevState) =>
        prevState.map((template) => ({
          ...template,
          isDefault: template.id === currentProjectTemplate.id,
        })),
      ),
    )

    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      isDefault: true,
    }))

    updateTemplate({
      ...currentProjectTemplate,
      [SCHEMA_KEYS.isDefault]: true,
    })
  }

  const isActiveSetAsDefault =
    !currentProjectTemplate.isDefault && !isModifyingTemplate

  return (
    <ProjectTemplateContext.Provider
      value={{
        updateTemplate,
        templateName,
        setTemplateName,
        isRequestInProgress,
        setIsRequestInProgress,
        templateModifier,
        setTemplateModifier,
      }}
    >
      <div className="settings-panel-project-template">
        <div className="settings-panel-project-template-container-select">
          <h3>Project template</h3>
          <TemplateSelect
            {...{projectTemplates, setProjectTemplates, currentProjectTemplate}}
          />
          {templateModifier && <TemplateNameInput />}
        </div>
        <div className="settings-panel-project-template-container-buttons">
          {!templateModifier ? (
            <>
              {isActiveSetAsDefault && (
                <button
                  className="template-button"
                  data-testid="set-as-default-template"
                  disabled={isRequestInProgress}
                  onClick={setCurrentProjectTemplateAsDefault}
                >
                  Set as default
                </button>
              )}
              {isModifyingTemplate && !isStandardTemplateBool && (
                <button
                  className="template-button button-save-changes"
                  disabled={isRequestInProgress}
                  onClick={() => updateTemplate()}
                >
                  Save changes
                </button>
              )}
              {isModifyingTemplate && (
                <button
                  className="template-button"
                  data-testid="save-as-new-template"
                  disabled={isRequestInProgress}
                  onClick={() => setTemplateModifier(TEMPLATE_MODIFIERS.CREATE)}
                >
                  Save as new
                </button>
              )}
              {!isStandardTemplateBool && <MoreMenu {...{portalTarget}} />}
            </>
          ) : (
            <CreateUpdateControl />
          )}
        </div>
      </div>
    </ProjectTemplateContext.Provider>
  )
}

ProjectTemplate.propTypes = {
  portalTarget: PropTypes.oneOfType([
    PropTypes.instanceOf(Element),
    PropTypes.node,
  ]),
}
