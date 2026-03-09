import React, {createRef} from 'react'
import {ANALYSIS_WORKFLOW_TYPES, UNIT_COUNT} from '../../constants/Constants'
import HelpCircle from '../../../img/icons/HelpCircle'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../common/Button/Button'
import Merge from '../../../img/icons/Merge'
import Split from '../../../img/icons/Split'
import ChevronRight from '../../../img/icons/ChevronRight'
import Tooltip from '../common/Tooltip'

const CompareTableHeader = ({
  countUnit,
  workflowType,
  job,
  thereIsChunkOutsourced,
  status,
  openSplitModal,
  openMergeModal,
  isSplit,
}) => {
  return (
    <div className="project-card__header">
      <div className="project-card__header-info">
        <div className="project-card__header-languages">
          <Tooltip content={job.source_name}>
            <span ref={createRef()}>{job.source}</span>
          </Tooltip>
          <ChevronRight size={16} />
          <Tooltip content={job.target_name}>
            <span ref={createRef()}>{job.target}</span>
          </Tooltip>
        </div>
        <div className="project-card__header-id">ID: {job.id}</div>
      </div>
      <div className="project-card__count">
        <div>
          {countUnit === UNIT_COUNT.WORDS
            ? 'Total word count'
            : 'Total character count'}
        </div>
        {workflowType === ANALYSIS_WORKFLOW_TYPES.STANDARD && (
          <div>
            Industry weighted
            <Tooltip
              content={'No discount applied for machine-translated words'}
            >
              <div ref={createRef()} style={{height: '16px'}}>
                <HelpCircle />
              </div>
            </Tooltip>
          </div>
        )}
        <div>Matecat weighted</div>
      </div>
      <div className="project-card__header-actions">
        {!config.jobAnalysis && config.splitEnabled ? (
          !isSplit ? (
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
          ) : (
            <Button
              type={BUTTON_TYPE.PRIMARY}
              mode={BUTTON_MODE.OUTLINE}
              size={BUTTON_SIZE.SMALL}
              className="merge"
              onClick={openMergeModal(job.id)}
            >
              <Merge size={18} />
              Merge
            </Button>
          )
        ) : null}
      </div>
    </div>
  )
}

export default CompareTableHeader
