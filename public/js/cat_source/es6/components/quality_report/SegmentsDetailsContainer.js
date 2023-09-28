import React from 'react'
import {debounce} from 'lodash'

import Filters from './FilterSegments'
import FileDetails from './FileDetails'
import QualityReportActions from '../../actions/QualityReportActions'

class SegmentsDetails extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      filter: null,
    }
  }
  getFiles() {
    let files = []
    if (this.props.files) {
      this.props.files.forEach((fileObj) => {
        let file = (
          <FileDetails
            revisionToShow={this.props.revisionToShow}
            key={fileObj.get('id')}
            file={fileObj}
            segments={this.props.segmentsFiles.get(
              fileObj.get('id').toString(),
            )}
            urls={this.props.urls}
            secondPassReviewEnabled={this.props.secondPassReviewEnabled}
          />
        )
        files.push(file)
      })
    }
    return files
  }

  scrollDebounceFn() {
    let self = this
    return debounce(function () {
      self.onScroll()
    }, 200)
  }

  onScroll() {
    let scrollableHeight =
      document.getElementById('qr-root').scrollHeight -
      document.getElementById('qr-root').clientHeight
    // When the user is [modifier]px from the bottom, fire the event.
    let modifier = 200
    if (
      document.getElementById('qr-root').scrollTop + modifier >
        scrollableHeight &&
      this.props.moreSegments
    ) {
      QualityReportActions.getMoreQRSegments(
        this.state.filter,
        this.props.lastSegment,
      )
    }
  }
  filterSegments(filter) {
    this.setState({
      filter: filter,
    })
    QualityReportActions.filterSegments(filter, null)
  }

  componentDidMount() {
    document
      .getElementById('qr-root')
      .addEventListener('scroll', this.scrollDebounceFn(), false)
  }

  componentWillUnmount() {
    document
      .getElementById('qr-root')
      .removeEventListener('scroll', this.scrollDebounceFn(), false)
  }

  render() {
    let totalSegments = 0
    if (this.props.segmentsFiles) {
      this.props.segmentsFiles.forEach((ss) => (totalSegments += ss.size))
    }

    return (
      <div className="qr-segment-details-container shadow-2">
        <div className="qr-segments-summary">
          <div className="qr-filter-container">
            <h3>Segment details</h3>
            <Filters
              applyFilter={this.filterSegments.bind(this)}
              categories={this.props.categories}
              secondPassReviewEnabled={this.props.secondPassReviewEnabled}
              segmentToFilter={this.props.segmentToFilter}
              updateSegmentToFilter={this.props.updateSegmentToFilter}
            />
          </div>
          {this.props.files && this.props.files.length === 0 ? (
            <div className="no-segments-found">No segments found</div>
          ) : (
            this.getFiles()
          )}

          {this.props.moreSegments &&
          this.props.files &&
          this.props.files.size !== 0 &&
          totalSegments >= 20 ? (
            <div className="ui one column grid">
              <div className="one column spinner" style={{height: '100px'}}>
                <div className="ui active inverted dimmer">
                  <div className="ui medium text loader">
                    Loading more segments
                  </div>
                </div>
              </div>
            </div>
          ) : null}
        </div>
      </div>
    )
  }
}

export default SegmentsDetails
