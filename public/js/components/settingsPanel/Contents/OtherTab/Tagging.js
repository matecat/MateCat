import React, {useCallback, useContext, useEffect, useMemo, useRef} from 'react'
import {Select} from '../../../common/Select'
import {CreateProjectContext} from '../../../createProject/CreateProjectContext'
import {SettingsPanelContext} from '../../SettingsPanelContext'

export const taggingTypes = [
  {id: 'markup', name: 'Markup', default: true},
  {id: 'percent_double_curly', name: 'Percent Double Curly', default: true},
  {id: 'twig', name: 'Twig Double Curly', default: true},
  {id: 'ruby_on_rails', name: 'Ruby on Rails', default: true},
  {id: 'double_snail', name: 'Double Snail', default: true},
  {id: 'double_square', name: 'Double Square', default: true},
  {id: 'dollar_curly', name: 'Dollar Curly', default: true},
  {id: 'single_curly', name: 'Single Curly', default: false},
  {id: 'objective_c_ns', name: 'Objective CNS', default: true},
  {id: 'double_percent', name: 'Double Percent', default: true},
  {id: 'square_sprintf', name: 'Square Sprintf', default: true},
  {id: 'sprintf', name: 'Sprintf', default: true},
]

export const Tagging = () => {
  const {SELECT_HEIGHT} = useContext(CreateProjectContext)
  const {currentProjectTemplate, modifyingCurrentTemplate} =
    useContext(SettingsPanelContext)

  const setTagging = useCallback(
    ({options}) =>
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        subfiltering_handlers: options,
      })),
    [modifyingCurrentTemplate],
  )

  const activeOptions = useMemo(() => {
    const tagging = currentProjectTemplate?.subfilteringHandlers
    if (tagging?.length === 0)
      return taggingTypes.filter((type) => type.default)
    else if (tagging?.length > 0) {
      return tagging.map((item) =>
        taggingTypes.find((type) => type.id === item),
      )
    } else {
      return []
    }
  }, [currentProjectTemplate?.subfilteringHandlers])

  const toggleOption = (option) => {
    const optionsIds = activeOptions.map((option) => option.id)
    if (optionsIds.includes(option.id)) {
      optionsIds.splice(optionsIds.indexOf(option.id), 1)
    } else {
      optionsIds.push(option.id)
    }
    const defaultTaggingIds = taggingTypes
      .filter((type) => type.default)
      .map((type) => type.id)

    if (optionsIds.length === 0) {
      setTagging({options: null})
    } else if (
      defaultTaggingIds.every((id) => optionsIds.includes(id)) &&
      optionsIds.length === defaultTaggingIds.length
    ) {
      setTagging({options: []})
    } else {
      setTagging({options: optionsIds})
    }
  }

  return (
    <div className="options-box">
      <div className="option-description">
        <h3>Tagging syntaxes</h3>
        <p>
          Choose the syntaxes for tagging. For example, selecting the{' '}
          {'{{tag}}'} syntax locks all text between {'{{and}}'} into a tag.
        </p>
      </div>
      <div className="options-select-container">
        <Select
          id="project-tagging"
          name={'project-tagging'}
          isPortalDropdown={true}
          dropdownClassName="select-dropdown__wrapper-portal"
          maxHeightDroplist={SELECT_HEIGHT}
          showSearchBar={true}
          options={taggingTypes}
          activeOptions={activeOptions}
          checkSpaceToReverse={true}
          onToggleOption={toggleOption}
          multipleSelect={'dropdown'}
        />
      </div>
    </div>
  )
}
