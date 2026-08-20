// Permissions, not behaviour.
//
// A capability answers "is this deployment allowed to do X", which is a
// different question from "who implements X". Modelling a permission as an
// extension point produces a no-oped function: the UI still offers the action,
// the click still fires, and nothing happens. A boolean lets the UI not offer it
// at all.
//
// Capabilities default to permitted, so a deployment that says nothing gets the
// full product.

const defaults = new Map()
const permitted = new Map()

export const defineCapability = (name, defaultValue = true) => {
  if (defaults.has(name)) {
    throw new Error(`Capability already defined: ${name}`)
  }
  if (typeof defaultValue !== 'boolean') {
    throw new Error(`Capability ${name} needs a boolean default`)
  }
  defaults.set(name, defaultValue)
  permitted.set(name, defaultValue)
}

export const setCapability = (name, value) => {
  if (!defaults.has(name)) {
    throw new Error(`Unknown capability: ${name}`)
  }
  if (typeof value !== 'boolean') {
    throw new Error(`Capability ${name} must be set to a boolean`)
  }
  permitted.set(name, value)
}

export const can = (name) => {
  if (!defaults.has(name)) {
    throw new Error(`Unknown capability: ${name}`)
  }
  return permitted.get(name)
}

export const listCapabilities = () =>
  [...permitted.entries()]
    .sort(([a], [b]) => (a < b ? -1 : 1))
    .map(([name, allowed]) => ({name, allowed}))

// Restores every capability to what core declared. For tests that withdraw one
// and must not leak that into the next.
export const resetCapabilities = () => {
  defaults.forEach((value, name) => permitted.set(name, value))
}
