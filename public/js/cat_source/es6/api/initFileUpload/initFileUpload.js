export const initFileUpload = async () => {
  let url = `/lib/View/fileupload/`

  const res = await fetch(url, {
    credentials: 'include',
  })

  if (!res.ok) {
    return Promise.reject(res)
  }
  const response = await res.json()
  if (response?.errors) {
    return Promise.reject(response.errors)
  }

  return response
}
