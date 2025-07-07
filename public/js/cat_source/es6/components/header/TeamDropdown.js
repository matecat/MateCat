import React, {useCallback, useContext, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'
import {BUTTON_TYPE, Button} from '../common/Button/Button'
import IconDown from '../icons/IconDown'
import ManageActions from '../../actions/ManageActions'
import UserActions from '../../actions/UserActions'
import IconAdd from '../icons/IconAdd'
import IconSettings from '../icons/IconSettings'
import ModalsActions from '../../actions/ModalsActions'

export const TeamDropdown = ({isManage = true, showModals = true}) => {
  const {userInfo, setUserInfo} = useContext(ApplicationWrapperContext)

  const [isDropdownVisible, setIsDropdownVisible] = useState(false)

  const triggerRef = useRef()

  const {teams = []} = userInfo ?? {}
  const selectedTeam = teams.find(({isSelected}) => isSelected)

  const closeDropdown = useCallback((e) => {
    if (e) e.stopPropagation()
    if (triggerRef.current && !triggerRef.current.contains(e?.target)) {
      window.eventHandler.removeEventListener(
        'click.teamdropdown',
        closeDropdown,
      )

      setIsDropdownVisible(false)
    }
  }, [])

  const toggleDropdown = () => {
    if (isDropdownVisible) {
      window.eventHandler.removeEventListener(
        'click.teamdropdown',
        closeDropdown,
      )
    } else {
      window.eventHandler.addEventListener('click.teamdropdown', closeDropdown)
    }

    setIsDropdownVisible((prevState) => !prevState)
  }

  const onChangeTeam = (team) => {
    setUserInfo((prevState) => ({
      ...prevState,
      teams: prevState.teams.map((teamItem) => ({
        ...teamItem,
        isSelected: teamItem.id === team.id,
      })),
    }))

    if (isManage) {
      window.scrollTo(0, 0)
      ManageActions.changeTeam(team)
    } else {
      UserActions.changeTeamFromUploadPage(team)
    }
  }

  const openCreateTeams = () => {
    ModalsActions.openCreateTeamModal()
  }

  const openModifyTeam = (event, team) => {
    event.stopPropagation()
    event.preventDefault()
    ManageActions.openModifyTeamModal(team)
  }

  return (
    <div
      data-testid="team-select"
      className={`team-dropdown${isDropdownVisible ? ' open' : ''}`}
    >
      <Button
        ref={triggerRef}
        className={`trigger-button${isDropdownVisible ? ' open' : ''}`}
        type={BUTTON_TYPE.DEFAULT}
        onClick={toggleDropdown}
      >
        {selectedTeam?.name ?? 'Choose team'}
        <IconDown width={14} height={14} />
      </Button>
      <div className={`dropdown${isDropdownVisible ? ' open' : ''}`}>
        <ul>
          {showModals && (
            <li className="create-new-team" onClick={openCreateTeams}>
              Create new team <IconAdd size={22} />
            </li>
          )}
          {teams.map((team) => (
            <li
              key={team.id}
              className={`${team.id === selectedTeam?.id ? 'active' : ''}`}
              onClick={() => onChangeTeam(team)}
            >
              {team.name}
              {team.type !== 'personal' && (
                <div
                  className="container-icon-settings"
                  onClick={(event) => openModifyTeam(event, team)}
                >
                  <IconSettings size={16} />
                </div>
              )}
            </li>
          ))}
        </ul>
      </div>
    </div>
  )
}

TeamDropdown.propTypes = {
  isManage: PropTypes.bool,
  showModals: PropTypes.bool,
}
