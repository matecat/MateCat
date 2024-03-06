import React, {useContext, useEffect, useState} from 'react'
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
import {IconPin} from '../../icons/IconPin'
import {IconSave} from '../../icons/IconSave'
import {IconSaveChanges} from '../../icons/IconSaveChanges'
import {BUTTON_MODE, BUTTON_SIZE, Button} from '../../common/Button/Button'

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

  const updateTemplate = (updatedTemplate = currentProjectTemplate) => {
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
  }

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
              /* eslint-enable no-unused-vars */

              updateProjectTemplate({
                id: template.id,
                template: modifiedTemplate,
              })
                .then((template) => resolve(template))
                .catch(() => reject())
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
        templateName,
        setTemplateName,
        isRequestInProgress,
        setIsRequestInProgress,
        templateModifier,
        setTemplateModifier,
      }}
    >
      <div className="settings-panel-templates">
        <div className="settings-panel-templates-container-select">
          <h3>Project template</h3>
          <TemplateSelect
            {...{projectTemplates, setProjectTemplates, currentProjectTemplate}}
          />
          {templateModifier && <TemplateNameInput />}
        </div>
        <div className="settings-panel-templates-container-buttons">
          {!templateModifier ? (
            <>
              {isActiveSetAsDefault && (
                <Button
                  className="template-button-white"
                  size={BUTTON_SIZE.MEDIUM}
                  mode={BUTTON_MODE.OUTLINE}
                  testId="set-as-default-template"
                  disabled={isRequestInProgress}
                  onClick={setCurrentProjectTemplateAsDefault}
                >
                  <IconPin />
                  Set as default
                </Button>
              )}
              {isModifyingTemplate && !isStandardTemplateBool && (
                <Button
                  className="template-button-white button-save-changes"
                  size={BUTTON_SIZE.MEDIUM}
                  mode={BUTTON_MODE.OUTLINE}
                  disabled={isRequestInProgress}
                  onClick={() => updateTemplate()}
                >
                  <IconSaveChanges />
                  Save changes
                </Button>
              )}
              {isModifyingTemplate && (
                <Button
                  testId="save-as-new-template"
                  className="template-button-white"
                  size={BUTTON_SIZE.MEDIUM}
                  mode={BUTTON_MODE.OUTLINE}
                  disabled={isRequestInProgress}
                  onClick={() => setTemplateModifier(TEMPLATE_MODIFIERS.CREATE)}
                >
                  <IconSave />
                  Save as new
                </Button>
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
