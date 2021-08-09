import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const getChangeRates = async () => {
  const response = await fetch(
    `${getMatecatApiDomain()}?action=fetchChangeRates`,
    {
      method: 'POST',
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
