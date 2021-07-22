export const promiseToAjax = (promise) => {
  return {done: (res) => promise.then(res)}
}
