import React from 'react'

import TagUtils from '../../utils/tagUtils'
import SegmentUtils from '../../utils/segmentUtils'
import SearchUtils from '../header/cattol/search/searchUtils'
import {SegmentContext} from './SegmentContext'

class SimpleEditor extends React.Component {
  static contextType = SegmentContext

  constructor(props) {
    super(props)
  }

  render() {
    const {isTarget, text} = this.props
    const {segment} = this.context
    const sid = segment.sid

    let htmlText = SegmentUtils.checkCurrentSegmentTPEnabled(segment)
      ? TagUtils.removeAllTags(text)
      : text

    htmlText = TagUtils.matchTag(
      TagUtils.decodeHtmlInTag(
        TagUtils.decodePlaceholdersToTextSimple(htmlText),
        config.isTargetRTL,
      ),
    )

    if (segment.inSearch) {
      htmlText = SearchUtils.markText(htmlText, !isTarget, sid)
    }

    return (
      <div
        className={`${isTarget ? 'target' : 'source'} item`}
        id={`segment-${sid}-${isTarget ? 'target' : 'source'}`}
      >
        <div
          className={isTarget ? `targetarea editarea` : ``}
          dangerouslySetInnerHTML={{__html: htmlText}}
        />
      </div>
    )
  }
}

export default SimpleEditor
