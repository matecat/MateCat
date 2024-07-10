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
import ModalsActions from '../../../../actions/ModalsActions'
import {ConfirmDeleteResourceProjectTemplates} from '../../../modals/ConfirmDeleteResourceProjectTemplates'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {flushSync} from 'react-dom'
import {SCHEMA_KEYS} from '../../../../hooks/useProjectTemplates'

export const SubTemplateMoreMenu = ({portalTarget}) => {
  const {
    projectTemplates,
    setProjectTemplates,
    modifyingCurrentTemplate: modifyingCurrentProjectTemplate,
  } = useContext(SettingsPanelContext)

  const {
    setTemplates,
    currentTemplate,
    isRequestInProgress,
    setIsRequestInProgress,
    setTemplateModifier,
    setTemplateName,
    propConnectProjectTemplate,
    deleteApi,
  } = useContext(SubTemplatesContext)

  const deleteTemplateConfirmation = () => {
    const templatesInvolved = projectTemplates.filter(
      (template) =>
        !template.isTemporary &&
        template[propConnectProjectTemplate] === currentTemplate.id,
    )

    if (templatesInvolved.length) {
      ModalsActions.showModalComponent(
        ConfirmDeleteResourceProjectTemplates,
        {
          projectTemplatesInvolved: templatesInvolved,
          successCallback: deleteTemplate,
          content: `The ${propConnectProjectTemplate === SCHEMA_KEYS.qaModelTemplateId ? 'quality framework' : 'analysis'} template you are about to delete is used in the following project creation template(s):`,
        },
        'Confirm deletion',
      )
    } else {
      deleteTemplate()
    }
  }

  const deleteTemplate = () => {
    setIsRequestInProgress(true)
    deleteApi(currentTemplate.id)
      .then(({id}) => {
        setTemplates((prevState) =>
          prevState
            .filter((template) => template.id !== id)
            .map((template) => ({
              ...template,
              isSelected: isStandardSubTemplate(template),
            })),
        )

        cleanProjectTemplateAfterDelete(id)
      })
      .catch((error) => console.log(error))
      .finally(() => setIsRequestInProgress(false))
  }

  const cleanProjectTemplateAfterDelete = (id) => {
    if (
      id !==
      projectTemplates.find(({isSelected}) => isSelected).qaModelTemplateId
    )
      return

    const projectTemplatesUpdated = projectTemplates
      .filter(({qaModelTemplateId}) => qaModelTemplateId === id)
      .map((template) => ({...template, [propConnectProjectTemplate]: 0}))

    if (projectTemplatesUpdated.length) {
      flushSync(() =>
        setProjectTemplates((prevState) =>
          prevState.map((template) => {
            const update = projectTemplatesUpdated.find(
              ({id} = {}) => id === template.id && !template.isTemporary,
            )
            return {...template, ...(update && {...update})}
          }),
        ),
      )

      const currentOriginalTemplate = projectTemplatesUpdated.find(
        ({isSelected, isTemporary}) => isSelected && !isTemporary,
      )

      modifyingCurrentProjectTemplate((prevTemplate) => ({
        ...prevTemplate,
        ...currentOriginalTemplate,
      }))
    }
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
        onMouseUp={deleteTemplateConfirmation}
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
