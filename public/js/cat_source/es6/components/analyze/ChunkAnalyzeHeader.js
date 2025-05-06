import React from 'react'
import ChevronDown from '../../../../../img/icons/ChevronDown'
import {ANALYSIS_WORKFLOW_TYPES} from '../../constants/Constants'

const ChunkAnalyzeHeader = ({
  total,
  index,
  showFilesFn,
  showFiles,
  jobInfo,
  chunksSize,
  rates,
  workflowType,
}) => {
  return workflowType === ANALYSIS_WORKFLOW_TYPES.STANDARD ? (
    <div
      className={`chunk-analyze-info ${
        rates.ICE_MT &&
        rates.ICE_MT !== rates.MT &&
        total.find((item) => item.type === 'ice_mt').raw > 0
          ? 'more-columns'
          : ''
      }`}
    >
      {showFiles && <div className={`chunk-analyze-info-background`} />}
      <div>
        <div className={`chunk-analyze-info-header ${showFiles ? 'open' : ''}`}>
          <div>
            <span className={'chunk-analyze-info-index'}>
              {chunksSize > 1 ? '#' + index : ''}
            </span>
            <span>Raw</span>
          </div>
          <div>
            <div className={'chunk-analyze-info-files'} onClick={showFilesFn}>
              <ChevronDown size={10} />
              File ({jobInfo.files.length})
            </div>
            <span>Weighted</span>
          </div>
        </div>
      </div>
      <div>
        <div>{total.find((item) => item.type === 'new').raw}</div>
        <div>{total.find((item) => item.type === 'new').equivalent}</div>
      </div>
      <div>
        <div>{total.find((item) => item.type === 'repetitions').raw}</div>
        <div>
          {total.find((item) => item.type === 'repetitions').equivalent}
        </div>
      </div>
      <div>
        <div>{total.find((item) => item.type === 'internal').raw}</div>
        <div>{total.find((item) => item.type === 'internal').equivalent}</div>
      </div>
      <div>
        <div>{total.find((item) => item.type === 'tm_50_74').raw}</div>
        <div>{total.find((item) => item.type === 'tm_50_74').equivalent}</div>
      </div>
      <div>
        <div>{total.find((item) => item.type === 'tm_75_84').raw}</div>
        <div>{total.find((item) => item.type === 'tm_75_84').equivalent}</div>
      </div>
      <div>
        <div>{total.find((item) => item.type === 'tm_85_94').raw}</div>
        <div>{total.find((item) => item.type === 'tm_85_94').equivalent}</div>
      </div>
      <div>
        <div>{total.find((item) => item.type === 'tm_95_99').raw}</div>
        <div>{total.find((item) => item.type === 'tm_95_99').equivalent}</div>
      </div>
      <div>
        <div>{total.find((item) => item.type === 'tm_100').raw}</div>
        <div>{total.find((item) => item.type === 'tm_100').equivalent}</div>
      </div>
      <div>
        <div>{total.find((item) => item.type === 'tm_100_public').raw}</div>
        <div>
          {total.find((item) => item.type === 'tm_100_public').equivalent}
        </div>
      </div>
      <div>
        <div>{total.find((item) => item.type === 'ice').raw}</div>
        <div>{total.find((item) => item.type === 'ice').equivalent}</div>
      </div>
      <div>
        {rates.ICE_MT && rates.ICE_MT === rates.MT ? (
          <>
            <div>
              {total.find((item) => item.type === 'MT').raw +
                total.find((item) => item.type === 'ice_mt').raw}
            </div>
            <div>
              {total.find((item) => item.type === 'MT').equivalent +
                total.find((item) => item.type === 'ice_mt').equivalent}
            </div>
          </>
        ) : (
          <>
            <div>{total.find((item) => item.type === 'MT').raw}</div>
            <div>{total.find((item) => item.type === 'MT').equivalent}</div>
          </>
        )}
      </div>
      {rates.ICE_MT &&
      rates.ICE_MT !== rates.MT &&
      total.find((item) => item.type === 'ice_mt').raw > 0 ? (
        <div>
          <div>{total.find((item) => item.type === 'ice_mt').raw}</div>
          <div>{total.find((item) => item.type === 'ice_mt').equivalent}</div>
        </div>
      ) : null}
      <div className={'chunk-analyze-info-total'}>
        <div>{jobInfo.total_raw}</div>
        <div>{jobInfo.total_equivalent}</div>
      </div>
    </div>
  ) : workflowType === ANALYSIS_WORKFLOW_TYPES.MTQE ? (
    <div className={`chunk-analyze-info`}>
      {showFiles && <div className={`chunk-analyze-info-background`} />}
      <div>
        <div className={`chunk-analyze-info-header ${showFiles ? 'open' : ''}`}>
          <div>
            <span className={'chunk-analyze-info-index'}>
              {chunksSize > 1 ? '#' + index : ''}
            </span>
            <span>Raw</span>
          </div>
          <div>
            <div className={'chunk-analyze-info-files'} onClick={showFilesFn}>
              <ChevronDown size={10} />
              File ({jobInfo.files.length})
            </div>
            <span>Weighted</span>
          </div>
        </div>
      </div>
      <div>
        <div>{total.find((item) => item.type === 'ice').raw}</div>
        <div>{total.find((item) => item.type === 'ice').equivalent}</div>
      </div>
      <div>
        <div>{total.find((item) => item.type === 'tm_100').raw}</div>
        <div>{total.find((item) => item.type === 'tm_100').equivalent}</div>
      </div>
      <div>
        <div>{total.find((item) => item.type === 'tm_100_public').raw}</div>
        <div>
          {total.find((item) => item.type === 'tm_100_public').equivalent}
        </div>
      </div>
      <div>
        <div>{total.find((item) => item.type === 'repetitions').raw}</div>
        <div>
          {total.find((item) => item.type === 'repetitions').equivalent}
        </div>
      </div>
      <div>
        <div>{total.find((item) => item.type === 'ice_mt').raw}</div>
        <div>{total.find((item) => item.type === 'ice_mt').equivalent}</div>
      </div>
      <div>
        <div>{total.find((item) => item.type === 'top_quality_mt').raw}</div>
        <div>
          {total.find((item) => item.type === 'top_quality_mt').equivalent}
        </div>
      </div>
      <div>
        <div>{total.find((item) => item.type === 'higher_quality_mt').raw}</div>
        <div>
          {total.find((item) => item.type === 'higher_quality_mt').equivalent}
        </div>
      </div>
      <div>
        <div>
          {total.find((item) => item.type === 'standard_quality_mt').raw}
        </div>
        <div>
          {total.find((item) => item.type === 'standard_quality_mt').equivalent}
        </div>
      </div>
      <div className={'chunk-analyze-info-total'}>
        <div>{jobInfo.total_raw}</div>
        <div>{jobInfo.total_equivalent}</div>
      </div>
    </div>
  ) : null
}

export default ChunkAnalyzeHeader
