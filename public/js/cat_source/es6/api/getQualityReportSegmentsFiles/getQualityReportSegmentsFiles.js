import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'
import {objToQueryString} from '../../utils/objToQueryString'
export const getQualityReportSegmentsFiles = async (filter, segmentId) => {
  let data = {
    ref_segment: segmentId,
  }
  if (filter) {
    data.filter = filter
  }
  data.revision_number = config.revisionNumber
  let url = `${getMatecatApiDomain()}api/app/jobs/${config.id_job}/${
    config.password
  }/quality-report/segments?${objToQueryString(data)}`

  const res = await fetch(url, {
    credentials: 'include',
  })

  if (!res.ok) {
    throw Error(res)
  }

  const {errors, ...restData} = await res.json()

  if (errors) {
    return Promise.reject(res)
  }

  return restData
}
