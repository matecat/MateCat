import React, {createContext, useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import {SubTemplateSelect} from './SubTemplateSelect'
import {SubTemplateNameInput} from './SubTemplateNameInput'
import {SubTemplateMoreMenu} from './SubTemplateMoreMenu'
import {SubTemplateCreateUpdateControl} from './SubTemplateCreateUpdateControl'
import {IconSaveChanges} from '../../../icons/IconSaveChanges'
import {IconSave} from '../../../icons/IconSave'
import {BUTTON_MODE, BUTTON_SIZE, Button} from '../../../common/Button/Button'
import {flushSync} from 'react-dom'

export const SUBTEMPLATE_MODIFIERS = {
  CREATE: 'create',
  UPDATE: 'update',
}

export const isStandardSubTemplate = ({id} = {}) => id === 0

export const SubTemplatesContext = createContext({})

export const SubTemplates = ({
  templates,
  setTemplates,
  currentTemplate,
  modifyingCurrentTemplate,
  schema,
  getFilteredSchemaCreateUpdate,
  createApi,
  updateApi,
  deleteApi,
  portalTarget,
}) => {
  const [templateModifier, setTemplateModifier] = useState()
  const [templateName, setTemplateName] = useState('')
  const [isRequestInProgress, setIsRequestInProgress] = useState(false)

  const updateTemplate = (updatedTemplate = currentTemplate) => {
    const modifiedTemplate = {
      ...getFilteredSchemaCreateUpdate(updatedTemplate),
      [schema.name]: updatedTemplate.name,
    }
    setIsRequestInProgress(true)

    updateApi({
      id: updatedTemplate.id,
      template: modifiedTemplate,
    })
      .then((template) => {
        flushSync(() =>
          setTemplates((prevState) =>
            prevState
              .filter(({isTemporary}) => !isTemporary)
              .map((templateItem) =>
                templateItem.id === template.id
                  ? {...modifiedTemplate, ...template, isSelected: true}
                  : templateItem,
              ),
          ),
        )

        // update current template with new id's received
        modifyingCurrentTemplate((prevTemplate) => ({
          ...prevTemplate,
          ...template,
        }))
      })
      .catch((error) => console.log(error))
      .finally(() => {
        setIsRequestInProgress(false)
        setTemplateModifier()
      })
  }

  const isStandardTemplateBool = isStandardSubTemplate(currentTemplate)

  const isModifyingTemplate = templates.some(({isTemporary}) => isTemporary)

  useEffect(() => {
    setTemplateModifier()
    setTemplateName('')
  }, [currentTemplate])

  return (
    <SubTemplatesContext.Provider
      value={{
        templates,
        setTemplates,
        currentTemplate,
        modifyingCurrentTemplate,
        templateName,
        setTemplateName,
        isRequestInProgress,
        setIsRequestInProgress,
        templateModifier,
        setTemplateModifier,
        schema,
        getFilteredSchemaCreateUpdate,
        createApi,
        deleteApi,
      }}
    >
      <div className="settings-panel-templates settings-panel-subtemplates">
        <div className="settings-panel-templates-container-select">
          <SubTemplateSelect />
          {templateModifier && <SubTemplateNameInput />}
        </div>
        <div className="settings-panel-templates-container-buttons">
          {!templateModifier ? (
            <>
              {isModifyingTemplate && !isStandardTemplateBool && (
                <Button
                  className="template-button button-save-changes"
                  mode={BUTTON_MODE.OUTLINE}
                  size={BUTTON_SIZE.MEDIUM}
                  disabled={isRequestInProgress}
                  onClick={() => updateTemplate()}
                >
                  <IconSaveChanges />
                  Save changes
                </Button>
              )}
              {isModifyingTemplate && (
                <Button
                  className="template-button"
                  testId="save-as-new-template"
                  mode={BUTTON_MODE.OUTLINE}
                  size={BUTTON_SIZE.MEDIUM}
                  disabled={isRequestInProgress}
                  onClick={() =>
                    setTemplateModifier(SUBTEMPLATE_MODIFIERS.CREATE)
                  }
                >
                  <IconSave />
                  Save as new
                </Button>
              )}
              {!isStandardTemplateBool && (
                <SubTemplateMoreMenu {...{portalTarget}} />
              )}
            </>
          ) : (
            <SubTemplateCreateUpdateControl />
          )}
        </div>
      </div>
    </SubTemplatesContext.Provider>
  )
}

SubTemplates.propTypes = {
  templates: PropTypes.array.isRequired,
  setTemplates: PropTypes.func.isRequired,
  currentTemplate: PropTypes.object,
  modifyingCurrentTemplate: PropTypes.func.isRequired,
  schema: PropTypes.object.isRequired,
  getFilteredSchemaCreateUpdate: PropTypes.func.isRequired,
  createApi: PropTypes.func.isRequired,
  updateApi: PropTypes.func.isRequired,
  deleteApi: PropTypes.func.isRequired,
  portalTarget: PropTypes.any,
}
