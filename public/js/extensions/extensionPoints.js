// A named registry for the points where a deployment may replace core
// behaviour.
//
// Core declares a point with a default implementation; something outside core
// replaces it by name. Two properties are the whole reason this exists:
// registering against a name core does not define throws immediately instead of
// failing silently, and implementations are plain functions of their arguments
// rather than methods that depend on `this`.
//
// Point names are capability names drawn from the domain. Nothing here records
// who registers what — that mapping is deliberately not knowable from core.

import {listCapabilities} from './capabilities'

const defaults = new Map()
const overrides = new Map()

export const defineExtensionPoint = (name, defaultImpl) => {
  if (defaults.has(name)) {
    throw new Error(`Extension point already defined: ${name}`)
  }
  if (typeof defaultImpl !== 'function') {
    throw new Error(`Extension point ${name} needs a default implementation`)
  }
  defaults.set(name, defaultImpl)
}

export const registerExtension = (name, impl) => {
  if (!defaults.has(name)) {
    throw new Error(`Unknown extension point: ${name}`)
  }
  if (typeof impl !== 'function') {
    throw new Error(`Extension for ${name} must be a function`)
  }
  overrides.set(name, impl)
}

// A slot is a point whose implementation is a React component, so core can hand
// over a position in the tree instead of splicing a method's return value into
// its own render. Rendering nothing is the only sensible core default.
export const defineSlot = (name) => {
  defineExtensionPoint(name, () => null)
}

export const registerSlot = registerExtension

// What core would have done. An extension that means to add to core behaviour
// rather than replace it calls this instead of reaching for the module that
// happens to hold the default.
export const getExtensionDefault = (name) => {
  if (!defaults.has(name)) {
    throw new Error(`Unknown extension point: ${name}`)
  }
  return defaults.get(name)
}

// Resolved per call, not when the accessor is built, so core can capture an
// accessor at import time and still see an extension registered later at boot.
export const extend =
  (name) =>
  (...args) =>
    (overrides.get(name) ?? getExtensionDefault(name))(...args)

export const listExtensionPoints = () =>
  [...defaults.keys()]
    .sort()
    .map((name) => ({name, overridden: overrides.has(name)}))

// Drops registered extensions, keeping the defined points. For tests that
// register an extension and must not leak it into the next one.
export const resetExtensionOverrides = () => {
  overrides.clear()
}

// Dev-only. Prints which declared points are running an extension and which are
// running core's default, so a point that silently stopped being wired up is
// visible at boot. Names only.
export const logExtensionPointStatus = () => {
  if (process.env.NODE_ENV !== 'development') return

  const points = listExtensionPoints()
  const names = (list) => (list.length ? list.join(', ') : 'none')

  // eslint-disable-next-line no-console
  console.info(
    `[extensions] ${points.length} points defined | extended: ${names(
      points.filter(({overridden}) => overridden).map(({name}) => name),
    )} | core default: ${names(
      points.filter(({overridden}) => !overridden).map(({name}) => name),
    )}`,
  )

  // eslint-disable-next-line no-console
  console.info(
    `[extensions] withdrawn capabilities: ${names(
      listCapabilities()
        .filter(({allowed}) => !allowed)
        .map(({name}) => name),
    )}`,
  )
}
