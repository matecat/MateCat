import React from 'react'

import SegmentQR from './SegmentQR'

class FileDetails extends React.Component {
  getSegments() {
    let segments = []
    this.props.segments.forEach((item) => {
      let segment = (
        <SegmentQR
          key={item.get('id')}
          segment={item}
          urls={this.props.urls}
          secondPassReviewEnabled={this.props.secondPassReviewEnabled}
          revisionToShow={this.props.revisionToShow}
        />
      )
      segments.push(segment)
    })
    return segments
  }

  render() {
    return (
      <div className="qr-segments">
        <div className="document-name top-10">
          FILE {this.props.file.get('file_name')}
        </div>
        <div className="qr-segments-list">{this.getSegments()}</div>
      </div>
    )
  }
}

export default FileDetails
