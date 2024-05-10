import React, {useCallback, useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import CommonUtils from '../../utils/commonUtils'
import TEXT_UTILS from '../../utils/textUtils'
import {EmailsBadge} from '../common/EmailsBadge/EmailsBadge'
import IconSearch from '../icons/IconSearch'
import IconClose from '../icons/IconClose'
import ManageActions from '../../actions/ManageActions'
import ModalsActions from '../../actions/ModalsActions'
import IconEdit from '../icons/IconEdit'
import Checkmark from '../../../../../img/icons/Checkmark'
import Close from '../../../../../img/icons/Close'
import {EMAIL_PATTERN} from '../../constants/Constants'
import TeamsStore from '../../stores/TeamsStore'
import TeamConstants from '../../constants/TeamConstants'
import {
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
  Button,
} from '../common/Button/Button'

export const ModifyTeam = ({team}) => {
  const [teamState, setTeamState] = useState(team)
  const [teamName, setTeamName] = useState(teamState.get('name'))
  const [isModifyingName, setIsModifyingName] = useState(false)
  const [emailsCollection, setEmailsCollection] = useState([])
  const [searchMember, setSearchMember] = useState('')
  const [removeUserId, setRemoveUserId] = useState()
  const [resendInvitesCollection, setResendInvitesCollection] = useState([])

  useEffect(() => {
    const updateTeam = (teamUpdated) => {
      if (teamState.get('id') == teamUpdated.get('id'))
        setTeamState(teamUpdated)
    }

    TeamsStore.addListener(TeamConstants.UPDATE_TEAM, updateTeam)

    return () =>
      TeamsStore.removeListener(TeamConstants.UPDATE_TEAM, updateTeam)
  }, [teamState])

  const confirmRemoveMember = (userId) => {
    setRemoveUserId(userId)
  }

  const removeUser = (user) => {
    ManageActions.removeUserFromTeam(teamState, user)
    if (user.get('uid') === APP.USER.STORE.user.uid) {
      ModalsActions.onCloseModal()
    }
    setRemoveUserId()
  }

  const getUserList = () => {
    const teamMembers = teamState.get('members')
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

          const isRemovingMe =
            user.get('uid') == APP.USER.STORE.user.uid &&
            removeUserId == user.get('uid')
          const messageRemovingMe =
            isRemovingMe &&
            (teamState.get('members').size > 1
              ? 'Are you sure you want to leave this team?'
              : 'By removing the last member the team will be deleted. All projects will be moved to your Personal area.')

          const messageRemovingUser =
            !isRemovingMe &&
            removeUserId === user.get('uid') &&
            'Are you sure you want to remove this user?'

          const shouldShowMessage = messageRemovingMe || messageRemovingUser

          return (
            <li className="member-item" key={index}>
              {!shouldShowMessage && (
                <>
                  {userMetadata ? (
                    <img
                      className="member-avatar"
                      src={userMetadata.get('gplus_picture') + '?sz=80'}
                    />
                  ) : (
                    <span>{CommonUtils.getUserShortName(user)}</span>
                  )}
                  <div className="member-info">
                    {(user.get('first_name') || user.get('last_name')) && (
                      <span>{`${user.get('first_name')} ${user.get('last_name')}`}</span>
                    )}
                    <span>{user.get('email')}</span>
                  </div>
                </>
              )}

              {messageRemovingMe && (
                <span className="removing-user-message">
                  {messageRemovingMe}
                </span>
              )}
              {messageRemovingUser && (
                <span className="removing-user-message">
                  {messageRemovingUser}
                </span>
              )}

              {removeUserId === user.get('uid') ? (
                <div className="container-confirm-form">
                  <button
                    className="ui primary button confirm-button"
                    onClick={() => removeUser(user)}
                  >
                    <Checkmark size={12} />
                    Confirm
                  </button>

                  <button
                    className="ui button orange close-button"
                    onClick={() => setRemoveUserId()}
                  >
                    <Close size={18} />
                  </button>
                </div>
              ) : (
                <div
                  className="mini ui button button-remove"
                  onClick={() => confirmRemoveMember(user.get('uid'))}
                >
                  Remove
                </div>
              )}
            </li>
          )
        })}
      </ul>
    )
  }

  const getPendingInvitations = () => {
    if (
      !teamState.get('pending_invitations') ||
      !teamState.get('pending_invitations').size > 0
    )
      return

    return (
      <ul>
        {teamState.get('pending_invitations').map((email, index) => {
          const isAlreadyResendInvite = resendInvitesCollection.some(
            (resendEmail) => resendEmail === email,
          )

          return (
            <li className="member-item" key={index}>
              <div className="member-avatar">
                {email.substring(0, 1).toUpperCase()}
              </div>
              <div className="member-info">
                <span>{email}</span>
              </div>
              <div className="pending-member-remove">
                {isAlreadyResendInvite ? (
                  <span>Invite sent</span>
                ) : (
                  <>
                    <span>Pending user</span>
                    <div
                      className="mini ui button"
                      onClick={() => resendInvite(email)}
                    >
                      Resend Invite
                    </div>
                  </>
                )}
              </div>
            </li>
          )
        })}
      </ul>
    )
  }

  const saveTeamName = () => {
    if (teamName && teamName != teamState.get('name')) {
      ManageActions.changeTeamName(teamState.toJS(), teamName)
      setIsModifyingName(false)
    }
  }

  const onChangeSearchMember = (event) => {
    setSearchMember(event.target.value)
  }

  const onChangeAddMembers = useCallback(
    (emails) => setEmailsCollection(emails),
    [],
  )

  const inviteMembers = () => {
    ManageActions.addUserToTeam(teamState, emailsCollection)
    setEmailsCollection([])
  }

  const resendInvite = (email) => {
    ManageActions.addUserToTeam(teamState, email)
    setResendInvitesCollection((prevState) => [...prevState, email])
  }

  const userlist = getUserList()
  const pendingUsers = getPendingInvitations()

  return (
    <div className="team-modal">
      <div>
        <h2>Change Team Name</h2>
        <div className="team-name-container">
          {isModifyingName ? (
            <div className="container-input">
              <input
                className="team-modal-input"
                type="text"
                value={teamName}
                onChange={(e) => setTeamName(e.currentTarget.value)}
              />
              <Button
                type={BUTTON_TYPE.PRIMARY}
                size={BUTTON_SIZE.MEDIUM}
                onClick={saveTeamName}
              >
                Confirm
              </Button>
              <Button
                type={BUTTON_TYPE.WARNING}
                size={BUTTON_SIZE.ICON_STANDARD}
                onClick={() => setIsModifyingName(false)}
              >
                <IconClose />
              </Button>
            </div>
          ) : (
            <div className="container-button-edit">
              {teamName}
              <Button
                className="button-edit"
                mode={BUTTON_MODE.GHOST}
                size={BUTTON_SIZE.ICON_SMALL}
                onClick={() => setIsModifyingName(true)}
              >
                <IconEdit />
              </Button>
            </div>
          )}
        </div>
      </div>
      {teamState.get('type') !== 'personal' && (
        <div>
          <h2>Manage Members</h2>
          <EmailsBadge
            name="team"
            value={emailsCollection}
            onChange={onChangeAddMembers}
            placeholder="Add new people (separate email addresses with a comma)"
          />
          <button
            className="create-team ui primary button open button-invite"
            onClick={inviteMembers}
            disabled={
              !emailsCollection.length ||
              emailsCollection.some((email) => !EMAIL_PATTERN.test(email))
            }
          >
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
      <button
        className="create-team ui primary button open button-close"
        onClick={() => ModalsActions.onCloseModal()}
      >
        Close
      </button>
    </div>
  )
}

ModifyTeam.propTypes = {
  team: PropTypes.object.isRequired,
}
