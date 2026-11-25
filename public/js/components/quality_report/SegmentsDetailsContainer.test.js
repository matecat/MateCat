import React from 'react'
import {render, act} from '@testing-library/react'
import {fromJS} from 'immutable'

import SegmentsDetails from './SegmentsDetailsContainer'
import QualityReportActions from '../../actions/QualityReportActions'

jest.mock('../../actions/QualityReportActions', () => ({
  __esModule: true,
  default: {filterSegments: jest.fn(), getMoreQRSegments: jest.fn()},
}))

let filtersProps
jest.mock('./FilterSegments', () => ({
  __esModule: true,
  default: (props) => {
    filtersProps = props
    return null
  },
}))

let fileDetailsProps
jest.mock('./FileDetails', () => ({
  __esModule: true,
  default: (props) => {
    fileDetailsProps = props
    return null
  },
}))

const files = fromJS([{id: 1}])
const segmentsFiles = fromJS({1: [{sid: 10}]})

const renderDetails = (props = {}) => {
  const allProps = {
    files,
    segmentsFiles,
    revisionToShow: '1',
    secondPassReviewEnabled: true,
    categories: fromJS([]),
    moreSegments: false,
    loadingMoreSegments: false,
    updateSegmentToFilter: jest.fn(),
    ...props,
  }
  const view = render(<SegmentsDetails {...allProps} />)
  return {
    ...view,
    rerenderWith: (next) =>
      view.rerender(<SegmentsDetails {...allProps} {...next} />),
  }
}

const applyFilter = (filter) => act(() => filtersProps.applyFilter(filter))

beforeEach(() => {
  jest.clearAllMocks()
  document.body.innerHTML = '<div id="qr-root"></div>'
})

describe('SegmentsDetailsContainer revision handling', () => {
  test('segments open on the page revision when no revision filter is set', () => {
    renderDetails({revisionToShow: '2'})

    expect(fileDetailsProps.revisionToShow).toBe('2')
  })

  test('segments open on the filtered revision, not the page revision', () => {
    renderDetails({revisionToShow: '2'})

    applyFilter({issues_in_r: '1', severity: null})

    expect(fileDetailsProps.revisionToShow).toBe('1')
  })

  test('segments follow the page revision again once the revision filter is reset', () => {
    renderDetails({revisionToShow: '2'})
    applyFilter({issues_in_r: '1'})

    applyFilter({issues_in_r: null})

    expect(fileDetailsProps.revisionToShow).toBe('2')
  })

  test('the page revision is not sent as a segment filter', () => {
    renderDetails({revisionToShow: '2'})

    applyFilter({severity: 'Minor'})

    expect(QualityReportActions.filterSegments).toHaveBeenCalledWith(
      {severity: 'Minor'},
      null,
    )
  })

  test('switching the page revision does not reload the segments', () => {
    const {rerenderWith} = renderDetails({revisionToShow: '1'})
    applyFilter({severity: 'Minor'})
    QualityReportActions.filterSegments.mockClear()

    rerenderWith({revisionToShow: '2'})

    expect(QualityReportActions.filterSegments).not.toHaveBeenCalled()
    expect(fileDetailsProps.revisionToShow).toBe('2')
  })
})
