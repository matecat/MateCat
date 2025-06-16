import React from 'react'
import FileIcon from '../../../../../img/icons/FileIcon'
import LabelWithTooltip from '../common/LabelWithTooltip'
import {ANALYSIS_WORKFLOW_TYPES} from '../../constants/Constants'
const ChunkAnalyzeFile = ({file, index, size, rates, workflowType}) => {
  const matches = file.matches
  return workflowType === ANALYSIS_WORKFLOW_TYPES.STANDARD ? (
    <div
      className={`chunk-file-detail ${
        rates.ICE_MT &&
        rates.ICE_MT !== rates.MT &&
        matches.find((item) => item.type === 'ice_mt').raw > 0
          ? 'more-columns'
          : ''
      }`}
    >
      <div
        className={`chunk-file-detail-background ${size === index ? 'last' : ''} `}
      />
      <div className={'chunk-file-detail-filename'}>
        <div>
          <div>
            <FileIcon size={14} />
          </div>
          <LabelWithTooltip className={`chunk-file-detail-name `}>
            <span>{file.name}</span>
          </LabelWithTooltip>
        </div>
      </div>
      <div>
        <div>{matches.find((item) => item.type === 'new').equivalent}</div>
      </div>
      <div>
        <div>
          {matches.find((item) => item.type === 'repetitions').equivalent}
        </div>
      </div>
      <div>
        <div>{matches.find((item) => item.type === 'internal').equivalent}</div>
      </div>
      <div>
        <div>{matches.find((item) => item.type === 'tm_50_74').equivalent}</div>
      </div>
      <div>
        <div>{matches.find((item) => item.type === 'tm_75_84').equivalent}</div>
      </div>
      <div>
        <div>{matches.find((item) => item.type === 'tm_85_94').equivalent}</div>
      </div>
      <div>
        <div>{matches.find((item) => item.type === 'tm_95_99').equivalent}</div>
      </div>
      <div>
        <div>{matches.find((item) => item.type === 'tm_100').equivalent}</div>
      </div>
      <div>
        <div>
          {matches.find((item) => item.type === 'tm_100_public').equivalent}
        </div>
      </div>
      <div>
        <div>{matches.find((item) => item.type === 'ice').equivalent}</div>
      </div>
      <div>
        {rates.ICE_MT && rates.ICE_MT === rates.MT ? (
          <div>
            {matches.find((item) => item.type === 'MT').equivalent +
              matches.find((item) => item.type === 'ice_mt').equivalent}
          </div>
        ) : (
          <div>{matches.find((item) => item.type === 'MT').equivalent}</div>
        )}
      </div>
      {rates.ICE_MT &&
      rates.ICE_MT !== rates.MT &&
      matches.find((item) => item.type === 'ice_mt').raw > 0 ? (
        <div>
          <div>{matches.find((item) => item.type === 'ice_mt').equivalent}</div>
        </div>
      ) : null}
      <div className={'chunk-file-detail-total'}>
        <div>{file.total_equivalent}</div>
      </div>
    </div>
  ) : workflowType === ANALYSIS_WORKFLOW_TYPES.MTQE ? (
    <div className={`chunk-file-detail mtqe`}>
      <div
        className={`chunk-file-detail-background ${size === index ? 'last' : ''} `}
      />
      <div className={'chunk-file-detail-filename'}>
        <div>
          <div>
            <FileIcon size={14} />
          </div>
          <LabelWithTooltip className={`chunk-file-detail-name `}>
            <span>{file.name}</span>
          </LabelWithTooltip>
        </div>
      </div>
      <div>
        <div>{matches.find((item) => item.type === 'ice').equivalent}</div>
      </div>
      <div>
        <div>{matches.find((item) => item.type === 'tm_100').equivalent}</div>
      </div>
      <div>
        <div>
          {matches.find((item) => item.type === 'tm_100_public').equivalent}
        </div>
      </div>
      <div>
        <div>
          {matches.find((item) => item.type === 'repetitions').equivalent}
        </div>
      </div>
      <div>
        <div>{matches.find((item) => item.type === 'ice_mt').equivalent}</div>
      </div>
      <div>
        <div>
          {matches.find((item) => item.type === 'top_quality_mt').equivalent}
        </div>
      </div>
      <div>
        <div>
          {matches.find((item) => item.type === 'higher_quality_mt').equivalent}
        </div>
      </div>
      <div>
        <div>
          {
            matches.find((item) => item.type === 'standard_quality_mt')
              .equivalent
          }
        </div>
      </div>
      <div className={'chunk-file-detail-total'}>
        <div>{file.total_equivalent}</div>
      </div>
    </div>
  ) : null
}

export default ChunkAnalyzeFile
