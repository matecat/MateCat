import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'
import {flattenObject} from '../../utils/queryString'

export const confirmSplitRequest = async (
  job,
  project,
  numsplit,
  arrayValues,
  splitRawWords,
) => {
  const params = flattenObject({
    project_id: project.id,
    project_pass: project.password,
    job_id: job.id,
    job_pass: job.password,
    num_split: numsplit,
    split_values: arrayValues,
    split_raw_words: splitRawWords,
  })
  const formData = new FormData()

  Object.keys(params).forEach((key) => {
    formData.append(key, params[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/split-job-apply`,
    {
      method: 'POST',
      credentials: 'include',
      body: formData,
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
