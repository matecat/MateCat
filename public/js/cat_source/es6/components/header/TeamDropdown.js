import React, {useCallback, useContext, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper'
import {BUTTON_TYPE, Button} from '../common/Button/Button'
import IconDown from '../icons/IconDown'
import ManageActions from '../../actions/ManageActions'
import UserActions from '../../actions/UserActions'

export const TeamDropdown = ({isManage, showModals, changeTeam}) => {
  const {isUserLogged, userInfo, setUserInfo} = useContext(
    ApplicationWrapperContext,
  )

  const [isDropdownVisible, setIsDropdownVisible] = useState(false)

  const triggerRef = useRef()

  const {teams = []} = userInfo ?? {}
  const selectedTeam = teams.find(({isSelected}) => isSelected)

  const closeDropdown = useCallback((e) => {
    if (e) e.stopPropagation()
    if (triggerRef.current && !triggerRef.current.contains(e?.target)) {
      window.eventHandler.removeEventListener(
        'mouseup.teamdropdown',
        closeDropdown,
      )

      setIsDropdownVisible(false)
    }
  }, [])

  const toggleDropdown = () => {
    if (isDropdownVisible) {
      window.eventHandler.removeEventListener(
        'mousedown.teamdropdown',
        closeDropdown,
      )
    } else {
      window.eventHandler.addEventListener(
        'mouseup.teamdropdown',
        closeDropdown,
      )
    }

    setIsDropdownVisible((prevState) => !prevState)
  }

  const onChangeTeam = (id) => {
    const selectedTeam = teams.find((team) => team.id === id)
    setUserInfo((prevState) => ({
      ...prevState,
      teams: prevState.teams.map((team) => ({
        ...team,
        isSelected: team.id === selectedTeam.id,
      })),
    }))

    if (isManage) {
      window.scrollTo(0, 0)
      ManageActions.changeTeam(selectedTeam)
    } else {
      UserActions.changeTeamFromUploadPage(selectedTeam)
    }
  }

  return (
    <div className={`team-dropdown${isDropdownVisible ? ' open' : ''}`}>
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
        {showModals && (
          <>
            <Button type={BUTTON_TYPE.INFO}>Create new team</Button>
            <div className="divider"></div>
          </>
        )}
        <ul>
          {teams.map((team) => (
            <li
              key={team.id}
              className={`${team.id === selectedTeam?.id ? 'active' : ''}`}
              onClick={() => onChangeTeam(team.id)}
            >
              {team.name}
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
  changeTeam: PropTypes.bool,
}
