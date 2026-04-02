import React from 'react'
import {Popup} from 'semantic-ui-react'
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

const SplitChunkJob = ({
  job,
  project,
  status,
  openOutsource,
  outsourceJobId,
  showDetails,
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

    const jidChunk = `${chunkAnalysis.id}-${index}`

    return (
      <>
        <div key={index} className="project-card__content">
          <div className="project-card__header-info">
            <div className="project-card__chunkName">{`Chunk ${index}`}</div>
            <div className="project-card__header-link">
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
              <div>{chunkAnalysis.total_raw}</div>
            </div>
            {workflowType === ANALYSIS_WORKFLOW_TYPES.STANDARD && (
              <div>
                <div>{chunkAnalysis.total_industry}</div>
              </div>
            )}
            <div
              ref={(container) =>
                (containers.current[chunkAnalysis.id + index] = container)
              }
            >
              <div>{chunkAnalysis.total_equivalent}</div>
            </div>
          </div>
          <div className="project-card__header-actions">
            {!config.jobAnalysis && status === ANALYSIS_STATUS.DONE && (
              <OutsourceButton
                chunk={chunkAnalysis}
                index={`${chunkAnalysis.id}-${index}`}
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
            {getDirectOpenButton(chunkAnalysis, `${job.id}-${index}`)}
          </div>
        </div>
        <OutsourceContainer
          project={project}
          job={chunkJob}
          showTranslatorBox={false}
          extendedView={true}
          onClickOutside={closeOutsourceModal}
          openOutsource={isOutsourceOpen}
          idJobLabel={`${job.id}-${index}`}
          outsourceJobId={outsourceJobId}
          standardWC={chunkAnalysis.total_equivalent}
        />
      </>
    )
  })

  return chunksHtml
}

export default SplitChunkJob
