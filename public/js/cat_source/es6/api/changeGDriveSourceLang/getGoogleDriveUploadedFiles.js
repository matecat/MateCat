export const changeGDriveSourceLang = async (sourceLang) => {
  let url = `/gdrive/change/${sourceLang}`

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
