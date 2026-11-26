import CommonUtils from './commonUtils'

// Second argument of every createRoot. React's defaults only log (caught
// errors) or raise a global error (uncaught and recoverable ones), and neither
// carries the component stack.
//
// The error goes out through the track-error event, which the error tracker
// (when a plugin installs one) turns into a report with the component stack.
// The error itself is passed, not its message, so the report keeps its stack.
// window.reportError is deliberately not called: the tracker also listens to
// global errors and would report the same error a second time, without the
// component stack.
const handleRootError = (error, {componentStack}) => {
  CommonUtils.dispatchTrackingError(error, {componentStack})
  console.error(error, `\n\nThe above error occurred in:${componentStack}`)
}

export const rootErrorOptions = {
  onUncaughtError: handleRootError,
  onCaughtError: handleRootError,
  onRecoverableError: handleRootError,
}
