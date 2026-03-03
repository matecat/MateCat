import React from 'react'

import SegmentQR from './SegmentQR'

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
      <div className="document-name top-10">
        FILE {props.file.get('file_name')}
      </div>
      <div className="qr-segments-list">{getSegments()}</div>
    </div>
  )
}

export default FileDetails
