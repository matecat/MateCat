import React from 'react'
import Cookies from 'js-cookie'

export default class GMTSelect extends React.Component {
  constructor(props) {
    super(props)
  }

  componentDidMount() {
    let self = this
    let direction = 'downward'
    if (this.props.direction && this.props.direction === 'up') {
      direction = 'upward'
    }
    var timezoneToShow = Cookies.get('matecat_timezone')
    $(this.gmtSelect).dropdown('set selected', timezoneToShow)
    $(this.gmtSelect).dropdown({
      direction: direction,
      onChange: function (value) {
        if (self.props.changeValue) {
          self.props.changeValue(value)
        }
      },
    })
  }

  render() {
    return (
      <div
        className="ui selection floating dropdown gmt-select"
        ref={(gmtSelect) => (this.gmtSelect = gmtSelect)}
      >
        <input type="hidden" name="gmt" />
        <i className="dropdown icon" />
        <div className="default text">Select GMT</div>
        <div className="menu">
          <div className="item" data-value="-11">
            <div className="gmt-value">(GMT -11:00 )</div>
            <div className="gmt-description"> Midway Islands Time</div>{' '}
          </div>
          <div className="item" data-value="-10">
            <div className="gmt-value">(GMT -10:00 )</div>
            <div className="gmt-description"> Hawaii Standard Time</div>{' '}
          </div>
          <div className="item" data-value="-9">
            <div className="gmt-value">(GMT -9:00 )</div>
            <div className="gmt-description"> Alaska Standard Time</div>{' '}
          </div>
          <div className="item" data-value="-8">
            <div className="gmt-value">(GMT -8:00 )</div>
            <div className="gmt-description"> Pacific Standard Time</div>{' '}
          </div>
          <div className="item" data-value="-7">
            <div className="gmt-value">(GMT -7:00 )</div>
            <div className="gmt-description"> Mountain Standard Time</div>{' '}
          </div>
          <div className="item" data-value="-6">
            <div className="gmt-value">(GMT -6:00 )</div>
            <div className="gmt-description"> Central Standard Time</div>{' '}
          </div>
          <div className="item" data-value="-5">
            <div className="gmt-value">(GMT -5:00 )</div>
            <div className="gmt-description"> Eastern Standard Time</div>{' '}
          </div>
          <div className="item" data-value="-4">
            <div className="gmt-value">(GMT -4:00 )</div>
            <div className="gmt-description"> Atlantic Standard Time</div>{' '}
          </div>
          <div className="item" data-value="-3">
            <div className="gmt-value">(GMT -3:00 )</div>
            <div className="gmt-description">
              {' '}
              Brazil Eastern Time, Argentina Standard Time
            </div>{' '}
          </div>
          <div className="item" data-value="-2">
            <div className="gmt-value">(GMT -2:00 )</div>
            <div className="gmt-description"> South Sandwich Islands</div>{' '}
          </div>
          <div className="item" data-value="-1">
            <div className="gmt-value">(GMT -1:00 )</div>
            <div className="gmt-description"> Central African Time</div>{' '}
          </div>
          <div className="item" data-value="0">
            <div className="gmt-value">(GMT)</div>
            <div className="gmt-description"> Greenwich Mean Time</div>{' '}
          </div>
          <div className="item" data-value="1">
            <div className="gmt-value">(GMT +1:00 )</div>
            <div className="gmt-description"> European Central Time </div>{' '}
          </div>
          <div className="item" data-value="2">
            <div className="gmt-value">(GMT +2:00 )</div>
            <div className="gmt-description"> Eastern European Time</div>{' '}
          </div>
          <div className="item" data-value="3">
            <div className="gmt-value">(GMT +3:00 )</div>
            <div className="gmt-description"> Eastern African Time </div>{' '}
          </div>
          <div className="item" data-value="3.5">
            <div className="gmt-value">(GMT +3:30 )</div>
            <div className="gmt-description"> Middle East Time</div>{' '}
          </div>
          <div className="item" data-value="4">
            <div className="gmt-value">(GMT +4:00 )</div>
            <div className="gmt-description"> Near East Time</div>{' '}
          </div>
          <div className="item" data-value="5">
            <div className="gmt-value">(GMT +5:00 )</div>
            <div className="gmt-description"> Pakistan Lahore Time</div>{' '}
          </div>
          <div className="item" data-value="5.5">
            <div className="gmt-value">(GMT +5:30 )</div>
            <div className="gmt-description"> India Standard Time</div>{' '}
          </div>
          <div className="item" data-value="6">
            <div className="gmt-value">(GMT +6:00 )</div>
            <div className="gmt-description">
              {' '}
              Bangladesh Standard Time
            </div>{' '}
          </div>
          <div className="item" data-value="7">
            <div className="gmt-value">(GMT +7:00 )</div>
            <div className="gmt-description"> Vietnam Standard Time</div>{' '}
          </div>
          <div className="item" data-value="8">
            <div className="gmt-value">(GMT +8:00 )</div>
            <div className="gmt-description"> China Taiwan Time</div>{' '}
          </div>
          <div className="item" data-value="9">
            <div className="gmt-value">(GMT +9:00 )</div>
            <div className="gmt-description"> Japan Standard Time</div>{' '}
          </div>
          <div className="item" data-value="9.5">
            <div className="gmt-value">(GMT +9:30 )</div>
            <div className="gmt-description"> Australia Central Time</div>{' '}
          </div>
          <div className="item" data-value="10">
            <div className="gmt-value">(GMT +10:00 )</div>
            <div className="gmt-description"> Australia Eastern Time</div>{' '}
          </div>
          <div className="item" data-value="11">
            <div className="gmt-value">(GMT +11:00 )</div>
            <div className="gmt-description"> Solomon Standard Time</div>{' '}
          </div>
          <div className="item" data-value="12">
            <div className="gmt-value">(GMT +12:00 )</div>
            <div className="gmt-description">
              {' '}
              New Zealand Standard Time
            </div>{' '}
          </div>
        </div>
      </div>
    )
  }
}
