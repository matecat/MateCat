import React from 'react'
import OutsourceContainer from '../outsource/OutsourceContainer'
import {ANALYSIS_WORKFLOW_TYPES} from '../../constants/Constants'
import LabelWithTooltip from '../common/LabelWithTooltip'
import Split from '../../../img/icons/Split'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../common/Button/Button'
import OutsourceButton from './OutsourceButton'

const SingleChunkJob = ({
  job,
  project,
  status,
  openOutsource,
  outsourceJobId,
  showDetails,
  openSplitModal,
  copyJobLinkToClipboard,
  checkPayableChanged,
  getDirectOpenButton,
  closeOutsourceModal,
  handleOpenOutsourceModal,
  thereIsChunkOutsourced,
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
  const openOutsourceClass = isOutsourceOpen ? 'openOutsource' : ''

  checkPayableChanged(job.id, chunkAnalysis.total_equivalent)

  return (
    <div className="compare-table jobs">
      <div className="job">
        <div className="chunks">
          <div
            className={`chunk ${openOutsourceClass}`}
            onClick={showDetails(job.id)}
          >
            <div className="title-job">
              <div className="job-info">
                <div className="job-id">ID: {job.id}</div>
                <div className="source-target">
                  <LabelWithTooltip className="source-box no-split">
                    <span>{job.source_name}</span>
                  </LabelWithTooltip>
                  <div className="in-to">
                    <i className="icon-chevron-right icon" />
                  </div>
                  <LabelWithTooltip className="target-box no-split">
                    <span>{job.target_name}</span>
                  </LabelWithTooltip>
                </div>
              </div>
              <div className="translate-url">
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
            </div>
            <div className="titles-compare">
              <div className="title-total-words ttw">
                <div>{totalRaw}</div>
              </div>
              {workflowType === ANALYSIS_WORKFLOW_TYPES.STANDARD && (
                <div className="title-standard-words tsw">
                  <div>{totalStandard}</div>
                </div>
              )}
              <div
                className="title-matecat-words tmw"
                ref={(container) => (containers.current[job.id] = container)}
              >
                <div>{chunkAnalysis.total_equivalent}</div>
              </div>
            </div>
            <div className="activity-icons">
              <div
                className={`activity-button ${config.jobAnalysis ? 'disable-outsource' : ''}`}
              >
                {!config.jobAnalysis && config.splitEnabled ? (
                  <Button
                    type={BUTTON_TYPE.PRIMARY}
                    mode={BUTTON_MODE.OUTLINE}
                    size={BUTTON_SIZE.SMALL}
                    className="split"
                    disabled={status !== 'DONE' || thereIsChunkOutsourced()}
                    onClick={openSplitModal(job.id)}
                  >
                    <Split size={18} />
                    Split
                  </Button>
                ) : null}
                {getDirectOpenButton(chunkAnalysis)}
              </div>
              {!config.jobAnalysis && (
                <OutsourceButton
                  chunk={chunkAnalysis}
                  index={chunkAnalysis.id}
                  status={status}
                  openOutsourceModal={handleOpenOutsourceModal}
                />
              )}
            </div>
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
      </div>
    </div>
  )
}

export default SingleChunkJob
