import React from 'react'
import OutsourceContainer from '../outsource/OutsourceContainer'
import {
  ANALYSIS_STATUS,
  ANALYSIS_WORKFLOW_TYPES,
} from '../../constants/Constants'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../common/Button/Button'
import OutsourceButton from './OutsourceButton'
import CopyIcon from '../../../img/icons/CopyIcon'
import {Popup} from 'semantic-ui-react'

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
    <>
      <div className="project-card__content">
        <div className="project-card__header-info">
          <div className="project-card__header-link">
            <input
              type="text"
              readOnly
              value={encodeURI(chunkAnalysis.urls.t)}
              ref={(el) => (jobLinkRef.current[job.id] = el)}
              onClick={(e) => e.stopPropagation()}
            />
            <Popup
              content="Copied to Clipboard!"
              on="click"
              pinned
              position="top center"
              trigger={
                <Button
                  size={BUTTON_SIZE.ICON_XSMALL}
                  mode={BUTTON_MODE.GHOST}
                  type={BUTTON_TYPE.PRIMARY}
                  onClick={copyJobLinkToClipboard(job.id)}
                  data-position="top center"
                >
                  <CopyIcon size={16} />
                </Button>
              }
            />
          </div>
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
          {!config.jobAnalysis && status === ANALYSIS_STATUS.DONE && (
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
        </div>
      </div>
      <OutsourceContainer
        project={project}
        job={chunkJob}
        url={chunkAnalysis.urls.t}
        standardWC={chunkAnalysis.total_equivalent}
        showTranslatorBox={false}
        extendedView={false}
        onClickOutside={closeOutsourceModal}
        openOutsource={isOutsourceOpen}
        idJobLabel={job.id}
        outsourceJobId={outsourceJobId}
      />
    </>
  )
}

export default SingleChunkJob
