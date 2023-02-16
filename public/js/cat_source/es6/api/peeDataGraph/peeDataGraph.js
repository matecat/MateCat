import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'
import {flattenObject} from '../../utils/queryString'

/**
 * PEE get data graph
 *
 * @param {Object} options
 * @param {string} options.sources
 * @param {string} options.targets
 * @param {Array} options.monthInterval
 * @param {string} options.fuzzyBand
 * @returns {Promise<object>}
 */
export const peeDataGraph = async ({
  sources,
  targets,
  monthInterval,
  fuzzyBand,
}) => {
  const dataParams = flattenObject({
    sources,
    targets,
    month_interval: monthInterval,
    fuzzy_band: fuzzyBand,
  })
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    if (dataParams[key] !== undefined) formData.append(key, dataParams[key])
  })

  const response = await fetch(`/api/app/utils/pee/graph`, {
    method: 'POST',
    credentials: 'include',
    body: formData,
  })

  if (!response.ok) {
    if (response.headers.get('Content-Length') !== '0') {
      const data = await response.json()
      return Promise.reject({response, errors: data.errors ?? data})
    } else {
      return Promise.reject({response})
    }
  }

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject({response, errors})
  return data
}
