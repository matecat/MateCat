/**
 * Convert file before analysis
 *
 * @param {string} action
 * @param {string} file_name
 * @param {string} source_lang
 * @param {string} target_lang
 * @param {string} segmentation_rule
 * @param filters_extraction_parameters_template_id
 * @param {AbortController} signal
 * @param restartedConversion
 * @returns {Promise<object>}
 */
export const convertFileRequest = async ({
  action,
  file_name,
  source_lang,
  target_lang,
  segmentation_rule,
  filters_extraction_parameters_template_id,
  signal,
  restarted_conversion,
}) => {
  const dataParams = {
    action,
    file_name,
    source_lang,
    target_lang,
    segmentation_rule,
    filters_extraction_parameters_template_id,
    restarted_conversion,
  }
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    if (dataParams[key] !== undefined) formData.append(key, dataParams[key])
  })

  const response = await fetch(`api/app/convert-file`, {
    method: 'POST',
    credentials: 'include',
    signal: signal,
    body: formData,
  })

  if (!response.ok) {
    if (response.headers.get('Content-Length') !== '0') {
      const data = await response.json()
      return Promise.reject({response, errors: data.errors ?? data})
    } else {
      return Promise.reject({response})
    }
  }

  const {errors, ...data} = await response.json()
  if (!data.code && errors && errors.length > 0)
    return Promise.reject({response, errors})
  return {data, errors}
}
