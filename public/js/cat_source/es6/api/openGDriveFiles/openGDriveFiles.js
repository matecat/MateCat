export const openGDriveFiles = async (encodedJson, sourceLang, targetLang) => {
  let url = `/webhooks/gdrive/open?isAsync=true&state=${encodedJson}&source=${sourceLang}&target=${targetLang}`

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
