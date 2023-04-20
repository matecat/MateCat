import React, {Fragment, useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'
import CommonUtils from '../../utils/commonUtils'
import Cookies from 'js-cookie'

export const TabConcordanceResults = ({segment}) => {
  const [results, setResults] = useState(undefined)
  const [isExtended, setIsExtended] = useState(
    Cookies.get('segment_footer_extendend_concordance') === 'true',
  )

  useEffect(() => {
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
          const source = TagUtils.decodePlaceholdersToTextSimple(
            item.segment
              .replace(/&/g, '&amp;')
              .replace(/</gi, '&lt;')
              .replace(/>/gi, '&gt;'),
          )
            .replace(/#\{/gi, '<mark>')
            .replace(/\}#/gi, '</mark>')

          const translation = TagUtils.decodePlaceholdersToTextSimple(
            item.translation
              .replace(/&/g, '&amp;')
              .replace(/</gi, '&lt;')
              .replace(/>/gi, '&gt;'),
          )
            .replace(/#\{/gi, '<mark>')
            .replace(/\}#/gi, '</mark>')

          return {
            ...item,
            segment: source,
            translation,
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
  }, [])

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

  const getContentWithMarkTag = (content) =>
    content
      .split('<mark>')
      .map((value, index) =>
        index > 0 ? {tagContent: value.split('</mark>')} : value,
      )
      .reduce(
        (acc, cur) => [
          ...acc,
          typeof cur === 'object'
            ? [{tagContent: cur.tagContent[0]}, cur.tagContent[1]]
            : [cur],
        ],
        [],
      )
      .flat()
      .map((piece, index) =>
        typeof piece === 'object' ? (
          <mark key={index}>{piece.tagContent}</mark>
        ) : (
          <Fragment key={index}>{piece}</Fragment>
        ),
      )

  const renderResults = (item, index) => {
    const {sid} = segment

    return (
      <ul
        key={index}
        className={`graysmall ${index < MAX_ITEMS_TO_DISPLAY ? ' prime' : ''}`}
        data-item={index + 1}
        data-id={item.id}
      >
        <li className={'sugg-source'}>
          <span
            id={sid + '-tm-' + item.id + '-source'}
            className={'suggestion_source'}
          >
            {getContentWithMarkTag(item.segment)}
          </span>
        </li>
        <li className={'b sugg-target'}>
          <span
            id={sid + '-tm-' + item.id + '-translation'}
            className={'translation'}
          >
            {getContentWithMarkTag(item.translation)}
          </span>
        </li>
        <ul className={'graysmall-details'}>
          <li>{item.last_update_date}</li>
          <li className={'graydesc'}>
            <span className={'bold'}>
              {CommonUtils.getLanguageNameFromLocale(item.target)}
            </span>
          </li>
          <li className={'graydesc'}>
            Source: <span className={'bold'}>{item.created_by}</span>
          </li>
        </ul>
      </ul>
    )
  }

  const resultsDisplaying =
    results && (isExtended ? results : [...results].splice(0, 3))

  return (
    <div>
      {Array.isArray(resultsDisplaying) &&
        (resultsDisplaying.length > 0 ? (
          <div>
            <ul>{resultsDisplaying.map(renderResults)}</ul>
            <a className={'more'} onClick={toggleExtendend}>
              {isExtended ? 'Fewer' : 'More'}
            </a>
          </div>
        ) : (
          <ul className={'graysmall message prime'}>
            <li>
              Can&apos;t find any matches. Check the language combination.
            </li>
          </ul>
        ))}
    </div>
  )
}

TabConcordanceResults.propTypes = {
  segment: PropTypes.object,
}
