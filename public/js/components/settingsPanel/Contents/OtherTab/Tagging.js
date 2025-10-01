import React, {useContext, useMemo} from 'react'
import {Select} from '../../../common/Select'
import {CreateProjectContext} from '../../../createProject/CreateProjectContext'

export const Tagging = () => {
  const {SELECT_HEIGHT, tagging, setTagging} = useContext(CreateProjectContext)
  const types = useMemo(
    () => [
      {id: 'markup', name: 'Markup'},
      {id: 'percent_double_curly', name: 'Percent Double Curly'},
      {id: 'twig', name: 'Twig Double Curly'},
      {id: 'ruby_on_rails', name: 'Ruby on Rails'},
      {id: 'double_snail', name: 'Double Snail'},
      {id: 'double_square', name: 'Double Square'},
      {id: 'dollar_curly', name: 'Dollar Curly'},
      {id: 'single_curly', name: 'Single Curly'},
      {id: 'objective_c_ns', name: 'Objective CNS'},
      {id: 'double_percent', name: 'Double Percent'},
      {id: 'square_sprintf', name: 'Square Sprintf'},
      {id: 'sprintf', name: 'Sprintf'},
    ],
    [],
  )
  const taggingArray = [
    {id: 'all', name: 'All'},
    {id: 'none', name: 'None'},
  ].concat(types)

  const activeOptions = useMemo(() => {
    if (tagging?.length === 0) return [{id: 'all', name: 'All'}]
    else if (tagging?.length > 0) {
      return tagging.map((item) => types.find((type) => type.id === item))
    } else {
      return [{id: 'none', name: 'None'}]
    }
  }, [tagging, types])

  const toggleOption = (option) => {
    if (option.id === 'none') {
      setTagging({options: null})
    } else if (option.id === 'all') {
      setTagging({options: []})
    } else {
      activeOptions.includes(option)
        ? setTagging({
            options: tagging.filter((item) => item !== option.id),
          })
        : setTagging({options: [...(tagging || []), option.id]})
    }
  }

  return (
    <div className="options-box">
      <div className="option-description">
        <h3>Tagging syntaxes</h3>Choose the syntaxes for tagging. For example,
        selecting the {'{{tag}}'} syntax locks all text between {'{{and}}'} into
        a tag.
      </div>
      <div className="options-select-container">
        <Select
          id="project-tagging"
          name={'project-tagging'}
          isPortalDropdown={true}
          dropdownClassName="select-dropdown__wrapper-portal"
          maxHeightDroplist={SELECT_HEIGHT}
          showSearchBar={true}
          options={taggingArray}
          activeOptions={activeOptions}
          checkSpaceToReverse={true}
          onToggleOption={toggleOption}
          multipleSelect={'dropdown'}
        />
      </div>
    </div>
  )
}
