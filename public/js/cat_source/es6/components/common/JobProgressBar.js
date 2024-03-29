import React, {useRef} from 'react'
import Tooltip from './Tooltip'
import {isUndefined} from 'lodash'

const JobProgressBar = ({
  stats = {},
  onClickFn = () => {},
  showPercent = false,
}) => {
  const approved2ndPassTooltip = useRef()
  // const rejectedTooltip = useRef()
  const approvedTooltip = useRef()
  const translatedTooltip = useRef()
  const draftTooltip = useRef()
  const {raw} = stats
  const newWords = raw ? raw.new : undefined
  const {total, translated, approved, approved2, draft} = raw || {}
  const translatedPerc = (translated * 100) / total
  const approvedPerc = (approved * 100) / total
  const approved2Perc = (approved2 * 100) / total
  const draftPerc = ((draft + newWords) * 100) / total
  const totalPerc = ((total - draft - newWords) * 100) / total
  const analysisComplete = !isUndefined(stats.analysis_complete)
    ? stats.analysis_complete
    : true
  return (
    <div className="progress-bar" data-testid="progress-bar">
      <div className="progr">
        <div className="meter" onClick={onClickFn}>
          {!stats || !analysisComplete ? (
            <div className="bg-loader" />
          ) : (
            <>
              {/*<Tooltip*/}
              {/*  content={*/}
              {/*    'Rejected ' +*/}
              {/*    job.get('stats').get('REJECTED_PERC_FORMATTED') +*/}
              {/*    '%'*/}
              {/*  }*/}
              {/*>*/}
              {/*  <a*/}
              {/*    className="warning-bar translate-tooltip"*/}
              {/*    style={{*/}
              {/*      width: job.get('stats').get('REJECTED_PERC') + '%',*/}
              {/*    }}*/}
              {/*    ref={rejectedTooltip}*/}
              {/*  />*/}
              {/*</Tooltip>*/}
              <Tooltip content={'Approved ' + approved2Perc.toFixed(1) + '%'}>
                <a
                  className="approved-bar-2nd-pass translate-tooltip"
                  style={{width: approved2Perc + '%'}}
                  ref={approved2ndPassTooltip}
                />
              </Tooltip>
              <Tooltip content={'Approved ' + approvedPerc.toFixed(1) + '%'}>
                <a
                  className="approved-bar translate-tooltip"
                  style={{width: approvedPerc + '%'}}
                  ref={approvedTooltip}
                />
              </Tooltip>
              <Tooltip
                content={'Translated ' + translatedPerc.toFixed(1) + '%'}
              >
                <a
                  className="translated-bar translate-tooltip"
                  data-variation="tiny"
                  style={{
                    width: translatedPerc + '%',
                  }}
                  ref={translatedTooltip}
                />
              </Tooltip>
              <Tooltip content={'Draft ' + draftPerc.toFixed(1) + '%'}>
                <a
                  className="draft-bar translate-tooltip"
                  style={{
                    width: draftPerc + '%',
                  }}
                  ref={draftTooltip}
                />
              </Tooltip>
            </>
          )}
        </div>
        {showPercent && (
          <div className="percent">
            <span id="stat-progress" data-testid="progress-bar-amount">
              {totalPerc ? Math.round(totalPerc) : '-'}
            </span>
            %
          </div>
        )}
      </div>
    </div>
  )
}

export default JobProgressBar
