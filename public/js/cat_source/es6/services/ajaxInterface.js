export const promiseToAjax = (promise) => {
  return {done: (fn) => promise.then(fn)}
}
