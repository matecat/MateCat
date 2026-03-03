import React, {useContext} from 'react'
import {
  SUBTEMPLATE_MODIFIERS,
  SubTemplatesContext,
  isStandardSubTemplate,
} from './SubTemplate'
import IconEdit from '../../../icons/IconEdit'
import Trash from '../../../../../img/icons/Trash'
import DotsHorizontal from '../../../../../img/icons/DotsHorizontal'
import ModalsActions from '../../../../actions/ModalsActions'
import {ConfirmDeleteResourceProjectTemplates} from '../../../modals/ConfirmDeleteResourceProjectTemplates'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {flushSync} from 'react-dom'
import {SCHEMA_KEYS} from '../../../../hooks/useProjectTemplates'
import {DropdownMenu} from '../../../common/DropdownMenu/DropdownMenu'
import {BUTTON_MODE, BUTTON_SIZE} from '../../../common/Button/Button'

export const SubTemplateMoreMenu = () => {
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
      const templateCategoryName =
        propConnectProjectTemplate === SCHEMA_KEYS.qaModelTemplateId
          ? 'quality framework'
          : propConnectProjectTemplate === SCHEMA_KEYS.payableRateTemplateId
            ? 'analysis'
            : propConnectProjectTemplate === SCHEMA_KEYS.filtersTemplateId
              ? 'extraction parameters'
              : propConnectProjectTemplate === SCHEMA_KEYS.XliffConfigTemplateId
                ? 'XLIFF import settings'
                : ''

      ModalsActions.showModalComponent(
        ConfirmDeleteResourceProjectTemplates,
        {
          projectTemplatesInvolved: templatesInvolved,
          successCallback: deleteTemplate,
          content: `The ${templateCategoryName} template you are about to delete is used in the following project creation template(s):`,
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
      projectTemplates.find(
        ({isSelected, isTemporary}) => isSelected && !isTemporary,
      )[propConnectProjectTemplate]
    )
      return

    const projectTemplatesUpdated = projectTemplates
      .filter((template) => template[propConnectProjectTemplate] === id)
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
    <DropdownMenu
      dropdownClassName="settings-panel-dropdownMenu"
      toggleButtonProps={{
        mode: BUTTON_MODE.OUTLINE,
        size: BUTTON_SIZE.ICON_STANDARD,
        testId: 'subtemplates-more-menu',
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
          disabled: isRequestInProgress,
          onClick: () => {
            setTemplateModifier(SUBTEMPLATE_MODIFIERS.UPDATE)
            setTemplateName(currentTemplate.name)
          },
        },
        {
          label: (
            <>
              <Trash size={18} />
              Delete
            </>
          ),
          disabled: isRequestInProgress,
          onClick: deleteTemplateConfirmation,
          testId: 'delete-template',
        },
      ]}
    />
  )
}
