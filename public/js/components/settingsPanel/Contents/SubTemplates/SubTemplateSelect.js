import React, {useContext, useRef} from 'react'
import {Select} from '../../../common/Select'
import {SubTemplatesContext} from './SubTemplate'
import HelpCircle from '../../../../../img/icons/HelpCircle'
import Tooltip from '../../../common/Tooltip'

export const SubTemplateSelect = () => {
  const {templates, setTemplates, currentTemplate} =
    useContext(SubTemplatesContext)

  const helpRef = useRef()

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
        <div className="settings-panel-subtemplates-container-select">
          <span>
            Applied configuration
            <Tooltip content="When a configuration name appears in black, it means the current settings match that configuration. If the name is blue with an asterisk at the end (*), the settings don’t match the configuration, but they’ll still be applied to the project you’re creating.">
              <span
                ref={helpRef}
                className="settings-panel-subtemplates-container-select-help"
              >
                <HelpCircle />
              </span>
            </Tooltip>
          </span>
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
        </div>
      )}
    </>
  )
}
