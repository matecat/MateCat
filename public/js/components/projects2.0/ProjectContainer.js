import PropTypes from 'prop-types'
import React, {useContext, useRef, useState} from 'react'
import {ProjectsBulkActionsContext} from '../projects/ProjectsBulkActions/ProjectsBulkActionsContext'
import {Checkbox, CHECKBOX_STATE} from '../common/Checkbox'
import {Controller, useForm} from 'react-hook-form'
import ManageActions from '../../actions/ManageActions'
import {Input} from '../common/Input/Input'
import {
  Button,
  BUTTON_HTML_TYPE,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../common/Button/Button'
import IconEdit from '../icons/IconEdit'
import Checkmark from '../../../img/icons/Checkmark'
import IconClose from '../icons/IconClose'
import {JobContainer} from './JobContainer'
import {getLastProjectActivityLogAction} from '../../api/getLastProjectActivityLogAction'
import {isUndefined} from 'lodash'

export const ProjectContainer = ({
  project,
  teams,
  team,
  selectedUser,
  changeStatusFn,
  downloadTranslationFn,
}) => {
  const {jobsBulk, onCheckedProject, onCheckedJob} = useContext(
    ProjectsBulkActionsContext,
  )

  const {handleSubmit, control, reset} = useForm()

  const [shouldShowMoreActions, setShouldShowMoreActions] = useState(false)
  const [isEditingName, setIsEditingName] = useState(false)
  const [lastAction, setLastAction] = useState()
  const [jobsActions, setJobsActions] = useState()

  const handleFormSubmit = (formData) => {
    const {name} = formData
    ManageActions.changeProjectName(project, name)
    setIsEditingName(false)
  }

  const changeNameFormId = `project-change-name-${project.get('id')}`

  const changeNameForm = (
    <form
      id={changeNameFormId}
      className="project-container-form-edit-name"
      onSubmit={handleSubmit(handleFormSubmit)}
      onReset={() => {
        reset()
        setIsEditingName(false)
      }}
    >
      <fieldset>
        <Controller
          control={control}
          defaultValue={project.get('name')}
          name="name"
          rules={{
            required: true,
          }}
          render={({field: {name, onChange, value}, fieldState: {error}}) => (
            <Input
              autoFocus
              placeholder="Name"
              {...{name, value, onChange, error}}
            />
          )}
        />
      </fieldset>
    </form>
  )

  const idTeamProject = project.get('id_team')

  const jobsBulkForCurrentProject = project
    .get('jobs')
    .toJS()
    .filter(({id}) => jobsBulk.some((value) => value === id))

  const projectNameElements = (
    <div className="project-container-header-name">
      {isEditingName ? (
        <>
          {changeNameForm}
          {isEditingName && (
            <>
              <Button
                type={BUTTON_TYPE.PRIMARY}
                size={BUTTON_SIZE.SMALL}
                htmlType={BUTTON_HTML_TYPE.SUBMIT}
                form={changeNameFormId}
              >
                <Checkmark size={14} />
                Confirm
              </Button>

              <Button
                type={BUTTON_TYPE.WARNING}
                size={BUTTON_SIZE.SMALL}
                htmlType={BUTTON_HTML_TYPE.RESET}
                form={changeNameFormId}
              >
                <IconClose size={11} />
              </Button>
            </>
          )}
        </>
      ) : (
        <>
          <h6 title="Project name" data-testid="project-name">
            {project.get('name')}
          </h6>
          <Button
            className="project-container-button-edit-name"
            mode={BUTTON_MODE.GHOST}
            size={BUTTON_SIZE.ICON_XSMALL}
            onClick={() => setIsEditingName(true)}
          >
            <IconEdit size={16} />
          </Button>
        </>
      )}
    </div>
  )

  const getActivityLogUrl = () => {
    return '/activityLog/' + project.get('id') + '/' + project.get('password')
  }

  const thereIsChunkOutsourced = (idJob) => {
    const outsourceChunk = project.get('jobs').find(function (item) {
      return !!item.get('outsource') && item.get('id') === idJob
    })
    return !isUndefined(outsourceChunk)
  }

  const getLastAction = useRef()
  getLastAction.current = () => {
    getLastProjectActivityLogAction({
      id: project.get('id'),
      password: project.get('password'),
    }).then((data) => {
      const lastAction = data.activity[0] ? data.activity[0] : null
      setLastAction(lastAction)
      setJobsActions(data.activity)
    })
  }

  const getLastJobAction = (idJob) => {
    //Last Activity Log Action
    let lastAction
    if (jobsActions && jobsActions.length > 0) {
      lastAction = jobsActions.find(function (job) {
        return job.id_job == idJob
      })
    }
    return lastAction
  }

  const getJobContainer = () => {
    const tempIdsArray = []

    const jobs = project.get('jobs')

    return jobs.map((job, index) => {
      let isChunk = false
      if (tempIdsArray.indexOf(job.get('id')) > -1) {
        isChunk = true
        index++
      } else if (
        jobs.get(index + 1) &&
        jobs.get(index + 1).get('id') === job.get('id')
      ) {
        //The first of the Chunk
        isChunk = true
        tempIdsArray.push(job.get('id'))
        index = 1
      } else {
        index = 0
      }

      const lastAction = getLastJobAction(job.get('id'))
      const isChunkOutsourced = thereIsChunkOutsourced(job.get('id'))

      return (
        <JobContainer
          key={job.get('id') + '-' + index}
          job={job}
          index={index}
          project={project}
          jobsLenght={project.get('jobs').size}
          changeStatusFn={changeStatusFn}
          downloadTranslationFn={downloadTranslationFn}
          isChunk={isChunk}
          lastAction={lastAction}
          isChunkOutsourced={isChunkOutsourced}
          activityLogUrl={getActivityLogUrl()}
          isChecked={jobsBulk.some((jobId) => jobId === job.get('id'))}
          onCheckedJob={onCheckedJob}
        />
      )
    })
  }

  return (
    <div className="project-container">
      <div className="project-container-header">
        <Checkbox
          className="project-checkbox"
          onChange={() => onCheckedProject(project.get('id'))}
          value={
            jobsBulkForCurrentProject.length === 0
              ? CHECKBOX_STATE.UNCHECKED
              : jobsBulkForCurrentProject.length === project.get('jobs').size
                ? CHECKBOX_STATE.CHECKED
                : CHECKBOX_STATE.INDETERMINATE
          }
        />
        <div>
          {projectNameElements}
          <span title="Project id">ID: {project.get('id')}</span>
        </div>
      </div>
      {getJobContainer()}
    </div>
  )
}

ProjectContainer.propTypes = {
  project: PropTypes.object,
  teams: PropTypes.object,
  team: PropTypes.object,
  selectedUser: PropTypes.string,
  changeStatusFn: PropTypes.func,
  downloadTranslationFn: PropTypes.func,
}
