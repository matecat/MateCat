import React from 'react'
import {Popup} from 'semantic-ui-react'
import OutsourceContainer from '../outsource/OutsourceContainer'
import {ANALYSIS_WORKFLOW_TYPES} from '../../constants/Constants'
import {Button} from '../common/Button/Button'
import OutsourceButton from './OutsourceButton'

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

    const openOutsourceClass = isOutsourceOpen ? 'openOutsource' : ''
    const jidChunk = `${chunkAnalysis.id}-${index}`

    return (
      <div key={index} className={`${openOutsourceClass}`}>
        <div>
          <div>{`Chunk ${index}`}</div>
          <div>
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
        <div>
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
        <div>
          <div className={`${config.jobAnalysis ? 'disable-outsource' : ''}`}>
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
    <div>
      <div>
        <div>{chunksHtml}</div>
      </div>
    </div>
  )
}

export default SplitChunkJob
