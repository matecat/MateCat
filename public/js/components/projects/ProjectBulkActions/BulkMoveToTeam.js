import React, {useState} from 'react'
import PropTypes from 'prop-types'
import {Select} from '../../common/Select'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../../common/Button/Button'
import ModalsActions from '../../../actions/ModalsActions'

const BulkMoveToTeam = ({teams, successCallback}) => {
  const [teamSelected, setTeamSelected] = useState()

  const options = teams.map((team) => ({
    id: team.id.toString(),
    name: team.name,
  }))

  const onSelect = (option) => setTeamSelected(option)

  return (
    <div className="content-bulk-modal">
      <Select
        name="teams"
        label="Move to team"
        placeholder="Select team"
        isPortalDropdown={true}
        options={options}
        activeOption={teamSelected}
        checkSpaceToReverse={false}
        onSelect={onSelect}
        maxHeightDroplist={300}
      />
      <div className="content-bulk-modal-control">
        <Button
          mode={BUTTON_MODE.OUTLINE}
          size={BUTTON_SIZE.MEDIUM}
          onClick={() => ModalsActions.onCloseModal()}
        >
          Cancel
        </Button>
        <Button
          type={BUTTON_TYPE.PRIMARY}
          size={BUTTON_SIZE.MEDIUM}
          disabled={typeof teamSelected === 'undefined'}
          onClick={() => successCallback({id_team: teamSelected?.id})}
        >
          Continue
        </Button>
      </div>
    </div>
  )
}

BulkMoveToTeam.propTypes = {
  teams: PropTypes.array.isRequired,
  successCallback: PropTypes.func.isRequired,
}

export default BulkMoveToTeam
