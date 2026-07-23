import AppDispatcher from './AppDispatcher'
import QualityReportStore from './QualityReportStore'
import QRConstants from '../constants/QualityReportConstants'
import {fromJS} from 'immutable'

describe('QualityReportStore', () => {
  beforeEach(() => {
    QualityReportStore._segmentsFiles = fromJS({})
    QualityReportStore._files = fromJS({})
    QualityReportStore._jobInfo = fromJS({})
    QualityReportStore._lastSegment = null
    jest.clearAllMocks()
  })

  test('storeSegments groups segments by file and stores last segment id', () => {
    QualityReportStore.storeSegments({
      segments: [
        {sid: 1, file: {id: 1, name: 'file1'}},
        {sid: 2, file: {id: 1, name: 'file1'}},
        {sid: 3, file: {id: 2, name: 'file2'}},
      ],
      _links: {last_segment_id: 3},
    })

    expect(QualityReportStore._segmentsFiles.get('1').size).toBe(2)
    expect(QualityReportStore._segmentsFiles.get('2').size).toBe(1)
    expect(QualityReportStore._files.get('1').get('name')).toBe('file1')
    expect(QualityReportStore._lastSegment).toBe(3)
  })

  test('storeSegments sets lastSegment undefined when there are no segments', () => {
    QualityReportStore.storeSegments({
      segments: [],
      _links: {last_segment_id: 3},
    })

    expect(QualityReportStore._lastSegment).toBeUndefined()
  })

  test('storeJobInfo stores the job as immutable data', () => {
    QualityReportStore.storeJobInfo({id: 10, name: 'job'})

    expect(QualityReportStore._jobInfo.get('name')).toBe('job')
  })

  test('addSegments appends to an existing file and creates a new file entry', () => {
    QualityReportStore.storeSegments({
      segments: [{sid: 1, file: {id: 1, name: 'file1'}}],
      _links: {last_segment_id: 1},
    })

    QualityReportStore.addSegments({
      segments: [
        {sid: 2, file: {id: 1, name: 'file1'}},
        {sid: 3, file: {id: 2, name: 'file2'}},
      ],
      _links: {last_segment_id: 3},
    })

    expect(QualityReportStore._segmentsFiles.get('1').size).toBe(2)
    expect(QualityReportStore._segmentsFiles.get('2').size).toBe(1)
    expect(QualityReportStore._files.get('2').get('name')).toBe('file2')
    expect(QualityReportStore._lastSegment).toBe(3)
  })

  test('RENDER_SEGMENTS_QR action stores segments and emits change', () => {
    const emitSpy = jest.spyOn(QualityReportStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: QRConstants.RENDER_SEGMENTS_QR,
      files: {
        segments: [{sid: 1, file: {id: 1, name: 'file1'}}],
        _links: {last_segment_id: 1},
      },
    })

    expect(emitSpy).toHaveBeenCalledWith(
      QRConstants.RENDER_SEGMENTS_QR,
      QualityReportStore._segmentsFiles,
      QualityReportStore._files,
      QualityReportStore._lastSegment,
    )
  })

  test('ADD_SEGMENTS_QR action adds segments and emits render event', () => {
    QualityReportStore.storeSegments({
      segments: [{sid: 1, file: {id: 1, name: 'file1'}}],
      _links: {last_segment_id: 1},
    })
    const emitSpy = jest.spyOn(QualityReportStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: QRConstants.ADD_SEGMENTS_QR,
      files: {
        segments: [{sid: 2, file: {id: 1, name: 'file1'}}],
        _links: {last_segment_id: 2},
      },
    })

    expect(emitSpy).toHaveBeenCalledWith(
      QRConstants.RENDER_SEGMENTS_QR,
      QualityReportStore._segmentsFiles,
      QualityReportStore._files,
      QualityReportStore._lastSegment,
    )
  })

  test('RENDER_REPORT action stores job info and emits change', () => {
    const emitSpy = jest.spyOn(QualityReportStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: QRConstants.RENDER_REPORT,
      job: {id: 5, name: 'job5'},
    })

    expect(emitSpy).toHaveBeenCalledWith(
      QRConstants.RENDER_REPORT,
      QualityReportStore._jobInfo,
    )
  })

  test('NO_MORE_SEGMENTS action emits change with action type', () => {
    const emitSpy = jest.spyOn(QualityReportStore, 'emitChange')

    AppDispatcher.dispatch({actionType: QRConstants.NO_MORE_SEGMENTS})

    expect(emitSpy).toHaveBeenCalledWith(QRConstants.NO_MORE_SEGMENTS)
  })

  test('LOADING_MORE_SEGMENTS action emits change with action type', () => {
    const emitSpy = jest.spyOn(QualityReportStore, 'emitChange')

    AppDispatcher.dispatch({actionType: QRConstants.LOADING_MORE_SEGMENTS})

    expect(emitSpy).toHaveBeenCalledWith(QRConstants.LOADING_MORE_SEGMENTS)
  })
})
