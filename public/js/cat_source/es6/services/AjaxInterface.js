export default class AjaxInterface {
  #_fn = () => {}
  action = (...args) => this.#_fn?.(...args)
  callback = (fn) => (this.#_fn = fn)
}
