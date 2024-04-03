import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'
export const getQualityReportInfo = async () => {
  let url
  /**
   * ( 2023/11/06 )
   *
   * This is meant to allow back compatibility with running projects
   * after the advancement word-count switch from weighted to raw
   *
   * YYY [Remove] backward compatibility for current projects
   * YYY Remove after a reasonable amount of time
   */
  if (config.word_count_type === 'raw') {
    url = `${getMatecatApiDomain()}api/app/jobs/${config.id_job}/${
      config.password
    }`
  } else {
    url = `${getMatecatApiDomain()}api/v3/jobs/${config.id_job}/${
      config.password
    }`
  }

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
