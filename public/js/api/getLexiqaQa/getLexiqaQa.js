import md5 from 'crypto-js/md5'
/**
 * Return lexiqa warnings
 *
 * @param {Object} options
 * @param {string} options.partnerId
 * @param {string} [options.lexiqaDomain=config.lexiqaServer]
 * @param {string} [options.idJob=config.id_job]
 * @param {string} [options.password=config.password]
 * @returns {Promise<object>}
 */
export const getLexiqaQa = async ({params, settings}) => {
  const dataParams = {
    sourcelanguage: params.sourcelanguage,
    targetlanguage: params.targetlanguage,
    sourcetext: params.sourcetext,
    targettext: params.targettext,
    returnUrl: location.href,
    segmentId: params.segmentId,
    baseUrl: location.host,
    partnerId: settings.partnerId,
    projectId: settings.projectId,
    isSegmentCompleted: params.isSegmentCompleted,
    responseMode: 'includeQAResults',
    token: md5(
      settings.licenseKey +
        '|' +
        location.host +
        '|' +
        settings.projectId +
        '|' +
        params.segmentId +
        '|' +
        settings.partnerId,
    ),
  }

  const searchParams = new URLSearchParams()
  Object.keys(dataParams).forEach((key) => {
    searchParams.append(`qaData[${key}]`, dataParams[key])
  })
  const response = await fetch(`${config.lexiqaServer}/qasegment`, {
    method: 'POST',
    body: searchParams,
  })

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
