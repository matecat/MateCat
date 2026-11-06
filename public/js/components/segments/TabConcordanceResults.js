import React, {
  useEffect,
  useImperativeHandle,
  useState,
  forwardRef,
  createRef,
} from 'react'
import PropTypes from 'prop-types'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'
import Cookies from 'js-cookie'
import SegmentView from '../segmentView/SegmentView'
import {marksFromDelimiters} from '../segmentView/segmentModel'
import Tooltip from '../common/Tooltip'
import {Button, BUTTON_SIZE, BUTTON_TYPE} from '../common/Button/Button'
import IconDown from '../../../img/icons/IconDown'

export const TabConcordanceResults = forwardRef(({segment, isActive}, ref) => {
  const [results, setResults] = useState(undefined)
  const [isExtended, setIsExtended] = useState(
    Cookies.get('segment_footer_extendend_concordance') === 'true',
  )

  useImperativeHandle(ref, () => ({
    reset: () => setResults(undefined),
  }))

  useEffect(() => {
    if (!isActive) return

    const renderConcordances = (sid, data) => {
      const dataSorted = Array.isArray(data)
        ? data
            .filter(({segment, translation}) => segment && translation)
            .sort(
              (
                {last_update_date: lastUpdateA},
                {last_update_date: lastUpdateB},
              ) => (lastUpdateA < lastUpdateB ? 1 : -1),
            )
        : []

      setResults(
        dataSorted.map((item) => {
          // The service wraps the searched term in #{…}#
          const source = marksFromDelimiters(item.segment, '#{', '}#', {
            isRtl: !!config.isSourceRTL,
          })
          const translation = marksFromDelimiters(
            item.translation,
            '#{',
            '}#',
            {isRtl: !!config.isTargetRTL},
          )

          return {
            ...item,
            segment: source.segment,
            sourceMarks: source.marks,
            translation: translation.segment,
            translationMarks: translation.marks,
          }
        }),
      )
    }

    SegmentStore.addListener(
      SegmentConstants.CONCORDANCE_RESULT,
      renderConcordances,
    )

    return () => {
      SegmentStore.removeListener(
        SegmentConstants.CONCORDANCE_RESULT,
        renderConcordances,
      )
    }
  }, [isActive])

  const toggleExtendend = () => {
    if (isExtended) {
      Cookies.set('segment_footer_extendend_concordance', false, {
        expires: 3650,
        secure: true,
      })
    } else {
      Cookies.set('segment_footer_extendend_concordance', true, {
        expires: 3650,
        secure: true,
      })
    }
    setIsExtended(!isExtended)
  }

  const MAX_ITEMS_TO_DISPLAY = 3

  const renderResults = (item, index) => {
    const {sid} = segment
    return (
      <ul
        key={index}
        className={`graysmall ${index < MAX_ITEMS_TO_DISPLAY ? ' prime' : ''}`}
        data-item={index + 1}
        data-id={item.id}
      >
        <li className="sugg-source">
          <SegmentView
            id={`${sid}-tm-${item.id}-source`}
            className="suggestion_source"
            segment={item.segment}
            marks={item.sourceMarks}
            isRtl={!!config.isSourceRTL}
          />
        </li>
        <li className="b sugg-target">
          <SegmentView
            id={`${sid}-tm-${item.id}-translation`}
            className="translation"
            segment={item.translation}
            marks={item.translationMarks}
            isRtl={!!config.isTargetRTL}
          />
        </li>
        <ul className="graysmall-details">
          <li className="graydesc">
            Origin: <span className={'bold'}>{item.created_by}</span>
          </li>
          <li>{item.last_update_date}</li>
          <Tooltip
            content={
              <div
                style={{
                  display: 'flex',
                  flexDirection: 'column',
                }}
              >
                <span>
                  {item.target !== config.target_code ||
                  item.source !== config.source_code
                    ? 'Different language pair than the job'
                    : 'Same language pair as the job'}
                </span>
              </div>
            }
          >
            <li
              ref={createRef()}
              className={`percent ${
                item.target !== config.target_code ||
                item.source !== config.source_code
                  ? 'per-yellow-variant'
                  : 'per-green'
              } `}
            >
                {item.source} {'>'} {item.target}
            </li>
          </Tooltip>
          {/*<li className="graydesc">
            <span className="bold">
              {ApplicationStore.getLanguageNameFromLocale(item.target)}
            </span>
          </li>*/}
        </ul>
      </ul>
    )
  }

  const resultsDisplaying =
    results && (isExtended ? results : [...results].splice(0, 3))

  const moreButton = (
    <Button
      className={`segment-footer-tab-more-button ${isExtended ? 'segment-footer-tab-more-button-extended-mode' : ''}`}
      type={BUTTON_TYPE.DEFAULT}
      size={BUTTON_SIZE.SMALL}
      onClick={toggleExtendend}
    >
      <IconDown size={18} />
      {isExtended ? 'Fewer' : 'More'}
    </Button>
  )

  return (
    <div className="segment-footer-tab-concordance-results">
      {Array.isArray(resultsDisplaying) &&
        (resultsDisplaying.length > 0 ? (
          <div>
            <ul>{resultsDisplaying.map(renderResults)}</ul>
            {moreButton}
          </div>
        ) : (
          <ul className="graysmall message prime">
            <li>
              Can&apos;t find any matches. Check the language combination.
            </li>
          </ul>
        ))}
    </div>
  )
})

TabConcordanceResults.displayName = 'TabConcordanceResults'

TabConcordanceResults.propTypes = {
  segment: PropTypes.object,
  isActive: PropTypes.bool,
}
