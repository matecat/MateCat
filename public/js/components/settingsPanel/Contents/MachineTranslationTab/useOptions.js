import {useContext, useEffect, useMemo, useRef, useState} from 'react'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {useForm} from 'react-hook-form'
import {isEqual} from 'lodash'

function useOptions(excludedFields = []) {
  const {modifyingCurrentTemplate, currentProjectTemplate} =
    useContext(SettingsPanelContext)

  const {control, watch, setValue} = useForm()

  const valuesFormData = watch()
  const previousData = useRef()

  const filteredFormData = useMemo(() => {
    return Object.fromEntries(
      Object.entries(valuesFormData).filter(
        ([key]) => !excludedFields.includes(key),
      ),
    )
  }, [valuesFormData, excludedFields])

  const [formData, setFormData] = useState()

  const mtExtra = useRef()
  mtExtra.current = currentProjectTemplate.mt?.extra

  useEffect(() => {
    if (!isEqual(filteredFormData, previousData.current))
      setFormData(filteredFormData)

    previousData.current = filteredFormData
  }, [filteredFormData])

  useEffect(() => {
    if (typeof formData === 'undefined') return

    const restPropsValue = Object.entries(formData).reduce(
      (acc, [key, value]) => ({
        ...acc,
        ...{[key]: value},
      }),
      {},
    )

    if (
      !isEqual(mtExtra.current, restPropsValue) &&
      Object.keys(restPropsValue).length
    ) {
      modifyingCurrentTemplate((prevTemplate) => {
        const extraWithoutUndefinedValue = Object.entries(
          prevTemplate.mt.extra,
        ).reduce((acc, [key, value]) => {
          return {
            ...acc,
            ...(typeof value !== 'undefined' ? {[key]: value} : {}),
          }
        }, {})

        return {
          ...prevTemplate,
          mt: {
            ...prevTemplate.mt,
            extra: {
              ...extraWithoutUndefinedValue,
              ...restPropsValue,
            },
          },
        }
      })
    }
  }, [formData, modifyingCurrentTemplate, setValue])

  // set default values
  useEffect(() => {
    if (typeof mtExtra.current !== 'undefined')
      Object.entries(mtExtra.current).forEach(([key, value]) =>
        setValue(key, value),
      )
  }, [currentProjectTemplate.mt?.id, setValue])

  return {control, setValue, watch}
}

export default useOptions
