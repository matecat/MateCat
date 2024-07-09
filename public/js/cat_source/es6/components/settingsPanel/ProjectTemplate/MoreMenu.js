import React, {useContext} from 'react'
import PropTypes from 'prop-types'
import {MenuButton} from '../../common/MenuButton/MenuButton'
import DotsHorizontal from '../../../../../../img/icons/DotsHorizontal'
import {MenuButtonItem} from '../../common/MenuButton/MenuButtonItem'
import {ProjectTemplateContext} from './ProjectTemplateContext'
import {deleteProjectTemplate} from '../../../api/deleteProjectTemplate'
import {isStandardTemplate} from '../../../hooks/useProjectTemplates'
import {TEMPLATE_MODIFIERS} from './ProjectTemplate'
import {SettingsPanelContext} from '../SettingsPanelContext'
import IconEdit from '../../icons/IconEdit'
import Trash from '../../../../../../img/icons/Trash'

export const MoreMenu = ({portalTarget}) => {
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
    <MenuButton
      className="template-button-white button-more-items button-more-items-project-templates"
      onClick={() => false}
      icon={<DotsHorizontal size={18} />}
      isVisibleRectArrow={false}
      itemsTarget={portalTarget}
    >
      <MenuButtonItem
        disabled={isRequestInProgress}
        className="settings-panel-templates-button-more"
        onMouseUp={() => {
          setTemplateModifier(TEMPLATE_MODIFIERS.UPDATE)
          setTemplateName(currentProjectTemplate.name)
        }}
      >
        <IconEdit />
        Rename
      </MenuButtonItem>
      <MenuButtonItem
        data-testid="delete-template"
        className="settings-panel-templates-button-more"
        disabled={isRequestInProgress}
        onMouseUp={deleteTemplate}
      >
        <Trash size={16} />
        Delete
      </MenuButtonItem>
    </MenuButton>
  )
}

MoreMenu.propTypes = {
  portalTarget: PropTypes.oneOfType([
    PropTypes.instanceOf(Element),
    PropTypes.node,
  ]),
}
