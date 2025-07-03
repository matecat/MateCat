export const changeGDriveSourceLang = async ({
  sourceLang,
  segmentation_rule,
  filters_extraction_parameters_template_id,
}) => {
  let url = `/gdrive/change?source=${sourceLang}&segmentation_rule=${segmentation_rule}&filters_extraction_parameters_template_id=${filters_extraction_parameters_template_id}`

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
