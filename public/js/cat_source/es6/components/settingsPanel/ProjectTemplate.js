import React, {useContext, useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import {Select} from '../common/Select'
import {SettingsPanelContext} from './SettingsPanelContext'
import IconClose from '../icons/IconClose'
import {createProjectTemplate} from '../../api/createProjectTemplate'
import {deleteProjectTemplate} from '../../api/deleteProjectTemplate'
import {isStandardTemplate} from '../../hooks/useProjectTemplates'
import {updateProjectTemplate} from '../../api/updateProjectTemplate'
import {MenuButton} from '../common/MenuButton/MenuButton'
import {MenuButtonItem} from '../common/MenuButton/MenuButtonItem'
import DotsHorizontal from '../../../../../img/icons/DotsHorizontal'
import CreateProjectStore from '../../stores/CreateProjectStore'
import NewProjectConstants from '../../constants/NewProjectConstants'
import {flushSync} from 'react-dom'

const TEMPLATE_NAME_MANAGE_MODE = {
  NEW_ONE: 'newOne',
  EDITING: 'editing',
}

export const ProjectTemplate = ({portalTarget}) => {
  const {
    projectTemplates,
    setProjectTemplates,
    currentProjectTemplate,
    modifyingCurrentTemplate,
    availableTemplateProps,
  } = useContext(SettingsPanelContext)

  const [templateNameManageMode, setTemplateNameManageMode] = useState()
  const [templateName, setTemplateName] = useState('')
  const [isRequestInProgress, setIsRequestInProgress] = useState(false)

  const currentTemplate = projectTemplates.find(({isSelected}) => isSelected)
  const isModifyingTemplate = projectTemplates.some(
    ({isTemporary}) => isTemporary,
  )
  const isStandardTemplateBool = isStandardTemplate(currentTemplate)

  useEffect(() => {
    const updateProjectTemplatesAction = ({
      templates,
      modifiedPropsCurrentProjectTemplate,
    }) => {
      const promiseTemplates = templates
        .filter(({isTemporary, id}) => !isTemporary && !isStandardTemplate(id))
        .map(
          (template) =>
            new Promise((resolve, reject) => {
              /* eslint-disable no-unused-vars */
              const {
                created_at,
                id,
                uid,
                modified_at,
                isTemporary,
                isSelected,
                ...modifiedTemplate
              } = template
              //     /* eslint-enable no-unused-vars */

              updateProjectTemplate({
                id: template.id,
                template: modifiedTemplate,
              })
                .then((template) => resolve(template))
                .catch((error) => reject())
            }),
        )

      Promise.all(promiseTemplates).then((values) => {
        flushSync(() =>
          setProjectTemplates((prevState) =>
            prevState.map((template) => {
              const update = values.find(
                ({id} = {}) => id === template.id && !template.isTemporary,
              )
              return {...template, ...(update && {...update})}
            }),
          ),
        )

        const currentOriginalTemplate = values.find(
          ({id, isTemporary}) =>
            id === currentProjectTemplate.id && !isTemporary,
        )

        modifyingCurrentTemplate((prevTemplate) => ({
          ...prevTemplate,
          ...(templates.find(
            ({isTemporary, id}) => isTemporary && !isStandardTemplate(id),
          )
            ? {
                ...modifiedPropsCurrentProjectTemplate,
                ...(currentOriginalTemplate && {
                  modified_at: currentOriginalTemplate.modified_at,
                }),
              }
            : currentOriginalTemplate),
        }))
      })
    }

    CreateProjectStore.addListener(
      NewProjectConstants.UPDATE_PROJECT_TEMPLATES,
      updateProjectTemplatesAction,
    )

    return () =>
      CreateProjectStore.removeListener(
        NewProjectConstants.UPDATE_PROJECT_TEMPLATES,
        updateProjectTemplatesAction,
      )
  }, [currentProjectTemplate.id, setProjectTemplates, modifyingCurrentTemplate])

  useEffect(() => {
    setTemplateNameManageMode()
    setTemplateName('')
  }, [currentProjectTemplate])

  const options = projectTemplates
    .filter(({isTemporary}) => !isTemporary)
    .map(({id, name}) => ({
      id: id.toString(),
      name,
    }))
  const activeOption = currentTemplate && {
    id: currentTemplate.id.toString(),
    name: `${currentTemplate.name}${isModifyingTemplate ? ' *' : ''}`,
  }

  const onSelect = (option) =>
    setProjectTemplates((prevState) =>
      prevState.map((template) => ({
        ...template,
        isSelected: template.id === parseInt(option.id),
      })),
    )

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
    } = {...currentProjectTemplate, name: templateName}
    /* eslint-enable no-unused-vars */
    setIsRequestInProgress(true)

    createProjectTemplate(newTemplate)
      .then((template) => {
        setProjectTemplates((prevState) => [
          ...prevState
            .filter(({isTemporary}) => !isTemporary)
            .map((templateItem) => ({...templateItem, isSelected: false})),
          {...template, isSelected: true},
        ])
      })
      .catch((error) => console.log(error))
      .finally(() => setIsRequestInProgress(false))
  }

  const updateTemplate = () => {
    /* eslint-disable no-unused-vars */
    const {
      created_at,
      id,
      uid,
      modified_at,
      isTemporary,
      isSelected,
      ...modifiedTemplate
    } = {
      ...currentProjectTemplate,
      name: templateName ? templateName : currentProjectTemplate.name,
    }
    /* eslint-enable no-unused-vars */
    setIsRequestInProgress(true)

    updateProjectTemplate({
      id: currentProjectTemplate.id,
      template: modifiedTemplate,
    })
      .then((template) => {
        setProjectTemplates((prevState) =>
          prevState
            .filter(({isTemporary}) => !isTemporary)
            .map((templateItem) =>
              templateItem.id === template.id
                ? {...template, isSelected: true}
                : templateItem,
            ),
        )
      })
      .catch((error) => console.log(error))
      .finally(() => setIsRequestInProgress(false))
  }

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

  const onChangeTemplateName = (e) => setTemplateName(e.currentTarget.value)

  const cancelSavingNewTemplate = () => {
    setTemplateNameManageMode()
    setTemplateName('')
  }

  const setCurrentProjectTemplateAsDefault = () => {
    flushSync(() =>
      setProjectTemplates((prevState) =>
        prevState.map((template) => ({
          ...template,
          [availableTemplateProps.isDefault]:
            template.id === currentProjectTemplate.id,
        })),
      ),
    )

    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      [availableTemplateProps.isDefault]: true,
    }))

    // updateTemplate()
  }

  const moreButton = (
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
          setTemplateNameManageMode(TEMPLATE_NAME_MANAGE_MODE.EDITING)
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
  console.log('------>', projectTemplates)
  return (
    <div className="settings-panel-project-template">
      <div className="settings-panel-project-template-container-select">
        <h3>Project template</h3>
        {options.length > 0 && (
          <Select
            placeholder="Select template"
            className={`${isModifyingTemplate ? 'select-unsaved' : ''}`}
            id="project-template"
            maxHeightDroplist={100}
            options={options}
            activeOption={activeOption}
            onSelect={onSelect}
          />
        )}
        {templateNameManageMode && (
          <input
            className="template-name"
            data-testid="template-name-input"
            value={templateName}
            onChange={onChangeTemplateName}
            autoFocus
          ></input>
        )}
      </div>
      <div className="settings-panel-project-template-container-buttons">
        {!templateNameManageMode ? (
          <>
            {!currentProjectTemplate[availableTemplateProps.isDefault] &&
              !isModifyingTemplate && (
                <button
                  className="template-button"
                  disabled={isRequestInProgress}
                  onClick={setCurrentProjectTemplateAsDefault}
                >
                  Set as default
                </button>
              )}
            {isModifyingTemplate && !isStandardTemplateBool && (
              <button
                className="template-button button-save-changes"
                disabled={isRequestInProgress}
                onClick={updateTemplate}
              >
                Save changes
              </button>
            )}
            {isModifyingTemplate && (
              <button
                className="template-button"
                data-testid="save-as-new-template"
                disabled={isRequestInProgress}
                onClick={() =>
                  setTemplateNameManageMode(TEMPLATE_NAME_MANAGE_MODE.NEW_ONE)
                }
              >
                Save as new
              </button>
            )}
            {!isStandardTemplateBool && moreButton}
          </>
        ) : (
          <>
            <button
              className="template-button"
              data-testid="create-template"
              onClick={
                templateNameManageMode === TEMPLATE_NAME_MANAGE_MODE.NEW_ONE
                  ? createTemplate
                  : updateTemplate
              }
            >
              Confirm
            </button>
            <button
              className="template-button"
              onClick={cancelSavingNewTemplate}
            >
              <IconClose />
            </button>
          </>
        )}
      </div>
    </div>
  )
}

ProjectTemplate.propTypes = {
  portalTarget: PropTypes.oneOfType([
    PropTypes.instanceOf(Element),
    PropTypes.node,
  ]),
}
