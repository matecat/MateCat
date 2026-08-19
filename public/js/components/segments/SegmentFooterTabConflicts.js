import React, {memo} from 'react'
import {isUndefined, size} from 'lodash'
import {fromJS} from 'immutable'
import $ from 'jquery'

import TextUtils from '../../utils/textUtils'
import SegmentActions from '../../actions/SegmentActions'
import DraftMatecatUtils from './utils/DraftMatecatUtils'

const SegmentFooterTabConflicts = memo((props) => {
  const allowHTML = (string) => ({__html: string})

  const chooseAlternative = (text) => {
    SegmentActions.setFocusOnEditArea()
    SegmentActions.disableTPOnSegment(props.segment)
    setTimeout(() => {
      SegmentActions.replaceEditAreaTextContent(props.segment.sid, text)
      SegmentActions.modifiedTranslation(props.segment.sid, true)
    })
  }

  const renderAlternatives = (alternatives) => {
    const segment = props.segment
    const segment_id = props.segment.sid
    let html = []
    const source = DraftMatecatUtils.transformTagsToHtml(
      segment.segment,
      config.isSourceRTL,
    )
    $.each(alternatives.editable, function (index) {
      // Execute diff
      const segmentTranslation = segment.translation
      const conflictTranslation = this.translation
      let translation = TextUtils.getDiffHtml(
        segmentTranslation,
        conflictTranslation,
      )
      // let translation = TextUtils.diffMatchPatch.diff_prettyHtml(diff_obj)
      translation = DraftMatecatUtils.transformTagsToHtml(
        translation,
        config.isTargetRTL,
      )
      // No diff executed on source
      html.push(
        <ul
          className="graysmall"
          data-item={index + 1}
          key={'editable' + index}
          onDoubleClick={() => chooseAlternative(this.translation)}
        >
          <li className="sugg-source">
            <span
              id={segment_id + '-tm-' + this.id + '-source'}
              className="suggestion_source"
              dangerouslySetInnerHTML={allowHTML(source)}
            />
          </li>
          <li className="b sugg-target">
            {/*<span className="graysmall-message">{'CTRL' + (index + 1)}</span>*/}
            <span
              className="translation"
              dangerouslySetInnerHTML={allowHTML(translation)}
            />
            <span
              className="realData hide"
              dangerouslySetInnerHTML={allowHTML(this.translation)}
            />
          </li>
          <li className="goto">
            <a
              data-goto={this.involved_id[0]}
              onClick={() => SegmentActions.openSegment(this.involved_id[0])}
            >
              Go to
            </a>
          </li>
        </ul>,
      )
    })

    $.each(alternatives.not_editable, function (index1) {
      // Execute diff
      let diff_obj = TextUtils.execDiff(segment.translation, this.translation)
      // Restore Tags
      let translation = TextUtils.diffMatchPatch.diff_prettyHtml(diff_obj)
      translation = translation.replace(/&amp;/g, '&')
      translation = DraftMatecatUtils.transformTagsToHtml(
        translation,
        config.isTargetRTL,
      )
      // No diff executed on source
      html.push(
        <ul
          className="graysmall notEditable"
          data-item={index1 + alternatives.editable.length + 1}
          key={'not-editable' + index1}
          onDoubleClick={() => chooseAlternative(this.translation)}
        >
          <li className="sugg-source">
            <span
              id={segment_id + '-tm-' + this.id + '-source'}
              className="suggestion_source"
              dangerouslySetInnerHTML={allowHTML(source)}
            />
          </li>
          <li className="b sugg-target">
            {/*<span className="graysmall-message">{'CTRL+' + (index1 + alternatives.data.editable.length + 1)}</span>*/}
            <span
              className="translation"
              dangerouslySetInnerHTML={allowHTML(translation)}
            />
            <span
              className="realData hide"
              dangerouslySetInnerHTML={allowHTML(this.translation)}
            />
          </li>
          <li className="goto">
            <a
              data-goto={this.involved_id[0]}
              onClick={() => SegmentActions.openSegment(this.involved_id[0])}
            >
              Go to
            </a>
          </li>
        </ul>,
      )
    })

    return html
  }

  if (props.segment.alternatives && size(props.segment.alternatives) > 0) {
    const html = renderAlternatives(props.segment.alternatives)
    return (
      <div
        key={'container_' + props.code}
        className={
          'tab sub-editor ' + props.active_class + ' ' + props.tab_class
        }
        id={'segment-' + props.segment.sid + '-' + props.tab_class}
      >
        <div className="overflow">{html}</div>
      </div>
    )
  } else {
    return ''
  }
}, (prevProps, nextProps) =>
  !(
    prevProps.active_class !== nextProps.active_class ||
    prevProps.tab_class !== nextProps.tab_class ||
    ((!isUndefined(nextProps.segment.alternatives) ||
      !isUndefined(prevProps.segment.alternatives)) &&
      ((!isUndefined(nextProps.segment.alternatives) &&
        isUndefined(prevProps.segment.alternatives)) ||
        !fromJS(prevProps.segment.alternatives).equals(
          fromJS(nextProps.segment.alternatives),
        )))
  ),
)

SegmentFooterTabConflicts.displayName = 'SegmentFooterTabConflicts'

export default SegmentFooterTabConflicts
