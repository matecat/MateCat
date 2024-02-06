import React, {useContext} from 'react'
import PropTypes from 'prop-types'
import {Select} from '../../../common/Select'
import {SettingsPanelContext} from '../../SettingsPanelContext'

export const Team = ({selectedTeam, setSelectedTeam}) => {
  const {user} = useContext(SettingsPanelContext)
  console.log(user.teams)
  return (
    <div className="options-box">
      <div className="option-description">
        <h3>Team</h3>Description
      </div>
      <div className="options-select-container">
        <Select
          id="project-team"
          name={'project-team'}
          showSearchBar={true}
          options={
            user?.teams
              ? user.teams.map((team) => ({
                  ...team,
                  id: team.id.toString(),
                }))
              : []
          }
          activeOption={selectedTeam}
          checkSpaceToReverse={false}
          isDisabled={!user || user.teams.length === 1}
          onSelect={(option) => setSelectedTeam(option)}
        />
      </div>
    </div>
  )
}

Team.propTypes = {
  selectedTeam: PropTypes.object,
  setSelectedTeam: PropTypes.func,
}
