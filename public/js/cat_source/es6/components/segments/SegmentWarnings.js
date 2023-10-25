/**
 * React Component for the warnings.

 */
import React from 'react'
import Immutable from 'immutable'
import {forOwn} from 'lodash'

class SegmentWarnings extends React.Component {
  constructor(props) {
    super(props)
    this.state = {}
  }

  componentDidMount() {}

  componentWillUnmount() {}

  shouldComponentUpdate(nextProps) {
    return !Immutable.fromJS(this.props.warnings).equals(
      Immutable.fromJS(nextProps.warnings),
    )
  }

  render() {
    let warnings_count = {}
    let warnings = []
    let fnMap = (el, type) => {
      if (warnings_count[el.outcome]) {
        warnings_count[el.outcome]++
      } else {
        let item = el
        item.type = type
        warnings.push(item)
        warnings_count[el.outcome] = 1
      }
    }
    if (this.props.warnings) {
      if (this.props.warnings.ERROR) {
        _.forOwn(this.props.warnings.ERROR.Categories, (value, key) => {
          value.map((el) => {
            fnMap(el, 'ERROR')
          })
        })
      }
      if (this.props.warnings.WARNING) {
        forOwn(this.props.warnings.WARNING.Categories, (value, key) => {
          value.map((el) => {
            fnMap(el, 'WARNING')
          })
        })
      }
      if (this.props.warnings.INFO) {
        forOwn(this.props.warnings.INFO.Categories, (value, key) => {
          value.map((el) => {
            fnMap(el, 'INFO')
          })
        })
      }
    }

    return (
      <div className="warnings-block">
        {warnings.map((el, index) => {
          let classes_block, classes_icon
          switch (el.type) {
            case 'ERROR':
              classes_block = 'error-alert alert-block'
              classes_icon = 'icon-cancel-circle icon'
              break
            case 'WARNING':
              classes_block = 'warning-alert alert-block'
              classes_icon = 'icon-warning2 icon'
              break
            case 'INFO':
              classes_block = 'info-alert alert-block'
              classes_icon = 'icon-info icon'
              break
            default:
              classes_block = 'alert-block'
              classes_icon = 'icon-cancel-circle icon'
              break
          }
          return (
            <div key={index} className={classes_block}>
              <ul>
                <li className="icon-column">
                  <i className={classes_icon}></i>
                </li>
                <li className="content-column">
                  <p>
                    {el.debug}
                    {/*<b>({warnings_count[el.outcome]})</b>*/}
                  </p>
                  {el.tip !== '' ? (
                    <p className="error-solution">
                      <b>{el.tip}</b>
                    </p>
                  ) : null}
                </li>
              </ul>
            </div>
          )
        })}
      </div>
    )
  }
}

export default SegmentWarnings
