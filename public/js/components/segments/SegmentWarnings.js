/**
 * React Component for the warnings.

 */
import React from 'react'
import {fromJS} from 'immutable'
import {forOwn} from 'lodash'
import SegmentQA from '../../../img/icons/SegmentQA'
import InfoIcon from '../../../img/icons/InfoIcon'
import AlertIcon from '../../../img/icons/AlertIcon'

const ALERT_STYLES = {
  ERROR: {
    classes: 'error-alert alert-block',
    icon: <SegmentQA size={16} />,
  },
  WARNING: {
    classes: 'warning-alert alert-block',
    icon: <AlertIcon size={16} />,
  },
  INFO: {
    classes: 'info-alert alert-block',
    icon: <InfoIcon size={16} />,
  },
}

const DEFAULT_ALERT = {classes: 'alert-block', icon: <SegmentQA />}

// One entry per distinct outcome, in ERROR / WARNING / INFO order. Repeats of an
// outcome are counted but not shown, which is what the original did.
const collectWarnings = (warnings) => {
  const collected = []
  if (!warnings) return collected

  const countsByOutcome = {}
  Object.keys(ALERT_STYLES).forEach((type) => {
    if (!warnings[type]) return
    forOwn(warnings[type].Categories, (value) => {
      value.forEach((el) => {
        if (countsByOutcome[el.outcome]) {
          countsByOutcome[el.outcome]++
          return
        }
        // Copied rather than stamped in place: the class mutated the caller's
        // warning objects, which then compared unequal to a freshly supplied
        // set and defeated the memo comparison below.
        collected.push({...el, type})
        countsByOutcome[el.outcome] = 1
      })
    })
  })

  return collected
}

const SegmentWarnings = ({warnings}) => (
  <div className="warnings-block">
    {collectWarnings(warnings).map((el, index) => {
      const {classes, icon} = ALERT_STYLES[el.type] ?? DEFAULT_ALERT
      return (
        <div key={index} className={classes}>
          <ul>
            <li className="icon-column">{icon}</li>
            <li className="content-column">
              <p dangerouslySetInnerHTML={{__html: el.debug}} />
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

// Replaces shouldComponentUpdate: re-render only when the warnings differ by
// value, not by identity. memo skips the render when the comparator returns true.
export default React.memo(SegmentWarnings, (prev, next) =>
  fromJS(prev.warnings).equals(fromJS(next.warnings)),
)
