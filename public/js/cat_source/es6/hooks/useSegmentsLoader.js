import {useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import {getSegments} from '../api/getSegments'
import SegmentActions from '../actions/SegmentActions'

function useSegmentsLoader({
  segmentToOpen,
  where = 'center',
  idJob = config.id_job,
  password = config.password,
}) {
  const [isLoading, setIsLoading] = useState(false)
  const [result, setResult] = useState(undefined)

  useEffect(() => {
    if (!segmentToOpen) return

    let wasCleaned = false
    getSegments({
      jid: idJob,
      password,
      step: where === 'center' ? 40 : UI.moreSegNum,
      segment: segmentToOpen,
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
        }
        setResult({data, segmentToOpen, where: data.where})
      })
      .catch((errors) => {
        if (wasCleaned) return
        setResult({errors, where})
      })
      .finally(() => setIsLoading(false))

    setIsLoading(true)

    return () => {
      wasCleaned = true
    }
  }, [segmentToOpen, where, idJob, password])

  return {isLoading, result}
}

useSegmentsLoader.propTypes = {
  segmentToOpen: PropTypes.string.isRequired,
  where: PropTypes.string,
  idJob: PropTypes.string,
  password: PropTypes.string,
}

export default useSegmentsLoader
