import {useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {getSegments} from '../api/getSegments'
import SegmentActions from '../actions/SegmentActions'
import SegmentStore from '../stores/SegmentStore'
import CommonUtils from '../utils/commonUtils'

const INIT_NUM_SEGMENTS = 40
const MORE_NUM_SEGMENTS = 25

function useSegmentsLoader({
  segmentId,
  where = 'center',
  idJob = config.id_job,
  password = config.password,
}) {
  const [isLoading, setIsLoading] = useState(false)
  const [result, setResult] = useState(undefined)

  const loadingInfo = useRef({
    isLoading: false,
    thereAreNoItemsBefore: false,
    thereAreNoItemsAfter: false,
  })

  useEffect(() => {
    if (where === 'center') {
      const {current} = loadingInfo
      current.thereAreNoItemsBefore = false
      current.thereAreNoItemsAfter = false
    }
  }, [where])

  useEffect(() => {
    const {current} = loadingInfo
    const segmentIdValue =
      typeof segmentId === 'symbol' ? segmentId.description : segmentId
    if (
      !segmentIdValue ||
      current.isLoading ||
      (where === 'before' && current.thereAreNoItemsBefore) ||
      (where === 'after' && current.thereAreNoItemsAfter)
    )
      return

    if (where !== 'center') console.log('Get more segments', where)

    let wasCleaned = false

    getSegments({
      jid: idJob,
      password,
      step: where === 'center' ? INIT_NUM_SEGMENTS : MORE_NUM_SEGMENTS,
      segment: segmentIdValue,
      where,
    })
      .then(({data}) => {
        if (wasCleaned) return
        // Dispatch action addSegments
        if (typeof data.files !== 'undefined') {
          const segments = Object.entries(data.files)
            .map(([, value]) => value.segments)
            .flat()
          SegmentActions.addSegments(segments, where)

          const isFilesObjectEmpty = Object.keys(data.files).length === 0
          if (isFilesObjectEmpty && where === 'before')
            current.thereAreNoItemsBefore = true
          if (
            isFilesObjectEmpty &&
            SegmentStore.getLastSegmentId() === config.last_job_segment &&
            where === 'after'
          )
            current.thereAreNoItemsAfter = true
        }
        setResult({data, segmentId: segmentIdValue, where: data.where})
      })
      .catch((errors) => {
        if (wasCleaned) return
        setResult({errors, where})
      })
      .finally(() => {
        setIsLoading(false)
        current.isLoading = false
      })

    setIsLoading(true)
    current.isLoading = true

    return () => {
      wasCleaned = true
      current.isLoading = false
    }
  }, [segmentId, where, idJob, password])

  return {isLoading, result}
}

useSegmentsLoader.propTypes = {
  segmentId: PropTypes.oneOfType([PropTypes.symbol, PropTypes.string]),
  where: PropTypes.string,
  idJob: PropTypes.string,
  password: PropTypes.string,
}

export default useSegmentsLoader
