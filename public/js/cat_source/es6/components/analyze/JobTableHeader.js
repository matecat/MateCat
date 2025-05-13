import React from 'react'

const JobTableHeader = ({rates, iceMTRawWords}) => {
  return (
    <div
      className={`job-table-header ${rates.ICE_MT && rates.ICE_MT !== rates.MT && iceMTRawWords > 0 ? 'more-columns' : ''}`}
    >
      <div className="job-table-header-title">
        <div>Analysis bucket</div>
        <div>Payable rate</div>
      </div>
      <div>
        <div>New</div>
        <div>{rates.NO_MATCH}%</div>
      </div>
      <div>
        <div>Repetitions</div>
        <div>{rates.REPETITIONS}%</div>
      </div>
      <div>
        <div>
          Internal
          <br />
          75-99%
        </div>
        <div>{rates.INTERNAL}%</div>
      </div>
      <div>
        <div>
          TM Partial
          <br />
          50-74%
        </div>
        <div>{rates['50%-74%']}%</div>
      </div>
      <div>
        <div>
          TM Partial
          <br />
          75-84%
        </div>
        <div>{rates['75%-84%']}%</div>
      </div>
      <div>
        <div>
          TM Partial
          <br />
          85-94%
        </div>
        <div>{rates['85%-94%']}%</div>
      </div>
      <div>
        <div>
          TM Partial
          <br />
          95-99%
        </div>
        <div>{rates['95%-99%']}%</div>
      </div>
      <div>
        <div>
          TM
          <br />
          100%
        </div>
        <div>{rates['100%']}%</div>
      </div>
      <div>
        <div>
          Public TM
          <br />
          100%
        </div>
        <div>{rates['100%_PUBLIC']}%</div>
      </div>
      <div>
        <div>
          TM 100%
          <br />
          in context
        </div>
        <div>{rates['ICE'] ? rates['ICE'] : 0}%</div>
      </div>
      <div>
        <div>Machine Translation</div>
        <div>{rates.MT}%</div>
      </div>
      {rates.ICE_MT && rates.ICE_MT !== rates.MT && iceMTRawWords > 0 ? (
        <div>
          <div>Top Quality Machine Translation</div>
          <div>{rates.ICE_MT ? rates.ICE_MT : rates.MT}%</div>
        </div>
      ) : null}
      <div className="job-table-header-total">
        <div>Total</div>
      </div>
    </div>
  )
}

export default JobTableHeader
