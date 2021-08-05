import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'
export const getUserData = async () => {
  let url = `${getMatecatApiDomain()}api/app/user`

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
