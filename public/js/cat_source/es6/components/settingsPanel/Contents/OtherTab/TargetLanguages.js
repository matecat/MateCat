import React, {useContext} from 'react'
import {Select} from '../../../common/Select'
import {CreateProjectContext} from '../../../createProject/CreateProjectContext'

export const TargetLanguages = () => {
  const {SELECT_HEIGHT, languages, targetLangs, setTargetLangs} =
    useContext(CreateProjectContext)

  return (
    <div className="options-box">
      <div className="option-description">
        <h3>Target languages</h3>Select targets.
      </div>
      <div className="options-select-container">
        <Select
          id="target-langs"
          name="target-langs"
          maxHeightDroplist={SELECT_HEIGHT}
          showSearchBar={true}
          multipleSelect="dropdown"
          options={languages}
          activeOptions={targetLangs}
          checkSpaceToReverse={false}
          onToggleOption={(option) =>
            setTargetLangs(
              targetLangs.some(({id}) => id === option.id)
                ? targetLangs.filter(({id}) => id !== option.id)
                : [...targetLangs, option],
            )
          }
        >
          {({name, code}) => ({
            row: (
              <div className="language-dropdown-item-container">
                <div className="code-badge">
                  <span>{code}</span>
                </div>

                <span>{name}</span>
              </div>
            ),
          })}
        </Select>
      </div>
    </div>
  )
}
