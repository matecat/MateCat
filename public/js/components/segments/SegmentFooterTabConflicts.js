import React from 'react'
import {isUndefined, size} from 'lodash'
import {fromJS} from 'immutable'
import $ from 'jquery'

import TextUtils from '../../utils/textUtils'
import SegmentActions from '../../actions/SegmentActions'
import DraftMatecatUtils from './utils/DraftMatecatUtils'

const allowHTML = (string) => ({__html: string})

const chooseAlternative = (segment, text) => {
  SegmentActions.setFocusOnEditArea()
  SegmentActions.disableTPOnSegment(segment)
  setTimeout(() => {
    SegmentActions.replaceEditAreaTextContent(segment.sid, text)
    SegmentActions.modifiedTranslation(segment.sid, true)
  })
}

// $.each is kept rather than forEach: it iterates arrays and plain objects
// alike, and nothing here guarantees `editable` is an array.
const renderAlternatives = (segment, alternatives) => {
  const segment_id = segment.sid
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
        onDoubleClick={() => chooseAlternative(segment, this.translation)}
      >
        <li className="sugg-source">
          <span
            id={segment_id + '-tm-' + this.id + '-source'}
            className="suggestion_source"
            dangerouslySetInnerHTML={allowHTML(source)}
          />
        </li>
        <li className="b sugg-target">
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
        onDoubleClick={() => chooseAlternative(segment, this.translation)}
      >
        <li className="sugg-source">
          <span
            id={segment_id + '-tm-' + this.id + '-source'}
            className="suggestion_source"
            dangerouslySetInnerHTML={allowHTML(source)}
          />
        </li>
        <li className="b sugg-target">
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

const SegmentFooterTabConflicts = ({
  segment,
  active_class,
  tab_class,
  code,
}) => {
  if (!segment.alternatives || size(segment.alternatives) === 0) return ''

  return (
    <div
      key={'container_' + code}
      className={'tab sub-editor ' + active_class + ' ' + tab_class}
      id={'segment-' + segment.sid + '-' + tab_class}
    >
      <div className="overflow">
        {renderAlternatives(segment, segment.alternatives)}
      </div>
    </div>
  )
}

// The class's shouldComponentUpdate, negated: memo skips the render when the
// comparator says the props are equal, where sCU said when to re-render.
export default React.memo(
  SegmentFooterTabConflicts,
  (prev, next) =>
    !(
      prev.active_class !== next.active_class ||
      prev.tab_class !== next.tab_class ||
      ((!isUndefined(next.segment.alternatives) ||
        !isUndefined(prev.segment.alternatives)) &&
        ((!isUndefined(next.segment.alternatives) &&
          isUndefined(prev.segment.alternatives)) ||
          !fromJS(prev.segment.alternatives).equals(
            fromJS(next.segment.alternatives),
          )))
    ),
)
