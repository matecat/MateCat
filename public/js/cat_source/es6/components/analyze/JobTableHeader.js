import React from 'react'
import {
  ANALYSIS_BUCKETS_LABELS,
  ANALYSIS_WORKFLOW_TYPES,
} from '../../constants/Constants'

const JobTableHeader = ({workflowType, rates, iceMTRawWords}) => {
  console.log('workflowType', workflowType)
  return workflowType === ANALYSIS_WORKFLOW_TYPES.STANDARD ? (
    <div
      className={`job-table-header ${rates.ICE_MT && rates.ICE_MT !== rates.MT && iceMTRawWords > 0 ? 'more-columns' : ''}`}
    >
      <div className="job-table-header-title">
        <div>Analysis bucket</div>
        <div>Payable rate</div>
      </div>
      <div>
        <div>{ANALYSIS_BUCKETS_LABELS.new}</div>
        <div>{rates.NO_MATCH}%</div>
      </div>
      <div>
        <div>{ANALYSIS_BUCKETS_LABELS.repetitions}</div>
        <div>{rates.REPETITIONS}%</div>
      </div>
      <div>
        <div>{ANALYSIS_BUCKETS_LABELS.internal}</div>
        <div>{rates.INTERNAL}%</div>
      </div>
      <div>
        <div>{ANALYSIS_BUCKETS_LABELS.tm_50_74}</div>
        <div>{rates['50%-74%']}%</div>
      </div>
      <div>
        <div>{ANALYSIS_BUCKETS_LABELS.tm_75_84}</div>
        <div>{rates['75%-84%']}%</div>
      </div>
      <div>
        <div>{ANALYSIS_BUCKETS_LABELS.tm_85_94}</div>
        <div>{rates['85%-94%']}%</div>
      </div>
      <div>
        <div>{ANALYSIS_BUCKETS_LABELS.tm_95_99}</div>
        <div>{rates['95%-99%']}%</div>
      </div>
      <div>
        <div>{ANALYSIS_BUCKETS_LABELS.tm_100}</div>
        <div>{rates['100%']}%</div>
      </div>
      <div>
        <div>{ANALYSIS_BUCKETS_LABELS.tm_100_public}</div>
        <div>{rates['100%_PUBLIC']}%</div>
      </div>
      <div>
        <div>{ANALYSIS_BUCKETS_LABELS.ice}</div>
        <div>{rates['ICE'] ? rates['ICE'] : 0}%</div>
      </div>
      <div>
        <div>{ANALYSIS_BUCKETS_LABELS.MT}</div>
        <div>{rates.MT}%</div>
      </div>
      {rates.ICE_MT && rates.ICE_MT !== rates.MT && iceMTRawWords > 0 ? (
        <div>
          <div>{ANALYSIS_BUCKETS_LABELS.ice_mt}</div>
          <div>{rates.ICE_MT ? rates.ICE_MT : rates.MT}%</div>
        </div>
      ) : null}
      <div className="job-table-header-total">
        <div>Total</div>
      </div>
    </div>
  ) : workflowType === ANALYSIS_WORKFLOW_TYPES.MTQE ? (
    <div className="job-table-header">
      <div className="job-table-header-title">
        <div>Analysis bucket</div>
        <div>Payable rate</div>
      </div>
      <div>
        <div>{ANALYSIS_BUCKETS_LABELS.ice}</div>
        <div>{rates.ice}%</div>
      </div>
      <div>
        <div>{ANALYSIS_BUCKETS_LABELS.tm_100}</div>
        <div>{rates.tm_100}%</div>
      </div>
      <div>
        <div>{ANALYSIS_BUCKETS_LABELS.tm_100_public}</div>
        <div>{rates.tm_100_public}%</div>
      </div>
      <div>
        <div>{ANALYSIS_BUCKETS_LABELS.repetitions}</div>
        <div>{rates.repetitions}%</div>
      </div>
      <div>
        <div>{ANALYSIS_BUCKETS_LABELS.ice_mt}</div>
        <div>{rates.ice_mt}%</div>
      </div>
      <div>
        <div>{ANALYSIS_BUCKETS_LABELS.top_quality_mt}</div>
        <div>{rates.top_quality_mt}%</div>
      </div>
      <div>
        <div>{ANALYSIS_BUCKETS_LABELS.higher_quality_mt}</div>
        <div>{rates.higher_quality_mt}%</div>
      </div>
      <div>
        <div>{ANALYSIS_BUCKETS_LABELS.standard_quality_mt}</div>
        <div>{rates.standard_quality_mt}%</div>
      </div>
      <div className="job-table-header-total">
        <div>Total</div>
      </div>
    </div>
  ) : null
}

export default JobTableHeader
