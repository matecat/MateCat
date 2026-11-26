import React from 'react'

// Shows `fallback` in place of children that threw while rendering. The error
// is reported by the root's onCaughtError (rootErrorOptions), so the boundary
// only switches what is rendered.
// Error boundaries can only be class components
// eslint-disable-next-line no-restricted-syntax
export class ErrorBoundary extends React.Component {
  state = {failed: false}

  static getDerivedStateFromError() {
    return {failed: true}
  }

  render() {
    return this.state.failed ? this.props.fallback : this.props.children
  }
}
