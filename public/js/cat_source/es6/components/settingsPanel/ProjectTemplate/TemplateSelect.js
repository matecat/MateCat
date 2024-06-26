import React, {createRef, useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import {Select} from '../../common/Select'
import {IconPin} from '../../icons/IconPin'
import Check from '../../../../../../img/icons/Check'

export const TemplateSelect = ({
  projectTemplates,
  setProjectTemplates,
  currentProjectTemplate,
  label,
  maxHeightDroplist,
}) => {
  const [isLoadingTemplates, setIsLoadingTemplates] = useState(false)

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

  useEffect(() => {
    let tmOut
    if (!projectTemplates.length) {
      tmOut = setTimeout(() => setIsLoadingTemplates(true), 200)
    } else {
      clearTimeout(tmOut)
      setIsLoadingTemplates(false)
    }

    return () => clearTimeout(tmOut)
  }, [projectTemplates.length])

  return (
    <>
      <Select
        label={label}
        placeholder="Select template"
        className={`project-template-select${isModifyingTemplate ? ' project-template-select-unsaved' : ''}`}
        id="project-template"
        tooltipPosition="right"
        checkSpaceToReverse={false}
        maxHeightDroplist={maxHeightDroplist ?? 100}
        options={options}
        activeOption={activeOption}
        onSelect={onSelect}
        isDisabled={isLoadingTemplates}
      >
        {({id, name, showActiveOptionIcon}) => {
          const {isDefault} =
            projectTemplates
              .filter(({isTemporary}) => !isTemporary)
              .find((template) => template.id === parseInt(id)) ?? {}

          if (isDefault) {
            const labelRef = createRef()
            return {
              row: (
                <>
                  <div className="select-item-default">
                    <span ref={labelRef} className="select-item-default-label">
                      {name}
                    </span>
                    <IconPin />
                  </div>
                  {showActiveOptionIcon && <Check size={16} />}
                </>
              ),
              getElementToEllipsis: () => labelRef.current,
            }
          }
        }}
      </Select>
      {isLoadingTemplates && !config.is_cattool && (
        <div className="project-template-select-loading">
          <div className="project-template-select-loading-icon"></div>
        </div>
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
