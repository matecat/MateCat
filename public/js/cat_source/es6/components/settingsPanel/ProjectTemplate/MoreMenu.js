import React, {useContext} from 'react'
import PropTypes from 'prop-types'
import {MenuButton} from '../../common/MenuButton/MenuButton'
import DotsHorizontal from '../../../../../../img/icons/DotsHorizontal'
import {MenuButtonItem} from '../../common/MenuButton/MenuButtonItem'
import {ProjectTemplateContext} from './ProjectTemplateContext'
import {deleteProjectTemplate} from '../../../api/deleteProjectTemplate'
import {isStandardTemplate} from '../../../hooks/useProjectTemplates'
import {TEMPLATE_MODIFIERS} from './ProjectTemplate'

export const MoreMenu = ({portalTarget}) => {
  const {
    setProjectTemplates,
    isRequestInProgress,
    setIsRequestInProgress,
    currentProjectTemplate,
    setTemplateModifier,
    setTemplateName,
  } = useContext(ProjectTemplateContext)

  const deleteTemplate = () => {
    setIsRequestInProgress(true)
    deleteProjectTemplate(currentProjectTemplate.id)
      .then(({id}) =>
        setProjectTemplates((prevState) =>
          prevState
            .filter((template) => template.id !== id)
            .map((template) => ({
              ...template,
              isSelected: isStandardTemplate(template),
            })),
        ),
      )
      .catch((error) => console.log(error))
      .finally(() => setIsRequestInProgress(false))
  }

  return (
    <MenuButton
      className="template-button button-more-items"
      onClick={() => false}
      icon={<DotsHorizontal size={18} />}
      isVisibleRectArrow={false}
      itemsTarget={portalTarget ? portalTarget : document.body}
    >
      <MenuButtonItem
        disabled={isRequestInProgress}
        className="settings-panel-project-template-button-more"
        onMouseDown={() => {
          setTemplateModifier(TEMPLATE_MODIFIERS.UPDATE)
          setTemplateName(currentProjectTemplate.name)
        }}
      >
        Rename
      </MenuButtonItem>
      <MenuButtonItem
        data-testid="delete-template"
        className="settings-panel-project-template-button-more"
        disabled={isRequestInProgress}
        onMouseDown={deleteTemplate}
      >
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
