import React, {useState, useCallback, useEffect, useRef} from 'react'
import {debounce} from 'lodash'

import Filters from './FilterSegments'
import FileDetails from './FileDetails'
import QualityReportActions from '../../actions/QualityReportActions'

function SegmentsDetails(props) {
  const [filter, setFilter] = useState(null)
  const scrollDebounceFnRef = useRef(null)

  const getFiles = useCallback(() => {
    let files = []
    if (props.files) {
      props.files.forEach((fileObj) => {
        let file = (
          <FileDetails
            revisionToShow={props.revisionToShow}
            key={fileObj.get('id')}
            file={fileObj}
            segments={props.segmentsFiles.get(fileObj.get('id').toString())}
            urls={props.urls}
            secondPassReviewEnabled={props.secondPassReviewEnabled}
          />
        )
        files.push(file)
      })
    }
    return files
  }, [
    props.files,
    props.revisionToShow,
    props.segmentsFiles,
    props.urls,
    props.secondPassReviewEnabled,
  ])

  const onScroll = useCallback(() => {
    let scrollableHeight =
      document.getElementById('qr-root').scrollHeight -
      document.getElementById('qr-root').clientHeight
    // When the user is [modifier]px from the bottom, fire the event.
    let modifier = 200
    if (
      document.getElementById('qr-root').scrollTop + modifier >
        scrollableHeight &&
      props.moreSegments
    ) {
      QualityReportActions.getMoreQRSegments(filter, props.lastSegment)
    }
  }, [filter, props.moreSegments, props.lastSegment])

  // Debounced scroll handler
  useEffect(() => {
    scrollDebounceFnRef.current = debounce(onScroll, 200)
    const qrRoot = document.getElementById('qr-root')
    if (qrRoot) {
      qrRoot.addEventListener('scroll', scrollDebounceFnRef.current, false)
    }
    return () => {
      if (qrRoot && scrollDebounceFnRef.current) {
        qrRoot.removeEventListener('scroll', scrollDebounceFnRef.current, false)
      }
      if (scrollDebounceFnRef.current && scrollDebounceFnRef.current.cancel) {
        scrollDebounceFnRef.current.cancel()
      }
    }
  }, [onScroll])

  const filterSegments = useCallback((filterValue) => {
    setFilter(filterValue)
    QualityReportActions.filterSegments(filterValue, null)
  }, [])

  let totalSegments = 0
  if (props.segmentsFiles) {
    props.segmentsFiles.forEach((ss) => (totalSegments += ss.size))
  }

  return (
    <div className="qr-segment-details-container">
      <div className="qr-segments-summary">
        <div className="qr-filter-container">
          <h3>Segment details</h3>
          <Filters
            applyFilter={filterSegments}
            categories={props.categories}
            secondPassReviewEnabled={props.secondPassReviewEnabled}
            segmentToFilter={props.segmentToFilter}
            updateSegmentToFilter={props.updateSegmentToFilter}
          />
        </div>
        {props.files && props.files.length === 0 ? (
          <div className="no-segments-found">No segments found</div>
        ) : (
          getFiles()
        )}

        {props.moreSegments &&
        props.files &&
        props.files.size !== 0 &&
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

export default SegmentsDetails
