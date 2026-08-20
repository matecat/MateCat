import PropTypes from 'prop-types'
import React from 'react'
import {extend} from './extensionPoints'

// One component per point, created once and cached. Building it during render
// would hand React a new component type on every pass and remount whatever the
// slot holds.
const components = new Map()

const componentFor = (name) => {
  if (!components.has(name)) {
    const SlotContent = (props) => extend(name)(props) ?? null
    SlotContent.displayName = `Slot(${name})`
    components.set(name, SlotContent)
  }
  return components.get(name)
}

// Marks a place in the tree that something outside core may fill. Whatever is
// registered renders as a component, so it can hold state, use hooks and fail
// inside an error boundary — none of which a method returning JSX can do.
export const Slot = ({name, ...props}) => {
  const SlotContent = componentFor(name)
  return <SlotContent {...props} />
}

Slot.propTypes = {
  name: PropTypes.string.isRequired,
}
