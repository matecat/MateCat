import React, {useContext} from 'react'
import {ProjectTemplateContext} from './ProjectTemplateContext'
import {TEMPLATE_MODIFIERS} from './ProjectTemplate'
import IconClose from '../../icons/IconClose'
import {createProjectTemplate} from '../../../api/createProjectTemplate'
import {SCHEMA_KEYS} from '../../../hooks/useProjectTemplates'

export const CreateUpdateControl = () => {
  const {
    currentProjectTemplate,
    setProjectTemplates,
    modifyingCurrentTemplate,
    templateName,
    templateModifier,
    setTemplateModifier,
    setTemplateName,
    setIsRequestInProgress,
  } = useContext(ProjectTemplateContext)

  const createTemplate = () => {
    /* eslint-disable no-unused-vars */
    const {
      created_at,
      id,
      uid,
      modified_at,
      isTemporary,
      isSelected,
      ...newTemplate
    } = {
      ...currentProjectTemplate,
      name: templateName,
      [SCHEMA_KEYS.isDefault]: false,
    }
    /* eslint-enable no-unused-vars */
    setIsRequestInProgress(true)

    createProjectTemplate(newTemplate)
      .then((template) => {
        setProjectTemplates((prevState) => [
          ...prevState
            .filter(({isTemporary}) => !isTemporary)
            .map((templateItem) => ({...templateItem, isSelected: false})),
          {
            ...template,
            isSelected: true,
          },
        ])
      })
      .catch((error) => console.log(error))
      .finally(() => setIsRequestInProgress(false))
  }

  const updateName = () => {
    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      name: templateName,
    }))
  }

  const cancel = () => {
    setTemplateModifier()
    setTemplateName('')
  }

  return (
    <>
      <button
        className="template-button"
        data-testid="create-update-template"
        onClick={
          templateModifier === TEMPLATE_MODIFIERS.CREATE
            ? createTemplate
            : updateName
        }
      >
        Confirm
      </button>
      <button className="template-button" onClick={cancel}>
        <IconClose />
      </button>
    </>
  )
}
