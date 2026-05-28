import React from 'react'

import SegmentQR from './SegmentQR'
import FileIcon from '../../../img/icons/FileIcon'
import FileTypeFile from '../../../img/icons/FileTypeFile'
import CommonUtils from '../../utils/commonUtils'

function FileDetails(props) {
  const getSegments = () => {
    let segments = []
    props.segments.forEach((item) => {
      let segment = (
        <SegmentQR
          key={item.get('id')}
          segment={item}
          urls={props.urls}
          secondPassReviewEnabled={props.secondPassReviewEnabled}
          revisionToShow={props.revisionToShow}
        />
      )
      segments.push(segment)
    })
    return segments
  }

  return (
    <div className="qr-segments">
      <div className="document-name">
        {CommonUtils.getFileIcon(
          props.file.get('file_name').split('.').slice(-1)[0],
          16,
        )}
        FILE {props.file.get('file_name')}
      </div>
      <div className="qr-segments-list">{getSegments()}</div>
    </div>
  )
}

export default FileDetails
