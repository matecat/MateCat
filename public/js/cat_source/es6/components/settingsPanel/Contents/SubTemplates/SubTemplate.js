import React, {createContext, useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {SubTemplateSelect} from './SubTemplateSelect'
import {SubTemplateNameInput} from './SubTemplateNameInput'
import {SubTemplateMoreMenu} from './SubTemplateMoreMenu'
import {SubTemplateCreateUpdateControl} from './SubTemplateCreateUpdateControl'
import {IconSaveChanges} from '../../../icons/IconSaveChanges'
import {IconSave} from '../../../icons/IconSave'
import {BUTTON_MODE, BUTTON_SIZE, Button} from '../../../common/Button/Button'
import {flushSync} from 'react-dom'
import CatToolActions from '../../../../actions/CatToolActions'

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
  propConnectProjectTemplate,
  getFilteredSchemaCreateUpdate,
  createApi,
  updateApi,
  deleteApi,
  saveErrorCallback,
  portalTarget,
}) => {
  const [templateModifier, setTemplateModifier] = useState()
  const [templateName, setTemplateName] = useState('')
  const [isRequestInProgress, setIsRequestInProgress] = useState(false)

  const updateNameBehaviour = useRef({})
  updateNameBehaviour.current.confirm = () => {
    if (
      templates
        .filter(({id}) => id !== currentTemplate.id)
        .some(({name}) => name.toLowerCase() === templateName.toLowerCase())
    ) {
      // template name already exists
      CatToolActions.addNotification({
        title: 'Duplicated name',
        type: 'error',
        text: 'This name is already in use, please choose a different one',
        position: 'br',
      })

      return
    }

    const originalTemplate = templates.find(
      ({id, isTemporary}) => id === currentTemplate.id && !isTemporary,
    )

    const modifiedTemplate = {
      ...getFilteredSchemaCreateUpdate(originalTemplate),
      [schema.name]: templateName,
    }
    updateApi({
      id: originalTemplate.id,
      template: modifiedTemplate,
    }).then(() => {
      flushSync(() =>
        setTemplates((prevState) =>
          prevState.map((templateItem) =>
            templateItem.id === originalTemplate.id
              ? {...templateItem, [schema.name]: templateName}
              : templateItem,
          ),
        ),
      )

      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        name: templateName,
      }))
    })
  }
  updateNameBehaviour.current.cancel = () => {
    setTemplateModifier()
    setTemplateName('')
  }

  const createTemplate = useRef()
  createTemplate.current = () => {
    if (
      templates.some(
        ({name}) => name.toLowerCase() === templateName.toLowerCase(),
      )
    ) {
      // template name already exists
      CatToolActions.addNotification({
        title: 'Duplicated name',
        type: 'error',
        text: 'This name is already in use, please choose a different one',
        position: 'br',
      })
      return
    }

    const newTemplate = {
      ...getFilteredSchemaCreateUpdate(currentTemplate),
      [schema.name]: templateName,
    }
    setIsRequestInProgress(true)

    createApi(newTemplate)
      .then((template) => {
        setTemplates((prevState) => [
          ...prevState
            .filter(({isTemporary}) => !isTemporary)
            .map((templateItem) => ({...templateItem, isSelected: false})),
          {
            ...newTemplate,
            ...template,
            isSelected: true,
          },
        ])
      })
      .catch((error) => {
        if (saveErrorCallback) {
          saveErrorCallback(error)
        }
      })
      .finally(() => setIsRequestInProgress(false))
  }

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
      .catch(async (error) => {
        if (saveErrorCallback) {
          saveErrorCallback(error)
        }
      })
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
        propConnectProjectTemplate,
        deleteApi,
        updateNameBehaviour,
        createTemplate,
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
                  testId="save-as-changes"
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
  propConnectProjectTemplate: PropTypes.string.isRequired,
  getFilteredSchemaCreateUpdate: PropTypes.func.isRequired,
  createApi: PropTypes.func.isRequired,
  updateApi: PropTypes.func.isRequired,
  deleteApi: PropTypes.func.isRequired,
  saveErrorCallback: PropTypes.func,
  portalTarget: PropTypes.any,
}
