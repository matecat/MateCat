// Used in class component to extend methods over plugins

export class ComponentExtendInterface {
  get props() {
    return this
  }
  set props(_props = {}) {
    Object.entries(_props).forEach(([key, value]) => (this[key] = value))
  }
}
