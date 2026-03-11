import {fromJS} from 'immutable'
import PropTypes from 'prop-types'
import React, {useEffect} from 'react'
import ProjectsStore from '../../stores/ProjectsStore'
import ManageConstants from '../../constants/ManageConstants'
import {ProjectsBulkActions} from '../projects/ProjectsBulkActions'
import {ProjectContainer} from './ProjectContainer'

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

    ProjectsStore.addListener(ManageConstants.RENDER_PROJECTS, renderProjects)

    return () => {
      ProjectsStore.removeListener(
        ManageConstants.RENDER_PROJECTS,
        renderProjects,
      )
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
