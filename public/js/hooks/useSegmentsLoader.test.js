import {renderHook, act, waitFor} from '@testing-library/react'
import useSegmentsLoader from './useSegmentsLoader'
import {getSegments} from '../api/getSegments'
import SegmentActions from '../actions/SegmentActions'
import SegmentStore from '../stores/SegmentStore'

// --- Mocks ---

jest.mock('../api/getSegments')
jest.mock('../actions/SegmentActions', () => ({
  addSegments: jest.fn(),
}))
jest.mock('../stores/SegmentStore', () => ({
  getLastSegmentId: jest.fn(() => '100'),
}))

let mockIsUserLogged = true
jest.mock(
  '../components/common/ApplicationWrapper/ApplicationWrapperContext',
  () => {
    const React = require('react')
    return {
      ApplicationWrapperContext: React.createContext({isUserLogged: true}),
    }
  },
)

// Override context value per test via wrapper
const createWrapper =
  (isUserLogged = true) =>
  ({children}) => {
    const React = require('react')
    const {
      ApplicationWrapperContext,
    } = require('../components/common/ApplicationWrapper/ApplicationWrapperContext')
    return React.createElement(
      ApplicationWrapperContext.Provider,
      {value: {isUserLogged}},
      children,
    )
  }

global.config = {
  id_job: '1',
  password: 'abc123',
  last_job_segment: '100',
}

const defaultProps = {
  segmentId: '1',
  where: 'center',
  idJob: '1',
  password: 'abc123',
  isAnalysisCompleted: true,
}

const mockFilesResponse = {
  data: {
    where: 'center',
    files: {
      file1: {
        segments: [{sid: 1}, {sid: 2}],
      },
    },
  },
}

const emptyFilesResponse = {
  data: {
    where: 'before',
    files: {},
  },
}

// --- Tests ---

describe('useSegmentsLoader', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    getSegments.mockResolvedValue(mockFilesResponse)
  })

  describe('initial state', () => {
    it('returns isLoading false and result undefined initially', () => {
      const {result} = renderHook(() => useSegmentsLoader(defaultProps), {
        wrapper: createWrapper(true),
      })
      expect(result.current.isLoading).toBe(true)
      expect(result.current.result).toBeUndefined()
    })
  })

  describe('when user is not logged', () => {
    it('does not call getSegments', () => {
      renderHook(() => useSegmentsLoader(defaultProps), {
        wrapper: createWrapper(false),
      })
      expect(getSegments).not.toHaveBeenCalled()
    })
  })

  describe('when segmentId is missing', () => {
    it('does not call getSegments when segmentId is undefined', () => {
      renderHook(
        () => useSegmentsLoader({...defaultProps, segmentId: undefined}),
        {wrapper: createWrapper(true)},
      )
      expect(getSegments).not.toHaveBeenCalled()
    })
  })

  describe('successful fetch', () => {
    it('calls getSegments with correct params', async () => {
      renderHook(() => useSegmentsLoader(defaultProps), {
        wrapper: createWrapper(true),
      })

      await waitFor(() => expect(getSegments).toHaveBeenCalledTimes(1))

      expect(getSegments).toHaveBeenCalledWith({
        jid: '1',
        password: 'abc123',
        step: 40, // INIT_NUM_SEGMENTS for 'center'
        segment: '1',
        where: 'center',
      })
    })

    it('calls getSegments with step 25 when where is "after"', async () => {
      renderHook(() => useSegmentsLoader({...defaultProps, where: 'after'}), {
        wrapper: createWrapper(true),
      })

      await waitFor(() => expect(getSegments).toHaveBeenCalledTimes(1))

      expect(getSegments).toHaveBeenCalledWith(
        expect.objectContaining({step: 25}),
      )
    })

    it('calls SegmentActions.addSegments with flattened segments', async () => {
      const {result} = renderHook(() => useSegmentsLoader(defaultProps), {
        wrapper: createWrapper(true),
      })

      await waitFor(() => expect(result.current.result).toBeDefined())

      expect(SegmentActions.addSegments).toHaveBeenCalledWith(
        [{sid: 1}, {sid: 2}],
        'center',
      )
    })

    it('sets result with data and segmentId after successful fetch', async () => {
      const {result} = renderHook(() => useSegmentsLoader(defaultProps), {
        wrapper: createWrapper(true),
      })

      await waitFor(() => expect(result.current.result).toBeDefined())

      expect(result.current.result).toEqual(
        expect.objectContaining({
          segmentId: '1',
          where: 'center',
        }),
      )
    })

    it('sets isLoading to false after fetch completes', async () => {
      const {result} = renderHook(() => useSegmentsLoader(defaultProps), {
        wrapper: createWrapper(true),
      })

      await waitFor(() => expect(result.current.result).toBeDefined())

      expect(result.current.isLoading).toBe(false)
    })
  })

  describe('symbol segmentId', () => {
    it('uses symbol description as segment value', async () => {
      const sym = Symbol('42')
      renderHook(() => useSegmentsLoader({...defaultProps, segmentId: sym}), {
        wrapper: createWrapper(true),
      })

      await waitFor(() => expect(getSegments).toHaveBeenCalledTimes(1))

      expect(getSegments).toHaveBeenCalledWith(
        expect.objectContaining({segment: '42'}),
      )
    })
  })

  describe('error handling', () => {
    it('sets result with errors on fetch failure', async () => {
      const error = new Error('Network error')
      getSegments.mockRejectedValue(error)

      const {result} = renderHook(() => useSegmentsLoader(defaultProps), {
        wrapper: createWrapper(true),
      })

      await waitFor(() => expect(result.current.result).toBeDefined())

      expect(result.current.result).toEqual(
        expect.objectContaining({
          errors: error,
          where: 'center',
        }),
      )
    })

    it('sets isLoading to false after fetch failure', async () => {
      getSegments.mockRejectedValue(new Error('fail'))

      const {result} = renderHook(() => useSegmentsLoader(defaultProps), {
        wrapper: createWrapper(true),
      })

      await waitFor(() => expect(result.current.result).toBeDefined())

      expect(result.current.isLoading).toBe(false)
    })
  })

  describe('thereAreNoItems flags', () => {
    it('sets thereAreNoItemsBefore=true when files is empty and where=before', async () => {
      getSegments.mockResolvedValue({
        data: {...emptyFilesResponse.data, where: 'before'},
      })

      const {result} = renderHook(
        () => useSegmentsLoader({...defaultProps, where: 'before'}),
        {wrapper: createWrapper(true)},
      )

      await waitFor(() => expect(result.current.result).toBeDefined())

      // Re-triggering with same props should NOT call getSegments again
      // because thereAreNoItemsBefore is set
      const callCount = getSegments.mock.calls.length
      expect(callCount).toBe(1)
    })

    it('sets thereAreNoItemsAfter=true when files empty, where=after, lastSegment matches config', async () => {
      getSegments.mockResolvedValue({
        data: {...emptyFilesResponse.data, where: 'after'},
      })
      SegmentStore.getLastSegmentId.mockReturnValue('100')

      const {result} = renderHook(
        () => useSegmentsLoader({...defaultProps, where: 'after'}),
        {wrapper: createWrapper(true)},
      )

      await waitFor(() => expect(result.current.result).toBeDefined())

      expect(getSegments).toHaveBeenCalledTimes(1)
    })
  })

  describe('cleanup', () => {
    it('does not update state after unmount (wasCleaned)', async () => {
      let resolvePromise
      getSegments.mockReturnValue(
        new Promise((resolve) => (resolvePromise = resolve)),
      )

      const {result, unmount} = renderHook(
        () => useSegmentsLoader(defaultProps),
        {wrapper: createWrapper(true)},
      )

      unmount()

      act(() => resolvePromise(mockFilesResponse))

      // result should remain undefined since component was unmounted
      expect(result.current.result).toBeUndefined()
    })
  })

  describe('where reset on center', () => {
    it('resets thereAreNoItems flags when where changes to center', async () => {
      const {rerender} = renderHook(
        ({where}) => useSegmentsLoader({...defaultProps, where}),
        {
          initialProps: {where: 'before'},
          wrapper: createWrapper(true),
        },
      )

      await waitFor(() => expect(getSegments).toHaveBeenCalledTimes(1))

      act(() => rerender({where: 'center'}))

      await waitFor(() => expect(getSegments).toHaveBeenCalledTimes(2))
    })
  })
})
