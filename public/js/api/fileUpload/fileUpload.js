/**
 * Upload a file
 * @param file
 * @param onProgress
 * @param onSuccess
 * @param onError
 */
export const fileUpload = (file, onProgress, onSuccess, onError) => {
  const xhr = new XMLHttpRequest()
  const formData = new FormData()
  formData.append('files', file)

  xhr.upload.onprogress = (event) => {
    if (event.lengthComputable) {
      const progress = (event.loaded / event.total) * 100
      onProgress(progress)
    }
  }

  xhr.onload = () => {
    if (xhr.status === 200) {
      onSuccess(xhr.response)
    } else {
      onError(xhr.statusText)
    }
  }

  xhr.onerror = () => {
    onError('Connection error')
  }

  xhr.open('POST', '/fileupload/', true)
  xhr.send(formData)
}
