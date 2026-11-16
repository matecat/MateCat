import React, {useContext} from 'react'
import {ProjectTemplateContext} from './ProjectTemplateContext'
import {TEMPLATE_MODIFIERS} from './ProjectTemplateConstants'
import IconClose from '../../../../img/icons/IconClose'
import Checkmark from '../../../../img/icons/Checkmark'
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
        size={BUTTON_SIZE.STANDARD}
        disabled={templateName === ''}
        onClick={
          templateModifier === TEMPLATE_MODIFIERS.CREATE ? create : updateName
        }
      >
        <Checkmark size={18} />
        Confirm
      </Button>

      <Button
        type={BUTTON_TYPE.WARNING}
        size={BUTTON_SIZE.ICON_STANDARD}
        onClick={cancel}
      >
        <IconClose size={18} />
      </Button>
    </>
  )
}
