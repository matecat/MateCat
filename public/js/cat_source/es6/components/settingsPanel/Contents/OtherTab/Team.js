import React, {useContext, useEffect} from 'react'
import {Select} from '../../../common/Select'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import UserActions from '../../../../actions/UserActions'
import {CreateProjectContext} from '../../../createProject/CreateProjectContext'

export const Team = () => {
  const {user} = useContext(SettingsPanelContext)
  const {SELECT_HEIGHT, selectedTeam, setSelectedTeam} =
    useContext(CreateProjectContext)

  useEffect(() => {
    if (Array.isArray(user?.teams)) {
      setSelectedTeam(
        UserActions.getLastTeamSelected(
          user.teams.map((team) => ({...team, id: team.id.toString()})),
        ),
      )
    }
  }, [user?.teams, setSelectedTeam])

  return (
    <div className="options-box">
      <div className="option-description">
        <h3>Team</h3>Select what team the project should be created in.
      </div>
      <div className="options-select-container" data-testid="container-team">
        <Select
          id="project-team"
          name={'project-team'}
          isPortalDropdown={true}
          dropdownClassName="select-dropdown__wrapper-other-tab"
          showSearchBar={true}
          maxHeightDroplist={SELECT_HEIGHT}
          options={
            user?.teams
              ? user.teams.map((team) => ({
                  ...team,
                  id: team.id.toString(),
                }))
              : []
          }
          activeOption={selectedTeam}
          checkSpaceToReverse={true}
          isDisabled={!user || user.teams.length === 1}
          onSelect={(option) => {
            setSelectedTeam(option)
          }}
        />
      </div>
    </div>
  )
}
