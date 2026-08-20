import React, {memo, createRef, useContext} from 'react'
import {fromJS} from 'immutable'
import {isUndefined} from 'lodash'
import $ from 'jquery'

import TextUtils from '../../utils/textUtils'
import TranslationMatches from './utils/translationMatches'
import SegmentActions from '../../actions/SegmentActions'
import {SegmentContext} from './SegmentContext'
import {SegmentFooterTabError} from './SegmentFooterTabError'
import DraftMatecatUtils from './utils/DraftMatecatUtils'

const SegmentFooterMultiMatches = memo(
  (props) => {
    const {clientConnected} = useContext(SegmentContext)

    const processContributions = (matches) => {
      var matchesProcessed = []
      // SegmentActions.createFooter(props.segment.sid);
      $.each(matches, function () {
        if (
          isUndefined(this.segment) ||
          this.segment === '' ||
          this.translation === ''
        )
          return true
        var item = {...this}
        item.id = this.id
        item.disabled = this.id == '0' ? true : false
        item.cb = this.created_by
        item.segment = this.segment
        if (
          'sentence_confidence' in this &&
          this.sentence_confidence !== '' &&
          this.sentence_confidence !== 0 &&
          this.sentence_confidence != '0' &&
          this.sentence_confidence !== null &&
          this.sentence_confidence !== false &&
          typeof this.sentence_confidence != 'undefined'
        ) {
          item.suggestion_info =
            'Quality: <b>' + this.sentence_confidence + '</b>'
        } else if (this.match != 'MT') {
          item.suggestion_info = this.last_update_date
        } else {
          item.suggestion_info = ''
        }

        item.percentText = TranslationMatches.getPercentTextForMatch(this)
        item.percentClass = TranslationMatches.getPercentageClass(this)

        // Attention Bug: We are mixing the view mode and the raw data mode.
        // before doing a enhanced  view you will need to add a data-original tag
        //
        item.suggestionDecodedHtml = DraftMatecatUtils.transformTagsToHtml(
          this.segment,
          config.isTargetRTL,
        )
        item.translationDecodedHtml = DraftMatecatUtils.transformTagsToHtml(
          this.translation,
          config.isTargetRTL,
        )
        item.translation = this.translation
        item.sourceDiff = item.suggestionDecodedHtml

        if (
          this.match !== 'MT' &&
          parseInt(this.match) > 74 &&
          parseInt(this.match) < 100
        ) {
          item.sourceDiff = TextUtils.getDiffHtml(
            this.segment,
            props.segment.segment,
          )

          item.sourceDiff = DraftMatecatUtils.transformTagsToHtml(
            item.sourceDiff,
            config.isSourceRTL,
          )
        }
        if (!isUndefined(this.tm_properties)) {
          item.tm_properties = this.tm_properties
        }

        matchesProcessed.push(item)
      })
      return matchesProcessed
    }

    const getMatchInfo = (match) => {
      return (
        <ul className="graysmall-details">
          <li className="graydesc">
            Origin:
            <span className="bold"> {match.cb}</span>
          </li>
          <li>{match.suggestion_info}</li>
          <li
            ref={createRef()}
            className={`percent  ${
              match.source !== config.source_code
                ? 'per-yellow-variant'
                : 'per-green'
            }`}
          >
            {match.source} {'>'} {match.target}{' '}
            {match.source !== config.source_code ? '(-1%)' : ''}
          </li>
          <li className={'percent ' + match.percentClass}>
            {match.percentText}
          </li>
          {/*<li className="graydesc">
          Target:
          <span className="bold" style={{fontSize: '14px'}}>
            {' '}
            {match.target}
          </span>
        </li>*/}
        </ul>
      )
    }

    const suggestionDblClick = (match) => {
      SegmentActions.setFocusOnEditArea()
      SegmentActions.disableTPOnSegment(props.segment)
      setTimeout(() => {
        SegmentActions.replaceEditAreaTextContent(
          props.segment.sid,
          match.translation,
        )
      }, 200)
    }

    const allowHTML = (string) => {
      return {__html: string}
    }

    var matches = []
    if (
      props.segment.cl_contributions &&
      props.segment.cl_contributions.matches &&
      props.segment.cl_contributions.matches.length > 0
    ) {
      let tpmMatches = processContributions(
        props.segment.cl_contributions.matches,
      )
      tpmMatches.forEach(function (match, index) {
        var item = (
          <ul
            key={match.id + index}
            className="suggestion-item crosslang-item graysmall"
            data-item={index + 1}
            data-id={match.id}
            data-original={match.segment}
            onDoubleClick={suggestionDblClick.bind(null, match, index + 1)}
          >
            <li className="sugg-source">
              <span
                id={props.segment.sid + '-tm-' + match.id + '-source'}
                className="suggestion_source"
                dangerouslySetInnerHTML={allowHTML(match.sourceDiff)}
              ></span>
            </li>
            <li className="b sugg-target">
              <span
                id={props.segment.sid + '-tm-' + match.id + '-translation'}
                className="translation"
                dangerouslySetInnerHTML={allowHTML(
                  match.translationDecodedHtml,
                )}
              ></span>
            </li>
            {getMatchInfo(match)}
          </ul>
        )
        matches.push(item)
      })
    } else if (
      props.segment.cl_contributions &&
      props.segment.cl_contributions.matches &&
      props.segment.cl_contributions.matches.length === 0
    ) {
      if (config.mt_enabled) {
        matches.push(
          <ul key={0} className="graysmall message">
            <li>
              There are no matches for this segment in the languages you have
              selected. Please, contact{' '}
              <a href="mailto:support@matecat.com">support@matecat.com</a> if
              you think this is an error.
            </li>
          </ul>,
        )
      } else {
        matches.push(
          <ul key={0} className="graysmall message">
            <li>
              There are no matches for this segment in the languages you have
              selected.
            </li>
          </ul>,
        )
      }
    }
    return (
      <div
        key={'container_' + props.code}
        className={
          'tab sub-editor ' + props.active_class + ' ' + props.tab_class
        }
        id={'segment-' + props.segment.sid + '-' + props.tab_class}
      >
        {clientConnected ? (
          <div className="overflow">
            {!isUndefined(matches) && matches.length > 0 ? (
              matches
            ) : (
              <span className="loader loader_on" />
            )}
          </div>
        ) : (
          clientConnected === false && <SegmentFooterTabError />
        )}
      </div>
    )
  },
  (prevProps, nextProps) =>
    !(
      ((!isUndefined(nextProps.segment.cl_contributions) ||
        !isUndefined(prevProps.segment.cl_contributions)) &&
        ((!isUndefined(nextProps.segment.cl_contributions) &&
          isUndefined(prevProps.segment.cl_contributions)) ||
          !fromJS(prevProps.segment.cl_contributions).equals(
            fromJS(nextProps.segment.cl_contributions),
          ))) ||
      prevProps.active_class !== nextProps.active_class ||
      prevProps.tab_class !== nextProps.tab_class
    ),
)

SegmentFooterMultiMatches.displayName = 'SegmentFooterMultiMatches'

export default SegmentFooterMultiMatches
