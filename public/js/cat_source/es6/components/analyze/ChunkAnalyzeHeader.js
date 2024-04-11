import React from 'react'
import ChevronDown from '../../../../../img/icons/ChevronDown'

const ChunkAnalyzeHeader = ({
  total,
  index,
  showFilesFn,
  showFiles,
  jobInfo,
  chunksSize,
}) => {
  return (
    <div className="chunk-analyze-info">
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
        <div>{total.find((item) => item.type === '50_74').raw}</div>
        <div>{total.find((item) => item.type === '50_74').equivalent}</div>
      </div>
      <div>
        <div>{total.find((item) => item.type === '75_84').raw}</div>
        <div>{total.find((item) => item.type === '75_84').equivalent}</div>
      </div>
      <div>
        <div>{total.find((item) => item.type === '85_94').raw}</div>
        <div>{total.find((item) => item.type === '85_94').equivalent}</div>
      </div>
      <div>
        <div>{total.find((item) => item.type === '95_99').raw}</div>
        <div>{total.find((item) => item.type === '95_99').equivalent}</div>
      </div>
      <div>
        <div>{total.find((item) => item.type === '100').raw}</div>
        <div>{total.find((item) => item.type === '100').equivalent}</div>
      </div>
      <div>
        <div>{total.find((item) => item.type === '100_public').raw}</div>
        <div>{total.find((item) => item.type === '100_public').equivalent}</div>
      </div>
      <div>
        <div>{total.find((item) => item.type === 'ice').raw}</div>
        <div>{total.find((item) => item.type === 'ice').equivalent}</div>
      </div>
      <div>
        <div>{total.find((item) => item.type === 'MT').raw}</div>
        <div>{total.find((item) => item.type === 'MT').equivalent}</div>
      </div>
      <div className={'chunk-analyze-info-total'}>
        <div>{jobInfo.total_raw}</div>
        <div>{jobInfo.total_equivalent}</div>
      </div>
    </div>
  )

  /*return (
    <div className="chunk sixteen wide column pad-right-10">
      <div className="left-box">
        {id}
        <div className="file-details" onClick={showFiles}>
          File <span className="details">details </span>
        </div>
        <div className="f-details-number">({size(jobInfo.files)})</div>
      </div>
      <div className="single-analysis">
        <div className="single total">{jobInfo.total_raw}</div>

        <div className="single payable-words">{jobInfo.total_equivalent}</div>

        <div className="single new">
          {total.find((item) => item.type === 'new').raw}
        </div>

        <div className="single repetition">
          {total.find((item) => item.type === 'repetitions').raw}
        </div>

        <div className="single internal-matches">
          {total.find((item) => item.type === 'internal').raw}
        </div>

        <div className="single p-50-74">
          {total.find((item) => item.type === '50_74').raw}
        </div>

        <div className="single p-75-84">
          {total.find((item) => item.type === '75_84').raw}
        </div>

        <div className="single p-85-94">
          {total.find((item) => item.type === '85_94').raw}
        </div>

        <div className="single p-95-99">
          {total.find((item) => item.type === '95_99').raw}
        </div>

        <div className="single tm-100">
          {total.find((item) => item.type === '100').raw}
        </div>

        <div className="single tm-public">
          {total.find((item) => item.type === '100_public').raw}
        </div>

        <div className="single tm-context">
          {total.find((item) => item.type === 'ice').raw}
        </div>

        <div className="single machine-translation">
          {total.find((item) => item.type === 'MT').raw}
        </div>
      </div>
    </div>
  )*/
}

export default ChunkAnalyzeHeader
