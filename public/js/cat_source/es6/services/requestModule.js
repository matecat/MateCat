/**
 * Request function
 * @param {String} url
 * @param {Object} params
 * @param {String} responseType
 * @returns {Function}
 */
export default (url, params = {}, responseType = 'json') => {
  return async () => {
    try {
      const resp = await fetch(url, {...params, responseType})
      const json = await resp[responseType]()
      return json
    } catch (error) {
      return Promise.reject({error: error})
    }
  }
}
