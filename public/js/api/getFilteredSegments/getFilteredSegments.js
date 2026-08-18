import {getQueryStringFromNestedProps} from '../../utils/queryString'

export const getFilteredSegments = async (idJob, password, filter) => {
  // No revision_number: the filter is applied to the phase the password in the URL resolves to.
  const params = getQueryStringFromNestedProps({
    filter: filter,
  })

  const response = await fetch(
    `/api/v2/jobs/${idJob}/${password}/segments-filter${params}`,
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
