export const deleteGDriveUploadedFile = async ({
  fileId,
  segmentationRule,
  source,
  filtersTemplateId,
}) => {
  let url = `/gdrive/delete/${fileId}?segmentation_rule=${segmentationRule}&source=${source}&filters_template=${filtersTemplateId}`

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
