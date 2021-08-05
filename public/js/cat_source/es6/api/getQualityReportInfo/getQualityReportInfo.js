import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'
export const getQualityReportInfo = async () => {
  let url = `${getMatecatApiDomain()}api/v3/jobs/${config.id_job}/${
    config.password
  }`

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
