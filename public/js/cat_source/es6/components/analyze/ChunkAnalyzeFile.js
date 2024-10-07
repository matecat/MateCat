import React from 'react'
import FileIcon from '../../../../../img/icons/FileIcon'
import LabelWithTooltip from '../common/LabelWithTooltip'
const ChunkAnalyzeFile = ({file, index, size, rates}) => {
  const matches = file.matches
  return (
    <div
      className={`chunk-file-detail ${
        rates.ICE_MT &&
        rates.ICE_MT !== rates.MT &&
        matches.find((item) => item.type === 'ice_MT').raw > 0
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
        <div>{matches.find((item) => item.type === '50_74').equivalent}</div>
      </div>
      <div>
        <div>{matches.find((item) => item.type === '75_84').equivalent}</div>
      </div>
      <div>
        <div>{matches.find((item) => item.type === '85_94').equivalent}</div>
      </div>
      <div>
        <div>{matches.find((item) => item.type === '95_99').equivalent}</div>
      </div>
      <div>
        <div>{matches.find((item) => item.type === '100').equivalent}</div>
      </div>
      <div>
        <div>
          {matches.find((item) => item.type === '100_public').equivalent}
        </div>
      </div>
      <div>
        <div>{matches.find((item) => item.type === 'ice').equivalent}</div>
      </div>
      <div>
        {rates.ICE_MT && rates.ICE_MT === rates.MT ? (
          <div>
            {matches.find((item) => item.type === 'MT').equivalent +
              matches.find((item) => item.type === 'ice_MT').equivalent}
          </div>
        ) : (
          <div>{matches.find((item) => item.type === 'MT').equivalent}</div>
        )}
      </div>
      {rates.ICE_MT &&
      rates.ICE_MT !== rates.MT &&
      matches.find((item) => item.type === 'ice_MT').raw > 0 ? (
        <div>
          <div>{matches.find((item) => item.type === 'ice_MT').equivalent}</div>
        </div>
      ) : null}
      <div className={'chunk-file-detail-total'}>
        <div>{file.total_equivalent}</div>
      </div>
    </div>
  )
}

export default ChunkAnalyzeFile
