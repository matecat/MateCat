/**
 * Request function
 * @param {String} url
 * @param {Object} params
 * @returns {Function}
 */
export default (url, params = {responseType: 'json'}) => {
  return async () => {
    const resp = await fetch(url, {...params})
    const json = await resp.json()
    return json
  }
}
