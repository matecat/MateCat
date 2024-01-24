import React, {useContext, useEffect, useState} from 'react'
import {Select} from '../common/Select'
import {SettingsPanelContext} from './SettingsPanelContext'
import IconClose from '../icons/IconClose'
import {useRef} from 'react'
import {useLayoutEffect} from 'react'
import {createProjectTemplate} from '../../api/createProjectTemplate'

export const ProjectTemplate = () => {
  const {projectTemplates, setProjectTemplates, currentProjectTemplate} =
    useContext(SettingsPanelContext)

  const [isSavingNewTemplate, setIsSavingNewTemplate] = useState(false)
  const [templateName, setTemplateName] = useState()

  const templateNameRef = useRef()

  const currentTemplate = projectTemplates.find(({isSelected}) => isSelected)
  const isModifyingTemplate = projectTemplates.some(
    ({isTemporary}) => isTemporary,
  )
  const isDefaultTemplate = currentTemplate?.is_default

  useEffect(() => {
    setIsSavingNewTemplate(false)
    setTemplateName()
  }, [currentProjectTemplate])

  useLayoutEffect(() => {
    if (isSavingNewTemplate) templateNameRef.current.focus()
  }, [isSavingNewTemplate])

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
      is_default,
      created_at,
      id,
      uid,
      modified_at,
      isTemporary,
      isSelected,
      ...newTemplate
    } = {...currentProjectTemplate, name: templateName}
    /* eslint-enable no-unused-vars */
    createProjectTemplate(newTemplate)
      .then((data) => console.log(data))
      .catch((error) => console.log(error))
  }

  const onChangeTemplateName = (e) => setTemplateName(e.currentTarget.value)

  const cancelSavingNewTemplate = () => {
    setIsSavingNewTemplate(false)
    setTemplateName()
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
            ref={templateNameRef}
            className="template-name"
            value={templateName}
            onChange={onChangeTemplateName}
          ></input>
        )}
      </div>
      <div className="settings-panel-project-template-container-buttons">
        {!isSavingNewTemplate ? (
          <>
            {isModifyingTemplate && !isDefaultTemplate && (
              <button className="template-button button-save-changes">
                Save changes
              </button>
            )}
            {isModifyingTemplate && (
              <button
                className="template-button"
                onClick={() => setIsSavingNewTemplate(true)}
              >
                Save as new
              </button>
            )}
            {!isDefaultTemplate && (
              <button className="template-button">...</button>
            )}
          </>
        ) : (
          <>
            <button className="template-button" onClick={createTemplate}>
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
