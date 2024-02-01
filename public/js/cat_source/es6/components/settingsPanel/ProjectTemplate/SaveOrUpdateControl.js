import React, {useContext} from 'react'
import {ProjectTemplateContext} from './ProjectTemplateContext'
import {TEMPLATE_MODIFIERS} from './ProjectTemplate'
import IconClose from '../../icons/IconClose'
import {createProjectTemplate} from '../../../api/createProjectTemplate'

export const SaveOrUpdateControl = () => {
  const {
    currentProjectTemplate,
    setProjectTemplates,
    templateName,
    templateModifier,
    setTemplateModifier,
    setTemplateName,
    setIsRequestInProgress,
    updateTemplate,
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
    } = {...currentProjectTemplate, name: templateName, is_default: false}
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

  const update = () => updateTemplate()

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
            : update
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
