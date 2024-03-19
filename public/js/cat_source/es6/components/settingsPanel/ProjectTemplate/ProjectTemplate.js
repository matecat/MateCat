import React, {useContext, useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelContext} from '../SettingsPanelContext'
import {
  SCHEMA_KEYS,
  isStandardTemplate,
} from '../../../hooks/useProjectTemplates'
import {updateProjectTemplate} from '../../../api/updateProjectTemplate'
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
                  testId="save-as-changes"
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
