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
import CommonUtils from '../../../utils/commonUtils'

const getImgUser = (userData) => {
  const {user_metadata: metadata, user} = userData

  return metadata ? (
    <img
      className="ui avatar image ui-user-dropdown-image"
      src={metadata.gplus_picture}
    />
  ) : (
    <a className="ui circular label">{CommonUtils.getUserShortName(user)}</a>
  )
}

export const BulkAssignToMember = ({teams, projects, successCallback}) => {
  const {id_team, id_assignee} = projects[0]
  const projectTeam = teams.find((team) => team.id === id_team)
  const memberActuallySelected = projectTeam.members.find(
    ({user}) => user.uid === id_assignee,
  )

  const [memberSelected, setMemberSelected] = useState({
    id: memberActuallySelected.id.toString(),
    name: (
      <>
        {getImgUser(memberActuallySelected)}{' '}
        {`${memberActuallySelected.user.first_name} ${memberActuallySelected.user.last_name}`}
      </>
    ),
  })

  const options = projectTeam.members.map((member) => ({
    id: member.id.toString(),
    name: (
      <>
        {getImgUser(member)}{' '}
        {`${member.user.first_name} ${member.user.last_name}`}
      </>
    ),
  }))

  const onSelect = (option) => setMemberSelected(option)

  return (
    <div className="content-bulk-modal">
      <Select
        name="assignee"
        label="Assign to member"
        placeholder="Select member"
        isPortalDropdown={true}
        options={options}
        activeOption={memberSelected}
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
          disabled={typeof memberSelected === 'undefined'}
          onClick={() =>
            successCallback({id_assignee: parseInt(memberSelected?.id)})
          }
        >
          Continue
        </Button>
      </div>
    </div>
  )
}

BulkAssignToMember.propTypes = {
  teams: PropTypes.array.isRequired,
  projects: PropTypes.array.isRequired,
  successCallback: PropTypes.func.isRequired,
}
