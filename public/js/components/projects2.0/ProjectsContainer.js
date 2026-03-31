import {fromJS} from 'immutable'
import PropTypes from 'prop-types'
import React, {useEffect, useState} from 'react'
import ProjectsStore from '../../stores/ProjectsStore'
import ManageConstants from '../../constants/ManageConstants'
import {ProjectsBulkActions} from '../projects/ProjectsBulkActions'
import {ProjectContainer} from './ProjectContainer'
import UserConstants from '../../constants/UserConstants'
import UserStore from '../../stores/UserStore'
import {DASHBOARD_REQUEST_PROJECTS_STATUS} from '../../constants/Constants'

export const ProjectsContainer = ({
  team,
  teams,
  downloadTranslationFn,
  selectedUser,
  requestProjectsStatus,
}) => {
  const [projects, setProjects] = useState(fromJS([]))
  const [teamState, setTeamState] = useState(team)
  const [teamsState, setTeamsState] = useState(teams)

  useEffect(() => {
    const renderProjects = (projects, team, teams) => {
      setProjects(projects)
      setTeamState((prevState) => (team ? team : prevState))
      setTeamsState((prevState) => (teams ? teams : prevState))
    }
    const updateProjects = (projects) => setProjects(projects)
    const updateTeams = (teams) => setTeamsState(teams)
    const updateTeam = (team) =>
      setTeamState((prevState) =>
        team.get('id') === prevState.get('id') ? team : prevState,
      )

    ProjectsStore.addListener(ManageConstants.RENDER_PROJECTS, renderProjects)
    ProjectsStore.addListener(ManageConstants.UPDATE_PROJECTS, updateProjects)
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
      UserStore.removeListener(UserConstants.UPDATE_TEAM, updateTeam)
      UserStore.removeListener(UserConstants.UPDATE_TEAMS, updateTeams)
      UserStore.removeListener(UserConstants.RENDER_TEAMS, updateTeams)
    }
  }, [])

  return (
    <div
      className={`layout__container projects-container ${requestProjectsStatus === DASHBOARD_REQUEST_PROJECTS_STATUS.RELOAD_IN_PROGRESS ? 'projects-container--loading' : ''}`}
    >
      <div className="projects-container-title">
        <h4>Projects</h4>
        <div>
          <span>Legend:</span>
          <span>
            <span className="projects-container-legend-unconfirmed-quad" />
            Unconfirmed
          </span>
          <span>
            <span className="projects-container-legend-translated-quad" />
            Translated
          </span>
          <span>
            <span className="projects-container-legend-approved-quad" />
            Revise
          </span>
          <span>
            <span className="projects-container-legend-approved2-quad" />
            Revise 2
          </span>
        </div>
      </div>
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
  requestProjectsStatus: PropTypes.oneOf(
    Object.values(DASHBOARD_REQUEST_PROJECTS_STATUS),
  ),
}
