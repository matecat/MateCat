import React from 'react'
import {Popup} from 'semantic-ui-react'
import OutsourceContainer from '../outsource/OutsourceContainer'
import {ANALYSIS_WORKFLOW_TYPES} from '../../constants/Constants'
import LabelWithTooltip from '../common/LabelWithTooltip'
import Merge from '../../../img/icons/Merge'
import {Button, BUTTON_MODE, BUTTON_TYPE} from '../common/Button/Button'
import OutsourceButton from './OutsourceButton'

const SplitChunkJob = ({
  job,
  project,
  status,
  openOutsource,
  outsourceJobId,
  showDetails,
  openMergeModal,
  checkPayableChanged,
  copyJobLinkToClipboard,
  getDirectOpenButton,
  closeOutsourceModal,
  handleOpenOutsourceModal,
  jobLinkRef,
  containers,
}) => {
  const workflowType = project.get('analysis').get('workflow_type')

  const chunksHtml = job.chunks.map((rawChunk, rawIndex) => {
    const index = rawIndex + 1
    const chunkAnalysis = {
      ...rawChunk,
      id: job.id,
      outsource_available: job.outsource_available,
      target_name: job.target_name,
      source_name: job.source_name,
    }

    const isOutsourceOpen =
      openOutsource && outsourceJobId === `${job.id}-${index}`
    const chunkJob = project
      .get('jobs')
      .find(
        (item) =>
          item.get('id') === chunkAnalysis.id &&
          item.get('password') === chunkAnalysis.password,
      )
    checkPayableChanged(job.id + index, chunkAnalysis.total_equivalent)

    const openOutsourceClass = isOutsourceOpen ? 'openOutsource' : ''
    const jidChunk = `${chunkAnalysis.id}-${index}`

    return (
      <div
        key={index}
        className={`chunk ${openOutsourceClass}`}
        onClick={showDetails(chunkAnalysis.id)}
      >
        <div className="title-job splitted">
          <div className="job-id">{`Chunk ${index}`}</div>
          <div className="translate-url">
            <input
              ref={(el) => (jobLinkRef.current[jidChunk] = el)}
              type="text"
              readOnly
              value={encodeURI(chunkAnalysis.urls.t)}
              onClick={(e) => e.stopPropagation()}
            />
            <Popup
              content="Copied to Clipboard!"
              on="click"
              pinned
              position="top center"
              trigger={
                <Button
                  onClick={copyJobLinkToClipboard(jidChunk)}
                  className="copy"
                >
                  <i className="icon-link icon" />
                </Button>
              }
            />
          </div>
        </div>
        <div className="titles-compare">
          <div className="title-total-words ttw">
            <div>{chunkAnalysis.total_raw}</div>
          </div>
          {workflowType === ANALYSIS_WORKFLOW_TYPES.STANDARD && (
            <div className="title-standard-words tsw">
              <div>{chunkAnalysis.total_industry}</div>
            </div>
          )}
          <div
            className="title-matecat-words tmw"
            ref={(container) =>
              (containers.current[chunkAnalysis.id + index] = container)
            }
          >
            <div>{chunkAnalysis.total_equivalent}</div>
          </div>
        </div>
        <div className="activity-icons">
          <div
            className={`activity-button ${config.jobAnalysis ? 'disable-outsource' : ''}`}
          >
            {getDirectOpenButton(chunkAnalysis, `${job.id}-${index}`)}
          </div>
          {!config.jobAnalysis && (
            <OutsourceButton
              chunk={chunkAnalysis}
              index={`${chunkAnalysis.id}-${index}`}
              status={status}
              openOutsourceModal={handleOpenOutsourceModal}
            />
          )}
        </div>
        <OutsourceContainer
          project={project}
          job={chunkJob}
          url={chunkAnalysis.urls.t}
          showTranslatorBox={false}
          extendedView={true}
          onClickOutside={closeOutsourceModal}
          openOutsource={isOutsourceOpen}
          idJobLabel={`${job.id}-${index}`}
          outsourceJobId={outsourceJobId}
          standardWC={chunkAnalysis.total_equivalent}
        />
      </div>
    )
  })

  return (
    <div className="compare-table">
      <div className="job">
        <div className="chunks">
          <div className="chunk" onClick={showDetails(job.id)}>
            <div className="title-job heading splitted">
              <div className="job-info">
                <div className="job-id">ID: {job.id}</div>
                <div className="source-target">
                  <LabelWithTooltip className="source-box">
                    <span>{job.source_name}</span>
                  </LabelWithTooltip>
                  <div className="in-to">
                    <i className="icon-chevron-right icon" />
                  </div>
                  <LabelWithTooltip className="target-box">
                    <span>{job.target_name}</span>
                  </LabelWithTooltip>
                </div>
              </div>
            </div>
            <div className="activity-icons splitted">
              <Button
                type={BUTTON_TYPE.PRIMARY}
                mode={BUTTON_MODE.OUTLINE}
                className="merge"
                onClick={openMergeModal(job.id)}
              >
                <Merge size={18} />
                Merge
              </Button>
            </div>
          </div>
          {chunksHtml}
        </div>
      </div>
    </div>
  )
}

export default SplitChunkJob
