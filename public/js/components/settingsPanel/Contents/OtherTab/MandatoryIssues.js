import React, {useContext} from 'react'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {CreateProjectContext} from '../../../createProject/CreateProjectContext'
import {Select} from '../../../common/Select'

const ALL_REVISION_ROUNDS = ['r1', 'r2']

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

  // A job with no stored value requires an issue in every round, so show that rather than
  // "None" — an empty array is the one and only way to express "no round requires an issue".
  const mandatoryIssue = Array.isArray(currentProjectTemplate.mandatoryIssues)
    ? currentProjectTemplate.mandatoryIssues
    : ALL_REVISION_ROUNDS

  const setMandatoryIssue = (value) =>
    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      mandatoryIssues: value === 'none' ? [] : value.split(','),
    }))

  const activeOptionId =
    mandatoryIssue.length === 0
      ? 'none'
      : // Match regardless of the stored order, which the backend does not constrain.
        ALL_REVISION_ROUNDS.filter((round) =>
          mandatoryIssue.includes(round),
        ).join(',')

  return (
    <div className="options-box">
      <div className="option-description">
        <h3>Mandatory issue marking</h3>
        <p>
          Select which review rounds require adding an issue before approving a
          segment.
        </p>
      </div>
      <div className="options-select-container">
        <Select
          id="mandatory-issue"
          name="mandatory-issue"
          isPortalDropdown={true}
          dropdownClassName="select-dropdown__wrapper-portal"
          maxHeightDroplist={SELECT_HEIGHT}
          options={OPTIONS}
          activeOption={OPTIONS.find(({id}) => id === activeOptionId)}
          checkSpaceToReverse={true}
          onSelect={(option) => setMandatoryIssue(option.id)}
        />
      </div>
    </div>
  )
}
