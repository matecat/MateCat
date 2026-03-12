import {fromJS} from 'immutable'
import PropTypes from 'prop-types'
import React, {useEffect} from 'react'
import ProjectsStore from '../../stores/ProjectsStore'
import ManageConstants from '../../constants/ManageConstants'
import {ProjectsBulkActions} from '../projects/ProjectsBulkActions'
import {ProjectContainer} from './ProjectContainer'
import UserConstants from '../../constants/UserConstants'
import UserStore from '../../stores/UserStore'

export const ProjectsContainer = ({
  team,
  teams,
  downloadTranslationFn,
  selectedUser,
  fetchingProjects,
}) => {
  const [projects, setProjects] = React.useState(fromJS([]))
  const [teamState, setTeamState] = React.useState(team)
  const [teamsState, setTeamsState] = React.useState(teams)

  useEffect(() => {
    const renderProjects = (projects, team, teams, hideSpinner, filtering) => {
      setProjects(projects)
    }
    const updateProjects = (projects) => setProjects(projects)
    const updateTeams = (teams) => setTeamsState(teams)
    const updateTeam = (team) => {
      if (team.get('id') === teamState.get('id')) {
        setTeamState(team)
      }
    }
    const hideSpinner = () => {}
    const showProjectsReloadSpinner = () => {}

    ProjectsStore.addListener(ManageConstants.RENDER_PROJECTS, renderProjects)
    ProjectsStore.addListener(ManageConstants.UPDATE_PROJECTS, updateProjects)
    ProjectsStore.addListener(ManageConstants.NO_MORE_PROJECTS, hideSpinner)
    ProjectsStore.addListener(
      ManageConstants.SHOW_RELOAD_SPINNER,
      showProjectsReloadSpinner,
    )
    UserStore.addListener(UserConstants.UPDATE_TEAM, updateTeam)
    UserStore.addListener(UserConstants.UPDATE_TEAMS, updateTeams)
    UserStore.addListener(UserConstants.RENDER_TEAMS, updateTeams)

    return () => {
      ProjectsStore.removeListener(
        ManageConstants.RENDER_PROJECTS,
        renderProjects,
      )
      ProjectsStore.removeListener(
        ManageConstants.UPDATE_PROJECTS,
        updateProjects,
      )
      ProjectsStore.removeListener(
        ManageConstants.NO_MORE_PROJECTS,
        hideSpinner,
      )
      ProjectsStore.removeListener(
        ManageConstants.SHOW_RELOAD_SPINNER,
        showProjectsReloadSpinner,
      )
      UserStore.removeListener(UserConstants.UPDATE_TEAM, updateTeam)
      UserStore.removeListener(UserConstants.UPDATE_TEAMS, updateTeams)
      UserStore.removeListener(UserConstants.RENDER_TEAMS, updateTeams)
    }
  }, [])

  return (
    <div className="layout__container">
      <ProjectsBulkActions
        projects={projects.toJS()}
        teams={teamsState.toJS()}
        isSelectedTeamPersonal={teamState.get('type') === 'personal'}
      >
        <div className="projects-list">
          {projects.map((project) => (
            <ProjectContainer
              key={project.get('id')}
              project={project}
              downloadTranslationFn={downloadTranslationFn}
              team={teamState}
              teams={teamsState}
              selectedUser={selectedUser}
            />
          ))}
        </div>
      </ProjectsBulkActions>
    </div>
  )
}

ProjectsContainer.propTypes = {
  team: PropTypes.object,
  teams: PropTypes.object,
  downloadTranslationFn: PropTypes.func,
  selectedUser: PropTypes.string,
  fetchingProjects: PropTypes.bool,
}
