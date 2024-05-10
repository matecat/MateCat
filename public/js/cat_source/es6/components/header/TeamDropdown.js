import React, {useContext, useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import UserStore from '../../stores/UserStore'
import ManageConstants from '../../constants/ManageConstants'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper'
import {BUTTON_TYPE, Button} from '../common/Button/Button'

export const TeamDropdown = ({isManage, showModals, changeTeam}) => {
  const {isUserLogged, userInfo} = useContext(ApplicationWrapperContext)

  const [selectedTeamId, setSelectedTeamId] = useState()
  const [isDropdownVisible, setIsDropdownVisible] = useState(false)

  const {teams = []} = userInfo ?? {}
  const selectedTeam = teams.find(({isSelected}) => isSelected)

  //   useEffect(() => {
  //     const initPopup = () => {
  //         const teams = userInfo.teams;

  //     if (teams.length == 1 && showModals && showPopup) {
  //       let tooltipTex =
  //         "<h4 class='header'>Add your first team!</h4>" +
  //         "<div class='content'>" +
  //         '<p>Create a team and invite your colleagues to share and manage projects.</p>' +
  //         "<a class='close-popup-teams'>Got it!</a>" +
  //         '</div>'
  //       $(this.dropdownTeams)
  //         .popup({
  //           on: 'click',
  //           onHidden: self.removePopup,
  //           html: tooltipTex,
  //           closable: false,
  //           onCreate: self.onCreatePopup,
  //           className: {
  //             popup: 'ui popup cta-create-team',
  //           },
  //         })
  //         .popup('show')
  //       this.showPopup = false
  //     }
  //     }

  //     UserStore.addListener(ManageConstants.OPEN_INFO_TEAMS_POPUP, initPopup)
  //   }, [userInfo?.teams])

  const toggleDropdown = () => setIsDropdownVisible((prevState) => !prevState)

  return (
    <div className={`team-dropdown${isDropdownVisible ? ' open' : ''}`}>
      <Button type={BUTTON_TYPE.DEFAULT} onClick={toggleDropdown}>
        Choose team
      </Button>
      <div className="dropdown">
        {showModals && (
          <>
            <Button type={BUTTON_TYPE.INFO}>Create new team</Button>
            <div className="divider"></div>
          </>
        )}
        <ul>
          {teams.map((team) => (
            <li key={team.id}>{team.name}</li>
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
