import React from 'react'
import {ANALYSIS_WORKFLOW_TYPES, UNIT_COUNT} from '../../constants/Constants'
import HelpCircle from '../../../img/icons/HelpCircle'

const CompareTableHeader = ({countUnit, workflowType}) => {
  return (
    <div className="compare-table">
      <div className="header-compare-table">
        <div className="title-job">
          <h5 />
          <p />
        </div>
        <div className="titles-compare">
          <div className="title-total-words">
            <h5>
              {countUnit === UNIT_COUNT.WORDS
                ? 'Total word count'
                : 'Total character count'}
            </h5>
          </div>
          {workflowType === ANALYSIS_WORKFLOW_TYPES.STANDARD && (
            <div className="title-standard-words">
              <h5>
                Industry weighted
                <span data-tooltip="No discount applied for machine-translated words">
                  <HelpCircle />
                </span>
              </h5>
            </div>
          )}
          <div className="title-matecat-words">
            <h5>Matecat weighted</h5>
          </div>
        </div>
      </div>
    </div>
  )
}

export default CompareTableHeader
