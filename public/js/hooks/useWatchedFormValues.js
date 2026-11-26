import {useEffect, useRef, useState} from 'react'
import {isEqual} from 'lodash'

// Keeps the values of a react-hook-form form in state, updated only when they
// really change, so an effect can compare them with the stored template.
// Until the form receives its first value, watch() returns an empty object.
// That snapshot is skipped: compared with the template it would report every
// field as changed to undefined, marking the template modified on mount.
export const useWatchedFormValues = (watch) => {
  const values = watch()
  const [formValues, setFormValues] = useState()
  const previousValues = useRef()

  useEffect(() => {
    if (!Object.keys(values).length) return
    if (!isEqual(values, previousValues.current)) setFormValues(values)

    previousValues.current = values
  }, [values])

  return formValues
}
