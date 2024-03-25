import React, {useState} from 'react'
import PropTypes from 'prop-types'
import CommonUtils from '../../utils/commonUtils'
import TEXT_UTILS from '../../utils/textUtils'
import {EmailsBadge} from '../common/EmailsBadge/EmailsBadge'
import IconSearch from '../icons/IconSearch'
import IconClose from '../icons/IconClose'
import ManageActions from '../../actions/ManageActions'
import ModalsActions from '../../actions/ModalsActions'
import IconEdit from '../icons/IconEdit'

export const NewModifyTeam = ({team, hideChangeName}) => {
  const [teamName, setTeamName] = useState(team.get('name'))
  const [emailsCollection, setEmailsCollection] = useState([])
  const [searchMember, setSearchMember] = useState('')
  const [removeUserId, setRemoveUserId] = useState()

  const confirmRemoveMember = (userId) => {
    setRemoveUserId(userId)
  }

  const removeUser = (user) => {
    ManageActions.removeUserFromTeam(this.state.team, user)
    if (user.get('uid') === APP.USER.STORE.user.uid) {
      ModalsActions.onCloseModal()
    }
    setRemoveUserId()
  }

  const getUserList = () => {
    const teamMembers = team.get('members')
    const filteredMembers = teamMembers.filter((member) => {
      const user = member.get('user')
      const fullName = `${user.get('first_name')} ${user.get('last_name')}`
      const regex = new RegExp(TEXT_UTILS.escapeRegExp(searchMember), 'i')
      if (!searchMember) return true
      else return regex.test(fullName) || regex.test(user.get('email'))
    })

    if (!filteredMembers.size)
      return <span className="no-result">No results!</span>

    return (
      <ul>
        {filteredMembers.map((member, index) => {
          const user = member.get('user')
          const userMetadata = member.get('user_metadata')

          return (
            <li key={index}>
              {userMetadata ? (
                <img
                  className="member-avatar"
                  src={userMetadata.get('gplus_picture') + '?sz=80'}
                />
              ) : (
                <span>{CommonUtils.getUserShortName(user.toJS())}</span>
              )}
              <div>
                <span>{`${user.get('first_name')} ${user.get('last_name')}`}</span>
                <span>{user.get('email')}</span>
              </div>
            </li>
          )
        })}
      </ul>
    )

    /* return filteredMembers.map(function (member) {
      const confirmModule = (
        <>
          <div
            className="ui mini primary button"
            onClick={() => removeUser(user)}
          >
            <i className="icon-check icon" />
            Confirm
          </div>
          <div
            className="ui icon mini button red"
            onClick={() => setRemoveUserId()}
          >
            <i className="icon-cancel3 icon" />
          </div>
        </>
      )

      const user = member.get('user')
      if (
        user.get('uid') == APP.USER.STORE.user.uid &&
        removeUserId == user.get('uid')
      ) {
        if (team.get('members').size > 1) {
          return (
            <div className="item" key={'user' + user.get('uid')}>
              <div className="right floated content top-5 bottom-5">
                {confirmModule}
              </div>
              <div className="content pad-top-10 pad-bottom-8">
                Are you sure you want to leave this team?
              </div>
            </div>
          )
        } else {
          return (
            <div className="item" key={'user' + user.get('uid')}>
              <div className="right floated content top-20 bottom-5">
                {confirmModule}
              </div>
              <div className="content pad-top-10 pad-bottom-8">
                By removing the last member the team will be deleted. All
                projects will be moved to your Personal area.
              </div>
            </div>
          )
        }
      } else if (removeUserId == user.get('uid')) {
        return (
          <div className="item" key={'user' + user.get('uid')}>
            <div className="right floated content top-5 bottom-5">
              {confirmModule}
            </div>
            <div className="content pad-top-10 pad-bottom-8">
              Are you sure you want to remove this user?
            </div>
          </div>
        )
      } else {
        return (
          <div className="item" key={'user' + user.get('uid')}>
            <div
              className="mini ui button right floated"
              onClick={() => confirmRemoveMember(user.get('uid'))}
            >
              Remove
            </div>

            {member.get('user_metadata') ? (
              <img
                className="ui mini circular image"
                src={
                  member.get('user_metadata').get('gplus_picture') + '?sz=80'
                }
              />
            ) : (
              <div className="ui tiny image label">
                {CommonUtils.getUserShortName(user.toJS())}
              </div>
            )}

            <div className="middle aligned content">
              <div className="content user">
                {' ' + user.get('first_name') + ' ' + user.get('last_name')}
              </div>
              <div className="content email-user-invited">
                {user.get('email')}
              </div>
            </div>
          </div>
        )
      }
    }) */
  }

  const getPendingInvitations = () => {
    if (
      !team.get('pending_invitations') ||
      !team.get('pending_invitations').size > 0
    )
      return
    return team.get('pending_invitations').map(function (mail, i) {
      // const inviteResended = self.state.resendInviteArray.indexOf(mail) > -1
      const inviteResended = true
      return (
        <div className="item pending-invitation" key={'user-invitation' + i}>
          <div className="ui tiny image label">
            {mail.substring(0, 1).toUpperCase()}
          </div>
          <span className="email content user">{mail}</span>
          <div>
            {inviteResended ? (
              <span className="content pending-msg">Invite sent</span>
            ) : (
              <>
                <span className="content pending-msg">Pending user</span>
                <div
                  className="mini ui button right floated"
                  // onClick={self.resendInvite.bind(self, mail)}
                >
                  Resend Invite
                </div>
              </>
            )}
          </div>
        </div>
      )
    })
  }

  const onKeyUpTeamName = (event) => {}

  const onChangeSearchMember = (event) => {
    setSearchMember(event.target.value)
  }

  const onChangeInviteMembers = (emails) => console.log(emails)

  const applyChanges = () => {}

  const userlist = getUserList()
  const pendingUsers = getPendingInvitations()

  return (
    <div className="team-modal">
      <div>
        <h2>Change Team Name</h2>
        <div className="team-name-container">
          <input
            className="team-modal-input"
            type="text"
            value={teamName}
            onChange={(e) => setTeamName(e.currentTarget.value)}
            onKeyUp={onKeyUpTeamName}
          />
          {team.get('name') !== teamName && <IconEdit />}
        </div>
      </div>
      {team.get('type') !== 'personal' && (
        <div>
          <h2>Manage Members</h2>
          <EmailsBadge
            name="team"
            value={emailsCollection}
            onChange={onChangeInviteMembers}
            placeholder="Add new people (separate email addresses with a comma)"
          />
          <button className="create-team ui primary button open button-invite">
            Invite members
          </button>
        </div>
      )}

      <div>
        <div className="search-members">
          <IconSearch />
          <input
            name="search_member"
            placeholder="Search Member"
            value={searchMember}
            onChange={onChangeSearchMember}
          />
          <div
            className={`reset_button ${
              searchMember ? 'reset_button--visible' : 'reset_button--hidden'
            }`}
            onClick={() => setSearchMember('')}
          >
            <IconClose />
          </div>
        </div>
        <div className="members-list">
          {pendingUsers}
          {userlist}
        </div>
      </div>
    </div>
  )
}

NewModifyTeam.propTypes = {
  team: PropTypes.object.isRequired,
  hideChangeName: PropTypes.bool,
}
