import React, {useContext} from 'react'
import {ProjectTemplateContext} from './ProjectTemplateContext'
import {TEMPLATE_MODIFIERS} from './ProjectTemplate'
import IconClose from '../../icons/IconClose'
import Checkmark from '../../../../../../img/icons/Checkmark'
import {BUTTON_SIZE, BUTTON_TYPE, Button} from '../../common/Button/Button'

export const CreateUpdateControl = () => {
  const {templateName, templateModifier, updateNameBehaviour, createTemplate} =
    useContext(ProjectTemplateContext)

  const create = () => createTemplate.current()
  const updateName = () => updateNameBehaviour.current.confirm()
  const cancel = () => updateNameBehaviour.current.cancel()

  return (
    <>
      <Button
        testId="create-update-template"
        type={BUTTON_TYPE.PRIMARY}
        size={BUTTON_SIZE.MEDIUM}
        disabled={templateName === ''}
        onClick={
          templateModifier === TEMPLATE_MODIFIERS.CREATE ? create : updateName
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
