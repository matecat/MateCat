import React, {useContext} from 'react'
import {SUBTEMPLATE_MODIFIERS, SubTemplatesContext} from './SubTemplate'
import Checkmark from '../../../../../../../img/icons/Checkmark'
import IconClose from '../../../icons/IconClose'

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
  } = useContext(SubTemplatesContext)

  const createTemplate = () => {
    /* eslint-disable no-unused-vars */
    const {id, uid, isTemporary, isSelected, ...newTemplate} = {
      ...currentTemplate,
      name: templateName,
    }
    /* eslint-enable no-unused-vars */
    setIsRequestInProgress(true)

    //   createProjectTemplate(newTemplate)
    //     .then((template) => {
    //       setTemplates((prevState) => [
    //         ...prevState
    //           .filter(({isTemporary}) => !isTemporary)
    //           .map((templateItem) => ({...templateItem, isSelected: false})),
    //         {
    //           ...template,
    //           isSelected: true,
    //         },
    //       ])
    //     })
    //     .catch((error) => console.log(error))
    //     .finally(() => setIsRequestInProgress(false))
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
        className="ui primary button template-button control-button"
        data-testid="create-update-template"
        onClick={
          templateModifier === SUBTEMPLATE_MODIFIERS.CREATE
            ? createTemplate
            : updateName
        }
      >
        <Checkmark size={14} />
        Confirm
      </button>
      <button
        className="ui button orange template-button control-button"
        onClick={cancel}
      >
        <IconClose size={11} />
      </button>
    </>
  )
}
