import React, {useContext, useEffect} from 'react'
import Switch from '../../../../common/Switch'
import {FiltersParamsContext} from '../FiltersParams'
import {Controller, useForm} from 'react-hook-form'
import {isEqual} from 'lodash'

export const MsExcel = () => {
  const {currentTemplate, modifyingCurrentTemplate} =
    useContext(FiltersParamsContext)

  const {control, watch} = useForm()

  const {msExcel} = currentTemplate

  const propsValue = watch()

  useEffect(() => {
    if (!isEqual(msExcel, propsValue) && Object.keys(propsValue).length) {
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        msExcel: propsValue,
      }))
    }
  }, [propsValue, msExcel, modifyingCurrentTemplate])

  return (
    <div className="filters-params-accordion-content">
      <div className="filters-params-option">
        <div>
          <h3>Extract hidden cells</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
          </p>
        </div>
        <Controller
          control={control}
          defaultValue={msExcel.extract_hidden_cells}
          name="extract_hidden_cells"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>

      <div className="filters-params-option">
        <div>
          <h3>Extract drawing texts</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
          </p>
        </div>
        <Controller
          control={control}
          defaultValue={msExcel.extract_drawings}
          name="extract_drawings"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>

      <div className="filters-params-option">
        <div>
          <h3>Extract sheet names</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
          </p>
        </div>
        <Controller
          control={control}
          defaultValue={msExcel.extract_sheet_names}
          name="extract_sheet_names"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>

      <div className="filters-params-option">
        <div>
          <h3>Extract document properties</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
          </p>
        </div>
        <Controller
          control={control}
          defaultValue={msExcel.extract_doc_properties}
          name="extract_doc_properties"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>
    </div>
  )
}
