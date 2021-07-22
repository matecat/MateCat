export const createAjaxInterface = (promise) => {
  return {done: (fn) => promise.then(fn)}
}
