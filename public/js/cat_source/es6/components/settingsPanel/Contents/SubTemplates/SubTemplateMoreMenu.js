import React, {useContext} from 'react'
import PropTypes from 'prop-types'
import {
  SUBTEMPLATE_MODIFIERS,
  SubTemplatesContext,
  isStandardSubTemplate,
} from './SubTemplate'
import {MenuButton} from '../../../common/MenuButton/MenuButton'
import {MenuButtonItem} from '../../../common/MenuButton/MenuButtonItem'
import IconEdit from '../../../icons/IconEdit'
import Trash from '../../../../../../../img/icons/Trash'
import DotsHorizontal from '../../../../../../../img/icons/DotsHorizontal'

export const SubTemplateMoreMenu = ({portalTarget}) => {
  const {
    setTemplates,
    currentTemplate,
    isRequestInProgress,
    setIsRequestInProgress,
    setTemplateModifier,
    setTemplateName,
    deleteApi,
  } = useContext(SubTemplatesContext)

  const deleteTemplate = () => {
    setIsRequestInProgress(true)
    deleteApi(currentTemplate.id)
      .then(({id}) =>
        setTemplates((prevState) =>
          prevState
            .filter((template) => template.id !== id)
            .map((template) => ({
              ...template,
              isSelected: isStandardSubTemplate(template),
            })),
        ),
      )
      .catch((error) => console.log(error))
      .finally(() => setIsRequestInProgress(false))
  }

  return (
    <MenuButton
      className="button-menu-button button-more-items"
      onClick={() => false}
      icon={<DotsHorizontal size={18} />}
      isVisibleRectArrow={false}
      itemsTarget={portalTarget}
    >
      <MenuButtonItem
        disabled={isRequestInProgress}
        className="settings-panel-templates-button-more"
        onMouseUp={() => {
          setTemplateModifier(SUBTEMPLATE_MODIFIERS.UPDATE)
          setTemplateName(currentTemplate.name)
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

SubTemplateMoreMenu.propTypes = {
  portalTarget: PropTypes.oneOfType([
    PropTypes.instanceOf(Element),
    PropTypes.node,
  ]),
}
