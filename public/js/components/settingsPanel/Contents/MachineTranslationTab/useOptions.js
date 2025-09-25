import {useContext, useEffect, useRef, useState} from 'react'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {useForm} from 'react-hook-form'
import {isEqual} from 'lodash'

function useOptions() {
  const {modifyingCurrentTemplate, currentProjectTemplate} =
    useContext(SettingsPanelContext)

  const {control, watch, setValue} = useForm()

  const temporaryFormData = watch()
  const previousData = useRef()

  const [formData, setFormData] = useState()

  const mtExtra = useRef()
  mtExtra.current = currentProjectTemplate.mt?.extra

  useEffect(() => {
    if (!isEqual(temporaryFormData, previousData.current))
      setFormData(temporaryFormData)

    previousData.current = temporaryFormData
  }, [temporaryFormData])

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
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        mt: {
          ...prevTemplate.mt,
          extra: {
            ...prevTemplate.mt.extra,
            ...restPropsValue,
          },
        },
      }))
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
