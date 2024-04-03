import React, {useContext} from 'react'
import {SUBTEMPLATE_MODIFIERS, SubTemplatesContext} from './SubTemplate'
import Checkmark from '../../../../../../../img/icons/Checkmark'
import IconClose from '../../../icons/IconClose'
import {BUTTON_SIZE, BUTTON_TYPE, Button} from '../../../common/Button/Button'

export const SubTemplateCreateUpdateControl = () => {
  const {
    setTemplates,
    currentTemplate,
    modifyingCurrentTemplate,
    templateName,
    templateModifier,
    setTemplateModifier,
    setTemplateName,
    setIsRequestInProgress,
    schema,
    getFilteredSchemaCreateUpdate,
    createApi,
    saveErrorCallback,
  } = useContext(SubTemplatesContext)

  const createTemplate = () => {
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
      <Button
        type={BUTTON_TYPE.PRIMARY}
        size={BUTTON_SIZE.MEDIUM}
        testId="create-update-template"
        onClick={
          templateModifier === SUBTEMPLATE_MODIFIERS.CREATE
            ? createTemplate
            : updateName
        }
      >
        <Checkmark size={14} />
        Confirm
      </Button>

      <Button type={BUTTON_TYPE.WARNING} onClick={cancel}>
        <IconClose size={11} />
      </Button>
    </>
  )
}
