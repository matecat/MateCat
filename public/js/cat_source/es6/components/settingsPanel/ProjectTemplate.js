import React, {useContext, useEffect, useState} from 'react'
import {Select} from '../common/Select'
import {SettingsPanelContext} from './SettingsPanelContext'
import IconClose from '../icons/IconClose'
import {createProjectTemplate} from '../../api/createProjectTemplate'
import {deleteProjectTemplate} from '../../api/deleteProjectTemplate'
import {isStandardTemplate} from '../../hooks/useProjectTemplates'

export const ProjectTemplate = () => {
  const {projectTemplates, setProjectTemplates, currentProjectTemplate} =
    useContext(SettingsPanelContext)

  const [isSavingNewTemplate, setIsSavingNewTemplate] = useState(false)
  const [templateName, setTemplateName] = useState('')
  const [isRequestInProgress, setIsRequestInProgress] = useState(false)

  const currentTemplate = projectTemplates.find(({isSelected}) => isSelected)
  const isModifyingTemplate = projectTemplates.some(
    ({isTemporary}) => isTemporary,
  )
  const isStandardTemplateBool = isStandardTemplate(currentTemplate)

  useEffect(() => {
    setIsSavingNewTemplate(false)
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

  const onChangeTemplateName = (e) => setTemplateName(e.currentTarget.value)

  const cancelSavingNewTemplate = () => {
    setIsSavingNewTemplate(false)
    setTemplateName('')
  }

  const deleteTemplate = () => {
    setIsRequestInProgress(true)
    deleteProjectTemplate(currentProjectTemplate.id)
      .then((data) =>
        setProjectTemplates((prevState) => {
          const id = parseInt(data.id)
          const indexDelete = prevState.findIndex(
            (template) => template.id === id && !template.isTemporary,
          )
          const previousIdToSelect = prevState.find(
            (template, index) =>
              index === (indexDelete - 1 > 0 ? indexDelete - 1 : 0),
          )?.id

          return prevState
            .filter((template) => template.id !== id)
            .map((template) => ({
              ...template,
              isSelected: template.id === previousIdToSelect,
            }))
        }),
      )
      .catch((error) => console.log(error))
      .finally(() => setIsRequestInProgress(false))
  }

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
        {isSavingNewTemplate && (
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
        {!isSavingNewTemplate ? (
          <>
            {isModifyingTemplate && !isStandardTemplateBool && (
              <button
                className="template-button button-save-changes"
                disabled={isRequestInProgress}
              >
                Save changes
              </button>
            )}
            {isModifyingTemplate && (
              <button
                className="template-button"
                data-testid="save-as-new-template"
                disabled={isRequestInProgress}
                onClick={() => setIsSavingNewTemplate(true)}
              >
                Save as new
              </button>
            )}
            {!isStandardTemplateBool && (
              <>
                <button
                  className="template-button"
                  data-testid="delete-template"
                  disabled={isRequestInProgress}
                  onClick={deleteTemplate}
                >
                  Delete
                </button>
                <button className="template-button">...</button>
              </>
            )}
          </>
        ) : (
          <>
            <button
              className="template-button"
              data-testid="create-template"
              onClick={createTemplate}
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
