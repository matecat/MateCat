/**
 * Adapter that enables use of fetch instead of $.ajax
 * Temporary functionally, must be gradually replace $.ajax with fetch
 *
 * @param {Promise} promise
 * @returns {Object}
 */
export const createAjaxInterface = (promise) => {
  return {done: (fn) => promise.then(fn)}
}
