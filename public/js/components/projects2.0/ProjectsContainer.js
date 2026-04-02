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
import {SPINNER_LOADER_SIZE, SpinnerLoader} from '../common/SpinnerLoader'
import {set} from 'lodash'
import {hi} from 'make-plural'
import {Button, BUTTON_TYPE} from '../common/Button/Button'
import ManageActions from '../../actions/ManageActions'

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
  const [isFilterApplied, setIsFilterApplied] = useState(false)
  const [reachNoMoreProjects, setReachNoMoreProjects] = useState(false)

  useEffect(() => {
    const renderProjects = (projects, team, teams, hideSpinner, filtering) => {
      setProjects(projects)
      setTeamState((prevState) => (team ? team : prevState))
      setTeamsState((prevState) => (teams ? teams : prevState))
      setIsFilterApplied((prevState) => (filtering ? filtering : prevState))
      setReachNoMoreProjects((prevState) => (hideSpinner ? prevState : false))
    }
    const updateProjects = (projects) => setProjects(projects)
    const updateTeams = (teams) => setTeamsState(teams)
    const updateTeam = (team) =>
      setTeamState((prevState) =>
        team.get('id') === prevState.get('id') ? team : prevState,
      )
    const noMoreProjects = () => setReachNoMoreProjects(true)

    ProjectsStore.addListener(ManageConstants.RENDER_PROJECTS, renderProjects)
    ProjectsStore.addListener(ManageConstants.UPDATE_PROJECTS, updateProjects)
    UserStore.addListener(UserConstants.UPDATE_TEAM, updateTeam)
    UserStore.addListener(UserConstants.UPDATE_TEAMS, updateTeams)
    UserStore.addListener(UserConstants.RENDER_TEAMS, updateTeams)
    ProjectsStore.addListener(ManageConstants.NO_MORE_PROJECTS, noMoreProjects)

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
      ProjectsStore.removeListener(
        ManageConstants.NO_MORE_PROJECTS,
        noMoreProjects,
      )
    }
  }, [])

  const getEmptyState = () => {
    const thereAreMembers =
      (teamState.get('members') && teamState.get('members').size > 1) ||
      (teamState.get('pending_invitations') &&
        teamState.get('pending_invitations').size > 0) ||
      teamState.get('type') === 'personal'

    return (
      <div className="notify-notfound">
        {isFilterApplied ? (
          <div>
            <div className="message-nofound">No Projects Found</div>
            <div className="no-results-found"></div>
          </div>
        ) : teamState.get('type') === 'personal' ? (
          <div className="no-results-teams">
            <div className="message-nofound">Welcome to your Personal area</div>
            <div className="welcome-to-matecat"></div>
            <div className="message-create">
              <p>
                <Button
                  type={BUTTON_TYPE.PRIMARY}
                  onClick={() =>
                    window.open(`/?idTeam=${teamState.get('id')}`, '_blank')
                  }
                >
                  Create Project
                </Button>
              </p>
              {!thereAreMembers ? (
                <p>
                  <Button
                    type={BUTTON_TYPE.PRIMARY}
                    onClick={() =>
                      ManageActions.openModifyTeamModal(teamState.toJS())
                    }
                  >
                    Add member
                  </Button>
                </p>
              ) : (
                ''
              )}
            </div>
          </div>
        ) : (
          <div className="no-results-teams">
            <div className="message-nofound">
              Welcome to {teamState.get('name')}
            </div>
            <div className="no-results-found"></div>
            <div className="message-create">
              <p>
                <Button
                  type={BUTTON_TYPE.PRIMARY}
                  onClick={() =>
                    window.open(`/?idTeam=${teamState.get('id')}`, '_blank')
                  }
                >
                  Create Project
                </Button>
                {!thereAreMembers ? (
                  <Button
                    type={BUTTON_TYPE.PRIMARY}
                    onClick={() =>
                      ManageActions.openModifyTeamModal(teamState.toJS())
                    }
                  >
                    Add member
                  </Button>
                ) : (
                  ''
                )}
              </p>
            </div>
          </div>
        )}
      </div>
    )
  }

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
        {/* {projects.size > 0 ? ( */}
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
        {/*  ) : (
          getEmptyState()
        )} */}
      </ProjectsBulkActions>

      {reachNoMoreProjects ? (
        <div className="spinner-loader-more-projects spinner-loader-more-projects--visible">
          <h5>No more projects</h5>
        </div>
      ) : (
        <div
          className={`spinner-loader-more-projects ${
            requestProjectsStatus ===
            DASHBOARD_REQUEST_PROJECTS_STATUS.MORE_IN_PROGRESS
              ? 'spinner-loader-more-projects--visible'
              : ''
          }`}
        >
          <SpinnerLoader
            className="spinner-loader-more-projects__loader-component"
            size={SPINNER_LOADER_SIZE.MEDIUM}
            label="Loading more projects"
          />
        </div>
      )}
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
