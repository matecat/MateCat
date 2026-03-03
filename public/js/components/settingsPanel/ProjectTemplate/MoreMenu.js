import React, {useContext} from 'react'
import DotsHorizontal from '../../../../img/icons/DotsHorizontal'
import {ProjectTemplateContext} from './ProjectTemplateContext'
import {deleteProjectTemplate} from '../../../api/deleteProjectTemplate'
import {isStandardTemplate} from '../../../hooks/useProjectTemplates'
import {TEMPLATE_MODIFIERS} from './ProjectTemplate'
import {SettingsPanelContext} from '../SettingsPanelContext'
import IconEdit from '../../icons/IconEdit'
import Trash from '../../../../img/icons/Trash'
import {DropdownMenu} from '../../common/DropdownMenu/DropdownMenu'
import {BUTTON_MODE, BUTTON_SIZE, BUTTON_TYPE} from '../../common/Button/Button'

export const MoreMenu = () => {
  const {setProjectTemplates, currentProjectTemplate} =
    useContext(SettingsPanelContext)
  const {
    isRequestInProgress,
    setIsRequestInProgress,
    setTemplateModifier,
    setTemplateName,
  } = useContext(ProjectTemplateContext)

  const deleteTemplate = () => {
    setIsRequestInProgress(true)
    deleteProjectTemplate(currentProjectTemplate.id)
      .then(({id}) =>
        setProjectTemplates((prevState) => {
          const isDeleteTemplateDefault = prevState.find(
            (template) => template.id === id,
          ).isDefault

          return prevState
            .filter((template) => template.id !== id)
            .map((template) => ({
              ...template,
              isSelected: isStandardTemplate(template),
              ...(isDeleteTemplateDefault && {
                isDefault: isStandardTemplate(template),
              }),
            }))
        }),
      )
      .catch((error) => console.log(error))
      .finally(() => setIsRequestInProgress(false))
  }

  return (
    <DropdownMenu
      dropdownClassName="settings-panel-dropdownMenu"
      toggleButtonProps={{
        className: 'project-template-dropdown-trigger-button',
        mode: BUTTON_MODE.OUTLINE,
        size: BUTTON_SIZE.ICON_STANDARD,
        testId: 'project-template-more-menu',
        children: (
          <>
            <DotsHorizontal size={18} />
          </>
        ),
      }}
      items={[
        {
          label: (
            <>
              <IconEdit size={18} />
              Rename
            </>
          ),
          disable: isRequestInProgress,
          onClick: () => {
            setTemplateModifier(TEMPLATE_MODIFIERS.UPDATE)
            setTemplateName(currentProjectTemplate.name)
          },
        },
        {
          label: (
            <>
              <Trash size={18} />
              Delete
            </>
          ),
          disable: isRequestInProgress,
          onClick: deleteTemplate,
          testId: 'delete-template',
        },
      ]}
    />
  )
}
