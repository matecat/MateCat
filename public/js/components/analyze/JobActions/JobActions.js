import React, {createRef, useMemo} from 'react'
import PropTypes from 'prop-types'
import {fromJS} from 'immutable'
import ModalsActions from '../../../actions/ModalsActions'
import {getGlobalWarnings} from '../../../api/getGlobalWarnings'
import CommonUtils from '../../../utils/commonUtils'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../../common/Button/Button'
import Tooltip from '../../common/Tooltip'
import {JobDownloadMenu} from '../../common/JobDownloadMenu'
import Merge from '../../../../img/icons/Merge'
import Split from '../../../../img/icons/Split'
import Download from '../../../../img/icons/Download'
import {ACTIONS_DISABLED_TITLE} from '../LanguageTree'
import {DisabledHint} from '../shared/DisabledHint'
import styles from './JobActions.module.scss'

const SPLIT_NOT_ALLOWED_HINT =
  'Only the project owner or a member of its team can split a job'
const MERGE_NOT_ALLOWED_HINT =
  'Only the project owner or a member of its team can merge a job'

// Segments with at least one ERROR issue, counted once whatever the category
// (as SegmentStore.updateGlobalWarnings does)
export const countErrorSegments = (details) =>
  new Set(Object.values(details?.ERROR?.Categories ?? {}).flat()).size

const NotAllowedButton = ({hint, className, size, children}) => (
  // A refused split/merge stays hoverable so the tooltip can explain why
  <Tooltip content={hint} stylePointerElement={{display: 'inline-flex'}}>
    <Button
      ref={createRef()}
      type={BUTTON_TYPE.PRIMARY}
      mode={BUTTON_MODE.OUTLINE}
      size={size}
      className={`${className} ${styles.splitButton}`}
      aria-disabled={true}
      tabIndex="0"
      onClick={() => {}}
    >
      {children}
    </Button>
  </Tooltip>
)

NotAllowedButton.propTypes = {
  hint: PropTypes.string.isRequired,
  className: PropTypes.string,
  size: PropTypes.string,
  children: PropTypes.node,
}

/**
 * Split or merge and the download menu of a job or chunk. `chunk` null on a
 * split job means the whole job. The job page hides split and merge: they
 * belong to the project owner.
 */
export const JobActions = ({
  project,
  job,
  chunk,
  actionsEnabled,
  showSplitMerge = true,
  size = 'small',
}) => {
  // Split and merge: 32px in the details panel, 40px in a page hero
  const splitSize =
    size === 'standard' ? BUTTON_SIZE.STANDARD : BUTTON_SIZE.SMALL
  // An unsplit job is its only chunk
  const item = chunk ?? (job.isSplit ? null : job.chunks[0])
  // The selected chunk, or every chunk of a whole split job
  const chunks = item ? [item] : job.chunks

  const projectJobs = useMemo(() => project.jobs ?? [], [project])
  const findProjectJob = (password) =>
    projectJobs.find(
      (projectJob) =>
        String(projectJob.id) === String(job.id) &&
        projectJob.password === password,
    )
  const isJobCompleted = chunks.every((current) => {
    const stats = findProjectJob(current.password)?.stats
    return stats?.raw ? CommonUtils.isJobCompleted(stats) : false
  })

  const thereIsChunkOutsourced = projectJobs.some(
    (projectJob) =>
      String(projectJob.id) === String(job.id) && !!projectJob.outsource,
  )

  const findProjectJobForModals = () =>
    projectJobs.find((projectJob) => String(projectJob.id) === String(job.id))

  const openSplitModal = () => {
    ModalsActions.openSplitJobModal(
      fromJS(findProjectJobForModals()),
      fromJS(project),
      () => window.location.reload(),
    )
  }

  const openMergeModal = () => {
    ModalsActions.openMergeModal(project, findProjectJobForModals(), () =>
      window.location.reload(),
    )
  }

  // The QA check of the CAT tool: segments with errors may break the file
  const showWarnings = (proceed, proceedWithoutErrors, chunkWithErrors) =>
    ModalsActions.showDownloadWarningsModal(
      proceed,
      proceedWithoutErrors,
      () => window.open(chunkWithErrors.urls.t, '_blank'),
      // The analysis page has no ownerIsMe: splitEnabled is set for the
      // project owner and the members of its team
      {ownerIsMe: !!config.splitEnabled},
    )

  const checkGlobalWarnings = (proceed, proceedWithoutErrors) => {
    // A Google Drive download opens a window, which the browser allows only
    // inside the click: decide from the project's warnings_count, no await
    const counts = chunks.map(
      (current) => findProjectJob(current.password)?.warnings_count,
    )
    if (
      config.isGDriveProject &&
      counts.every((count) => typeof count === 'number')
    ) {
      const index = counts.findIndex((count) => count > 0)
      if (index < 0) proceed()
      else showWarnings(proceed, proceedWithoutErrors, chunks[index])
      return
    }
    Promise.all(
      chunks.map((current) =>
        getGlobalWarnings({id_job: job.id, password: current.password}).then(
          (data) => ({
            chunk: current,
            errors: countErrorSegments(data.details),
          }),
        ),
      ),
    )
      .then((results) => {
        const withErrors = results.find((result) => result.errors > 0)
        if (!withErrors) {
          proceed()
          return
        }
        showWarnings(proceed, proceedWithoutErrors, withErrors.chunk)
      })
      // The server still checks the file: a failed warnings call does not block
      .catch(() => proceed())
  }

  const splitOrMerge = job.isSplit ? (
    config.splitEnabled ? (
      <Button
        type={BUTTON_TYPE.PRIMARY}
        mode={BUTTON_MODE.OUTLINE}
        size={splitSize}
        className={`merge ${styles.splitButton}`}
        tabIndex="0"
        disabled={!actionsEnabled}
        onClick={openMergeModal}
      >
        <Merge size={18} />
        Merge
      </Button>
    ) : (
      <NotAllowedButton
        hint={MERGE_NOT_ALLOWED_HINT}
        className="merge merge-not-allowed"
        size={splitSize}
      >
        <Merge size={18} />
        Merge
      </NotAllowedButton>
    )
  ) : config.splitFeatureAvailable ? (
    config.splitEnabled ? (
      <Button
        type={BUTTON_TYPE.PRIMARY}
        mode={BUTTON_MODE.OUTLINE}
        size={splitSize}
        className={`split ${styles.splitButton}`}
        tabIndex="0"
        disabled={!actionsEnabled || thereIsChunkOutsourced}
        onClick={openSplitModal}
      >
        <Split size={18} />
        Split
      </Button>
    ) : (
      <NotAllowedButton
        hint={SPLIT_NOT_ALLOWED_HINT}
        className="split split-not-allowed"
        size={splitSize}
      >
        <Split size={18} />
        Split
      </NotAllowedButton>
    )
  ) : null

  return (
    <div className={styles.actions}>
      {showSplitMerge && splitOrMerge && (
        <DisabledHint hint={ACTIONS_DISABLED_TITLE} active={!actionsEnabled}>
          {splitOrMerge}
        </DisabledHint>
      )}
      {/* Every download returns the whole job, whatever chunk password
          it gets: a single chunk of a split job has no download menu */}
      {!(job.isSplit && chunk) && chunks.length > 0 && (
        <DisabledHint hint={ACTIONS_DISABLED_TITLE} active={!actionsEnabled}>
          <JobDownloadMenu
            // For a whole split job, the first chunk's password
            jid={job.id}
            password={chunks[0].password}
            isGDriveProject={!!config.isGDriveProject}
            isJobCompleted={isJobCompleted}
            disabled={!actionsEnabled}
            onBeforeDownload={checkGlobalWarnings}
            triggerProps={{
              children: (
                <>
                  <Download size={18} />
                  Download
                </>
              ),
              type: BUTTON_TYPE.DEFAULT,
              mode: BUTTON_MODE.OUTLINE,
              size: BUTTON_SIZE.STANDARD,
              className: styles.downloadButton,
            }}
          />
        </DisabledHint>
      )}
    </div>
  )
}

JobActions.propTypes = {
  // Plain project, as returned by the project API
  project: PropTypes.shape({
    id: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
    password: PropTypes.string,
    jobs: PropTypes.array,
  }).isRequired,
  job: PropTypes.object.isRequired,
  // null on a split job: the whole job
  chunk: PropTypes.object,
  actionsEnabled: PropTypes.bool,
  showSplitMerge: PropTypes.bool,
  size: PropTypes.oneOf(['small', 'standard']),
}
