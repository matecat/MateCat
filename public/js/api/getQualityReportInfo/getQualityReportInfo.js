import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'
export const getQualityReportInfo = async () => {
  let url = `${getMatecatApiDomain()}api/app/jobs/${config.id_job}/${
    config.password
  }`

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
