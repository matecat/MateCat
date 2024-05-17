import React, {useContext} from 'react'
import {SUBTEMPLATE_MODIFIERS, SubTemplatesContext} from './SubTemplate'
import Checkmark from '../../../../../../../img/icons/Checkmark'
import IconClose from '../../../icons/IconClose'
import {BUTTON_SIZE, BUTTON_TYPE, Button} from '../../../common/Button/Button'
import CatToolActions from '../../../../actions/CatToolActions'

export const SubTemplateCreateUpdateControl = () => {
  const {
    templates,
    currentTemplate,
    templateName,
    templateModifier,
    updateNameBehaviour,
    createTemplate,
  } = useContext(SubTemplatesContext)

  const create = () => createTemplate.current()
  const updateName = () => {
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
    } else {
      updateNameBehaviour.current.confirm()
    }
  }
  const cancel = () => updateNameBehaviour.current.cancel()

  return (
    <>
      <Button
        type={BUTTON_TYPE.PRIMARY}
        size={BUTTON_SIZE.MEDIUM}
        testId="create-update-template"
        onClick={
          templateModifier === SUBTEMPLATE_MODIFIERS.CREATE
            ? create
            : updateName
        }
        disabled={templateName === ''}
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
