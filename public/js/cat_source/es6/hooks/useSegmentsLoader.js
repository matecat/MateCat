import {useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {getSegments} from '../api/getSegments'
import SegmentActions from '../actions/SegmentActions'
import SegmentStore from '../stores/SegmentStore'

function useSegmentsLoader({
  segmentId,
  where = 'center',
  idJob = config.id_job,
  password = config.password,
  lastJobSegmentId = config.last_job_segment,
}) {
  const [isLoading, setIsLoading] = useState(false)
  const [result, setResult] = useState(undefined)

  const loadingInfo = useRef({
    isLoading: false,
    thereAreNoItemsBefore: false,
    thereAreNoItemsAfter: false,
  })

  useEffect(() => {
    const {current} = loadingInfo
    if (
      !segmentId ||
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
      step: where === 'center' ? 40 : UI.moreSegNum,
      segment: segmentId,
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
            SegmentStore.getLastSegmentId() === lastJobSegmentId &&
            where === 'after'
          )
            current.thereAreNoItemsAfter = true
        }
        setResult({data, segmentId, where: data.where})
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
    }
  }, [segmentId, where, idJob, password, lastJobSegmentId])

  return {isLoading, result}
}

useSegmentsLoader.propTypes = {
  segmentId: PropTypes.string.isRequired,
  where: PropTypes.string,
  idJob: PropTypes.string,
  password: PropTypes.string,
  lastJobSegmentId: PropTypes.string,
}

export default useSegmentsLoader
