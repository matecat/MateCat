import React from 'react'
import {isUndefined, size} from 'lodash'
import {fromJS} from 'immutable'

import SegmentActions from '../../actions/SegmentActions'
import SegmentView from '../segmentView/SegmentView'
import {Button, BUTTON_MODE, BUTTON_SIZE} from '../common/Button/Button'

const chooseAlternative = (segment, text) => {
  SegmentActions.setFocusOnEditArea()
  SegmentActions.disableTPOnSegment(segment)
  setTimeout(() => {
    SegmentActions.replaceEditAreaTextContent(segment.sid, text)
    SegmentActions.modifiedTranslation(segment.sid, true)
  })
}

// Nothing guarantees the lists are arrays: a plain object is iterated by key,
// as $.each did (array indexes stay numbers, object keys stay strings).
const entriesOf = (list) =>
  Array.isArray(list)
    ? list.map((item, index) => [index, item])
    : Object.entries(list ?? {})

const renderAlternatives = (segment, alternatives) => {
  const segment_id = segment.sid
  let html = []
  entriesOf(alternatives.editable).forEach(([index, item]) => {
    html.push(
      <ul
        className="graysmall"
        data-item={index + 1}
        key={'editable' + index}
        onDoubleClick={() => chooseAlternative(segment, item.translation)}
      >
        <li className="sugg-source">
          {/* No diff on the source */}
          <SegmentView
            id={segment_id + '-tm-' + item.id + '-source'}
            className="suggestion_source"
            segment={segment.segment}
            isRtl={!!config.isSourceRTL}
          />
        </li>
        <li className="b sugg-target">
          {/* Diff from the segment translation to the alternative */}
          <SegmentView
            className="translation"
            segment={item.translation}
            compareTo={segment.translation}
            isRtl={!!config.isTargetRTL}
          />
        </li>
        <li className="goto">
          <Button
            mode={BUTTON_MODE.LINK}
            size={BUTTON_SIZE.LINK_SMALL}
            onClick={() => SegmentActions.openSegment(item.involved_id[0])}
          >
            Go to
          </Button>
        </li>
      </ul>,
    )
  })

  entriesOf(alternatives.not_editable).forEach(([index1, item]) => {
    html.push(
      <ul
        className="graysmall notEditable"
        data-item={index1 + alternatives.editable?.length + 1}
        key={'not-editable' + index1}
        onDoubleClick={() => chooseAlternative(segment, item.translation)}
      >
        <li className="sugg-source">
          {/* No diff on the source */}
          <SegmentView
            id={segment_id + '-tm-' + item.id + '-source'}
            className="suggestion_source"
            segment={segment.segment}
            isRtl={!!config.isSourceRTL}
          />
        </li>
        <li className="b sugg-target">
          {/* Diff from the segment translation to the alternative */}
          <SegmentView
            className="translation"
            segment={item.translation}
            compareTo={segment.translation}
            isRtl={!!config.isTargetRTL}
          />
        </li>
        <li className="goto">
          <a
            data-goto={item.involved_id[0]}
            onClick={() => SegmentActions.openSegment(item.involved_id[0])}
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
