import React from 'react'
import OutsourceContainer from '../outsource/OutsourceContainer'
import {ANALYSIS_WORKFLOW_TYPES} from '../../constants/Constants'
import {Button, BUTTON_MODE, BUTTON_SIZE} from '../common/Button/Button'
import OutsourceButton from './OutsourceButton'

const SingleChunkJob = ({
  job,
  project,
  status,
  openOutsource,
  outsourceJobId,
  showDetails,
  copyJobLinkToClipboard,
  checkPayableChanged,
  getDirectOpenButton,
  closeOutsourceModal,
  handleOpenOutsourceModal,
  jobLinkRef,
  containers,
}) => {
  const workflowType = project.get('analysis').get('workflow_type')
  const rawChunk = job.chunks[0]
  const chunkAnalysis = {
    ...rawChunk,
    id: job.id,
    outsource_available: job.outsource_available,
    outsource: job.outsource,
    target_name: job.target_name,
    source_name: job.source_name,
  }

  const totalRaw = chunkAnalysis.total_raw
  const totalStandard = chunkAnalysis.total_industry || 0
  const chunkJob = project
    .get('jobs')
    .find(
      (item) =>
        item.get('id') === chunkAnalysis.id &&
        item.get('password') === chunkAnalysis.password,
    )

  const isOutsourceOpen = openOutsource && outsourceJobId === job.id

  checkPayableChanged(job.id, chunkAnalysis.total_equivalent)

  return (
    <div className="project-card__content">
      <div className="project-card__header-info">
        <input
          type="text"
          readOnly
          value={encodeURI(chunkAnalysis.urls.t)}
          ref={(el) => (jobLinkRef.current[job.id] = el)}
          onClick={(e) => e.stopPropagation()}
        />
        <Button
          onClick={copyJobLinkToClipboard(job.id)}
          className="copy"
          data-content="Copied to Clipboard!"
          data-position="top center"
        >
          <i className="icon-link icon" />
        </Button>
      </div>
      <div className="project-card__count">
        <div>
          <div>{totalRaw}</div>
        </div>
        {workflowType === ANALYSIS_WORKFLOW_TYPES.STANDARD && (
          <div>
            <div>{totalStandard}</div>
          </div>
        )}
        <div ref={(container) => (containers.current[job.id] = container)}>
          <div>{chunkAnalysis.total_equivalent}</div>
        </div>
      </div>
      <div className="project-card__header-actions">
        {!config.jobAnalysis && (
          <OutsourceButton
            chunk={chunkAnalysis}
            index={chunkAnalysis.id}
            status={status}
            openOutsourceModal={handleOpenOutsourceModal}
          />
        )}
        <Button
          mode={BUTTON_MODE.OUTLINE}
          size={BUTTON_SIZE.SMALL}
          onClick={showDetails(job.id)}
        >
          Details
        </Button>
        {getDirectOpenButton(chunkAnalysis)}
        <OutsourceContainer
          project={project}
          job={chunkJob}
          url={chunkAnalysis.urls.t}
          standardWC={chunkAnalysis.total_equivalent}
          showTranslatorBox={false}
          extendedView={true}
          onClickOutside={closeOutsourceModal}
          openOutsource={isOutsourceOpen}
          idJobLabel={job.id}
          outsourceJobId={outsourceJobId}
        />
      </div>
    </div>
  )
}

export default SingleChunkJob
