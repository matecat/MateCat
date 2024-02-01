import React, {useCallback, useContext, useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import {Select} from '../../common/Select'
import {SettingsPanelContext} from '../SettingsPanelContext'
import IconClose from '../../icons/IconClose'
import {createProjectTemplate} from '../../../api/createProjectTemplate'
import {deleteProjectTemplate} from '../../../api/deleteProjectTemplate'
import {isStandardTemplate} from '../../../hooks/useProjectTemplates'
import {updateProjectTemplate} from '../../../api/updateProjectTemplate'
import {MenuButton} from '../../common/MenuButton/MenuButton'
import {MenuButtonItem} from '../../common/MenuButton/MenuButtonItem'
import DotsHorizontal from '../../../../../../img/icons/DotsHorizontal'
import CreateProjectStore from '../../../stores/CreateProjectStore'
import NewProjectConstants from '../../../constants/NewProjectConstants'
import {flushSync} from 'react-dom'
import {ProjectTemplateContext} from './ProjectTemplateContext'
import {TemplateNameInput} from './TemplateNameInput'
import {MoreMenu} from './MoreMenu'
import {SaveOrUpdateControl} from './SaveOrUpdateControl'
import {ProjectTemplateSelect} from './ProjectTemplateSelect'

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
    availableTemplateProps,
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
        name: templateName ? templateName : updatedTemplate.name,
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
    [currentProjectTemplate, setProjectTemplates, templateName],
  )

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
                  modified_at: currentOriginalTemplate.modified_at,
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
          [availableTemplateProps.isDefault]:
            template.id === currentProjectTemplate.id,
        })),
      ),
    )

    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      [availableTemplateProps.isDefault]: true,
    }))

    updateTemplate({
      ...currentProjectTemplate,
      [availableTemplateProps.isDefault]: true,
    })
  }

  const isActiveSetAsDefault =
    !currentProjectTemplate[availableTemplateProps.isDefault] &&
    !isModifyingTemplate

  return (
    <ProjectTemplateContext.Provider
      value={{
        currentProjectTemplate,
        projectTemplates,
        setProjectTemplates,
        templateName,
        setTemplateName,
        isRequestInProgress,
        setIsRequestInProgress,
        templateModifier,
        setTemplateModifier,
        updateTemplate,
      }}
    >
      <div className="settings-panel-project-template">
        <div className="settings-panel-project-template-container-select">
          <h3>Project template</h3>
          <ProjectTemplateSelect />
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
            <SaveOrUpdateControl />
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
