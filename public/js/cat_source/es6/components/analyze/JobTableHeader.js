import React from 'react'

class JobTableHeader extends React.Component {
  constructor(props) {
    super(props)
  }

  shouldComponentUpdate() {
    return true
  }

  render() {
    let rates = this.props.rates
    return (
      <div className="ui grid job-table-header">
        <div className="chunks-title-table sixteen wide column pad-right-10">
          <div className="left-box"></div>
          <div className="single-analysis">
            <div className="single total">Total</div>
            <div className="single payable-words">Weighted</div>
            <div className="single new">New</div>
            <div className="single repetition">Repetition</div>
            <div className="single internal-matches">
              Internal Matches <br /> <span> 75-99% </span>
            </div>
            <div className="single p-50-74">
              TM Partial <span> 50-74% </span>
            </div>
            <div className="single p-75-84">
              TM Partial <span> 75-84% </span>
            </div>
            <div className="single p-65-94">
              TM Partial <span> 85-94% </span>
            </div>
            <div className="single p-95-99">
              TM Partial <span> 95-99% </span>
            </div>
            <div className="single tm-100">
              TM <span>100%</span>
            </div>
            <div className="single tm-public">Public TM 100%</div>
            <div className="single tm-context">TM 100% in context</div>
            <div className="single machine-translation">
              Machine Translation
            </div>
          </div>
        </div>

        <div className="chunks-pay-table sixteen wide column pad-right-10">
          <div className="left-box"></div>
          <div className="single-analysis">
            <div className="single total">Payable rate</div>
            {/*<div className="single payable-words"> > </div>*/}
            <div className="single new">{rates.NO_MATCH}%</div>
            <div className="single repetition">{rates.REPETITIONS}%</div>
            <div className="single internal-matches">{rates.INTERNAL}%</div>
            <div className="single p-50-74">{rates['50%-74%']}%</div>
            <div className="single p-75-84">{rates['75%-84%']}%</div>
            <div className="single p-65-94">{rates['85%-94%']}%</div>
            <div className="single p-95-99">{rates['95%-99%']}%</div>
            <div className="single tm-100">{rates['100%']}%</div>
            <div className="single tm-public">{rates['100%_PUBLIC']}%</div>
            <div className="single tm-context">
              {rates['ICE'] ? rates['ICE'] : 0}%
            </div>
            <div className="single machine-translation">{rates.MT}%</div>
          </div>
        </div>
      </div>
    )
  }
}

export default JobTableHeader
