export const openGDriveFiles = async ({
  stateJson,
  sourceLang,
  targetLang,
  segmentation_rule,
  filters_extraction_parameters_template_id,
  filters_extraction_parameters_template,
}) => {
  let url = `/webhooks/gdrive/open`

  const dataParams = {
    isAsync: true,
    filters_extraction_parameters_template,
    state: stateJson,
    source: sourceLang,
    target: targetLang,
    segmentation_rule: segmentation_rule,
    filters_extraction_parameters_template_id,
  }
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    if (dataParams[key] !== undefined) formData.append(key, dataParams[key])
  })

  const res = await fetch(url, {
    method: 'POST',
    credentials: 'include',
    body: formData,
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
