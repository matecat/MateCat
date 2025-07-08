import {getQueryStringFromNestedProps} from '../../utils/queryString'

export const getFilteredSegments = async (
  idJob,
  password,
  filter,
  revisionNumber,
) => {
  const params = getQueryStringFromNestedProps({
    filter: filter,
    revision_number: revisionNumber,
  })

  const response = await fetch(
    `/api/v2/jobs/${idJob}/${password}/segments-filter${params}`,
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
