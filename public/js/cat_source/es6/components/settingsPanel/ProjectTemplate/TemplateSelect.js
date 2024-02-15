import React from 'react'
import PropTypes from 'prop-types'
import {Select} from '../../common/Select'

export const TemplateSelect = ({
  projectTemplates,
  setProjectTemplates,
  currentProjectTemplate,
  label,
  maxHeightDroplist,
}) => {
  const isModifyingTemplate = projectTemplates.some(
    ({isTemporary}) => isTemporary,
  )

  const options = projectTemplates
    .filter(({isTemporary}) => !isTemporary)
    .map(({id, name}) => ({
      id: id.toString(),
      name,
    }))
  const activeOption = currentProjectTemplate && {
    id: currentProjectTemplate.id.toString(),
    name: `${currentProjectTemplate.name}${isModifyingTemplate ? ' *' : ''}`,
  }

  const onSelect = (option) =>
    setProjectTemplates((prevState) =>
      prevState.map((template) => ({
        ...template,
        isSelected: template.id === parseInt(option.id),
      })),
    )

  return (
    <>
      {options.length > 0 && (
        <Select
          label={label}
          placeholder="Select template"
          className={`${isModifyingTemplate ? 'select-unsaved' : ''}`}
          id="project-template"
          checkSpaceToReverse={false}
          maxHeightDroplist={maxHeightDroplist ?? 100}
          options={options}
          activeOption={activeOption}
          onSelect={onSelect}
        />
      )}
    </>
  )
}

TemplateSelect.propTypes = {
  projectTemplates: PropTypes.array,
  setProjectTemplates: PropTypes.func,
  currentProjectTemplate: PropTypes.any,
  label: PropTypes.string,
  maxHeightDroplist: PropTypes.number,
}