export const getGoogleDriveUploadedFiles = async () => {
  let url = `/gdrive/list`

  const res = await fetch(url, {
    credentials: 'include',
  })

  if (!res.ok) {
    const error = await res.json()
    return Promise.reject(error)
  }

  const {errors, ...restData} = await res.json()

  if (errors) {
    return Promise.reject(errors)
  }

  return restData
}
