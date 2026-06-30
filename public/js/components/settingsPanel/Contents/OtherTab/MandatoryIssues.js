import React, {useContext} from 'react'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {CreateProjectContext} from '../../../createProject/CreateProjectContext'
import {Select} from '../../../common/Select'

const OPTIONS = [
  {
    id: 'r1,r2',
    name: 'R1 + R2',
  },
  {
    id: 'r1',
    name: 'Only R1',
  },
  {
    id: 'r2',
    name: 'Only R2',
  },
  {
    id: 'none',
    name: 'None',
  },
]

export const MandatoryIssues = () => {
  const {currentProjectTemplate, modifyingCurrentTemplate} =
    useContext(SettingsPanelContext)

  const {SELECT_HEIGHT} = useContext(CreateProjectContext)

  const mandatoryIssue = currentProjectTemplate.mandatoryIssues
  const setMandatoryIssue = (value) =>
    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      mandatoryIssues: value === 'none' ? [] : value.split(','),
    }))

  return (
    <div className="options-box">
      <div className="option-description">
        <h3>Mandatory issue marking</h3>
        <p>lorem ipsum</p>
      </div>
      <div className="options-select-container" data-testid="container-team">
        <Select
          id="mandatory-issue"
          name="mandatory-issue"
          isPortalDropdown={true}
          dropdownClassName="select-dropdown__wrapper-portal"
          maxHeightDroplist={SELECT_HEIGHT}
          options={OPTIONS}
          activeOption={OPTIONS.find(
            ({id}) =>
              id ===
              (mandatoryIssue.length === 0 ? 'none' : mandatoryIssue.join(',')),
          )}
          checkSpaceToReverse={true}
          onSelect={(option) => setMandatoryIssue(option.id)}
        />
      </div>
    </div>
  )
}
