import React, {useCallback, useContext, useState} from 'react'
import {
  EmailsBadge,
  SPECIALS_SEPARATORS,
} from '../common/EmailsBadge/EmailsBadge'
import CommonUtils from '../../utils/commonUtils'
import {EMAIL_PATTERN} from '../../constants/Constants'
import ManageActions from '../../actions/ManageActions'
import ModalsActions from '../../actions/ModalsActions'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'

export const CreateTeam = () => {
  const {userInfo} = useContext(ApplicationWrapperContext)

  const [teamName, setTeamName] = useState('')
  const [emailsCollection, setEmailsCollection] = useState([])

  const onChangeAddMembers = useCallback(
    (emails) => setEmailsCollection(emails),
    [],
  )

  const sendCreate = () => {
    ManageActions.createTeam(teamName, emailsCollection)
    ModalsActions.onCloseModal()
  }

  const {user, metadata} = userInfo

  const isDisabledCreation =
    !teamName ||
    !emailsCollection.length ||
    emailsCollection.some((email) => !EMAIL_PATTERN.test(email))

  const handleEnterKey = ({key}) => {
    if (key === 'Enter' && !isDisabledCreation)
      setTimeout(() => sendCreate(), 100)
  }

  return (
    <div
      className="team-modal team-modal-create"
      tabIndex={1}
      onKeyDown={handleEnterKey}
    >
      <p>
        Create a team and invite your colleagues to share and manage projects.
      </p>
      <div>
        <h2>Assign a name to your team</h2>
        <div className="team-name-container">
          <input
            className="team-modal-input"
            placeholder="Team name"
            type="text"
            value={teamName}
            onChange={(e) => setTeamName(e.currentTarget.value)}
          />
        </div>
      </div>
      <div>
        <h2>Add members</h2>
        <EmailsBadge
          name="team"
          value={emailsCollection}
          separators={[',', SPECIALS_SEPARATORS.EnterKey]}
          onChange={onChangeAddMembers}
          placeholder="Insert email or emails separated by commas"
        />
      </div>
      <div>
        <div className="member-item">
          {metadata ? (
            <img className="member-avatar" src={metadata.gplus_picture} />
          ) : (
            <span>{CommonUtils.getUserShortName(user)}</span>
          )}
          <div className="member-info">
            {(user.first_name || user.last_name) && (
              <span>{`${user.first_name} ${user.last_name}`}</span>
            )}
            <span>{user.email}</span>
          </div>
        </div>
      </div>
      <button
        className="create-team ui primary button open button-close"
        disabled={isDisabledCreation}
        onClick={sendCreate}
      >
        Create
      </button>
    </div>
  )
}
