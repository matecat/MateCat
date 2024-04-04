import React, {useContext} from 'react'
import {ProjectTemplateContext} from './ProjectTemplateContext'
import {TEMPLATE_MODIFIERS} from './ProjectTemplate'
import IconClose from '../../icons/IconClose'
import {createProjectTemplate} from '../../../api/createProjectTemplate'
import {SCHEMA_KEYS} from '../../../hooks/useProjectTemplates'
import {SettingsPanelContext} from '../SettingsPanelContext'
import Checkmark from '../../../../../../img/icons/Checkmark'
import {BUTTON_SIZE, BUTTON_TYPE, Button} from '../../common/Button/Button'

export const CreateUpdateControl = () => {
  const {
    currentProjectTemplate,
    setProjectTemplates,
    modifyingCurrentTemplate,
  } = useContext(SettingsPanelContext)
  const {
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
      <Button
        testId="create-update-template"
        type={BUTTON_TYPE.PRIMARY}
        size={BUTTON_SIZE.MEDIUM}
        disabled={templateName === ''}
        onClick={
          templateModifier === TEMPLATE_MODIFIERS.CREATE
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
