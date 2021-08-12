import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'
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
    return Promise.reject(res)
  }

  const {errors, ...restData} = await res.json()

  if (errors) {
    return Promise.reject(errors)
  }

  return restData
}

const objToQueryString = (obj) => {
  const keyValuePairs = []
  for (const key in obj) {
    if (obj[key] && typeof obj[key] === 'object' && !Array.isArray(obj[key])) {
      for (const subKey in obj[key]) {
        if (obj[key][subKey]) {
          keyValuePairs.push(
            encodeURIComponent(`${key}[${subKey}]`) +
              '=' +
              encodeURIComponent(obj[key][subKey]),
          )
        }
      }
    } else if (obj[key]) {
      keyValuePairs.push(
        encodeURIComponent(key) + '=' + encodeURIComponent(obj[key]),
      )
    }
  }
  return keyValuePairs.join('&')
}
