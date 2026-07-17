import React from 'react'
import SegmentFilter from './segment_filter/segment_filter'

export const SegmentsFilterButton = () => {
  const openSegmetsFilters = (event) => {
    event.preventDefault()
    if (!SegmentFilter.open) {
      SegmentFilter.openFilter()
    } else {
      SegmentFilter.closeFilter()
      SegmentFilter.open = false
    }
  }
  return (
    <>
      {config.segmentFilterEnabled && (
        <div
          className="action-submenu ui floating"
          id="action-filter"
          title="Filter segments"
          onClick={openSegmetsFilters}
        >
          <svg
            width="30px"
            height="30px"
            viewBox="-6 -5 33 33"
            version="1.1"
            xmlns="http://www.w3.org/2000/svg"
          >
            <g
              id="Icon/Filter/Active"
              stroke="none"
              strokeWidth="1"
              fill="none"
              fillRule="evenodd"
            >
              <g id="filter" fill="none">
                <path
                  strokeWidth="1.5"
                  stroke="#fff"
                  d="M22.9660561,1.79797063e-06 L1.03369998,1.79797063e-06 C0.646872201,-0.00071025154 0.292239364,0.210114534 0.115410779,0.545863698 C-0.0638568515,0.88613389 -0.0323935402,1.29588589 0.196629969,1.60665014 L8.23172155,12.6494896 C8.23440448,12.6532968 8.2373313,12.6568661 8.24001423,12.6606733 C8.53196433,13.0452025 8.68976863,13.510873 8.69074424,13.9896308 L8.69074424,22.9927526 C8.68903691,23.2594959 8.79635358,23.5155313 8.98903581,23.7047026 C9.18171797,23.8938738 9.44366823,24.0000018 9.71683793,24.0000018 C9.85586177,24.0000018 9.99317834,23.9728736 10.1214705,23.9210002 L14.6365754,22.2413027 C15.041208,22.1208994 15.3097436,21.7485057 15.3097436,21.2999677 L15.3097436,13.9896308 C15.3104753,13.5111109 15.4685235,13.0452025 15.7602297,12.6606733 C15.7629126,12.6568661 15.7658394,12.6532968 15.7685223,12.6494896 L23.80337,1.60617426 C24.0323936,1.2956479 24.0638568,0.88613389 23.8845893,0.545863698 C23.7077606,0.210114534 23.3531278,-0.00071025154 22.9660561,1.79797063e-06 Z"
                  id="Shape"
                />
              </g>
            </g>
          </svg>
        </div>
      )}
    </>
  )
}
