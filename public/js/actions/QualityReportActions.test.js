jest.mock('../stores/AppDispatcher', () => ({
  dispatch: jest.fn(),
}))
jest.mock('../constants/QualityReportConstants', () => ({
  RENDER_SEGMENTS_QR: 'RENDER_SEGMENTS_QR',
  RENDER_REPORT: 'RENDER_REPORT',
  LOADING_MORE_SEGMENTS: 'LOADING_MORE_SEGMENTS',
  ADD_SEGMENTS_QR: 'ADD_SEGMENTS_QR',
  NO_MORE_SEGMENTS: 'NO_MORE_SEGMENTS',
}))
jest.mock('../api/getQualityReportSegmentsFiles', () => ({
  getQualityReportSegmentsFiles: jest.fn(),
}))
jest.mock('../api/getQualityReportInfo', () => ({
  getQualityReportInfo: jest.fn(),
}))

import QualityReportActions from './QualityReportActions'
import AppDispatcher from '../stores/AppDispatcher'
import {getQualityReportSegmentsFiles} from '../api/getQualityReportSegmentsFiles'
import {getQualityReportInfo} from '../api/getQualityReportInfo'

describe('QualityReportActions', () => {
  beforeEach(() => {
    jest.clearAllMocks()
  })

  test('loadInitialAjaxData dispatches RENDER_SEGMENTS_QR and RENDER_REPORT', async () => {
    getQualityReportSegmentsFiles.mockResolvedValueOnce({
      segments: [{id: 1}],
    })
    getQualityReportInfo.mockResolvedValueOnce({
      job: {chunks: [{id: 'chunk1'}]},
    })

    QualityReportActions.loadInitialAjaxData({filter: 'a'})
    await Promise.resolve()
    await Promise.resolve()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'RENDER_SEGMENTS_QR',
      files: {segments: [{id: 1}]},
    })
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'RENDER_REPORT',
      job: {id: 'chunk1'},
    })
  })

  test('loadInitialAjaxData does not dispatch when responses are empty', async () => {
    getQualityReportSegmentsFiles.mockResolvedValueOnce({})
    getQualityReportInfo.mockResolvedValueOnce({})

    QualityReportActions.loadInitialAjaxData({filter: 'a'})
    await Promise.resolve()
    await Promise.resolve()

    expect(AppDispatcher.dispatch).not.toHaveBeenCalled()
  })

  test('getMoreQRSegments dispatches LOADING_MORE_SEGMENTS then ADD_SEGMENTS_QR', async () => {
    getQualityReportSegmentsFiles.mockResolvedValueOnce({
      segments: [{id: 1}],
    })

    QualityReportActions.getMoreQRSegments('filter', 5)
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'LOADING_MORE_SEGMENTS',
    })

    await Promise.resolve()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'ADD_SEGMENTS_QR',
      files: {segments: [{id: 1}]},
    })
  })

  test('getMoreQRSegments dispatches NO_MORE_SEGMENTS when segments are empty', async () => {
    getQualityReportSegmentsFiles.mockResolvedValueOnce({segments: []})

    QualityReportActions.getMoreQRSegments('filter', 5)
    await Promise.resolve()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'NO_MORE_SEGMENTS',
    })
  })

  test('filterSegments dispatches RENDER_SEGMENTS_QR', async () => {
    getQualityReportSegmentsFiles.mockResolvedValueOnce({
      segments: [{id: 2}],
    })

    QualityReportActions.filterSegments('filter', 5)
    await Promise.resolve()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'RENDER_SEGMENTS_QR',
      files: {segments: [{id: 2}]},
    })
  })
})
