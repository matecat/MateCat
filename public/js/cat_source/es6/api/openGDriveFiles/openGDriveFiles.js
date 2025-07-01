export const openGDriveFiles = async ({
  encodedJson,
  sourceLang,
  targetLang,
  segmentation_rule,
  filters_extraction_parameters_template_id,
  filters_extraction_parameters_template,
}) => {
  let url = `/webhooks/gdrive/open?isAsync=true&state=${encodedJson}&source=${sourceLang}&target=${targetLang}&segmentation_rule=${segmentation_rule}&filters_extraction_parameters_template_id=${filters_extraction_parameters_template_id}`

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
