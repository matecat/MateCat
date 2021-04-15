import SegmentQR from './SegmentQR'

class FileDetails extends React.Component {
  getSegments() {
    let segments = []
    this.props.file.get('segments').forEach((item) => {
      let segment = (
        <SegmentQR
          key={item.get('sid')}
          segment={item}
          urls={this.props.urls}
          secondPassReviewEnabled={this.props.secondPassReviewEnabled}
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
          FILE {this.props.file.get('filename')}
        </div>
        <div className="qr-segments-list">{this.getSegments()}</div>
      </div>
    )
  }
}

export default FileDetails
