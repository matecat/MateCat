import React, {useContext} from 'react'
import {Select} from '../../../common/Select'
import {SubTemplatesContext} from './SubTemplate'

export const SubTemplateSelect = () => {
  const {templates, setTemplates, currentTemplate} =
    useContext(SubTemplatesContext)

  const isModifyingTemplate = templates.some(({isTemporary}) => isTemporary)

  const options = templates
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
    setTemplates((prevState) =>
      prevState.map((template) => ({
        ...template,
        isSelected: template.id === parseInt(option.id),
      })),
    )

  return (
    <>
      {options.length > 0 && (
        <Select
          placeholder="Select template"
          className={`settings-panel-subtemplates-select${isModifyingTemplate ? ' settings-panel-subtemplates-select-unsaved' : ''}`}
          id="project-template"
          tooltipPosition="right"
          checkSpaceToReverse={false}
          options={options}
          activeOption={activeOption}
          onSelect={onSelect}
          maxHeightDroplist={300}
        />
      )}
    </>
  )
}
