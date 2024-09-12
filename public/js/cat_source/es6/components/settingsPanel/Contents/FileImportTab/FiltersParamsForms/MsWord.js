import React, {useContext, useEffect} from 'react'
import Switch from '../../../../common/Switch'
import {WordsBadge} from '../../../../common/WordsBadge/WordsBadge'
import {FiltersParamsContext} from '../FiltersParams'
import {Controller, useForm} from 'react-hook-form'
import {isEqual} from 'lodash'

export const MsWord = () => {
  const {currentTemplate, modifyingCurrentTemplate} =
    useContext(FiltersParamsContext)

  const {control, watch} = useForm()

  const {msWord} = currentTemplate

  const propsValue = watch()

  useEffect(() => {
    if (!isEqual(msWord, propsValue) && Object.keys(propsValue).length) {
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        msWord: propsValue,
      }))
    }
  }, [propsValue, msWord, modifyingCurrentTemplate])

  return (
    <div className="filters-params-accordion-content">
      <div className="filters-params-option">
        <div>
          <h3>Extract headers and footers</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
          </p>
        </div>
        <Controller
          control={control}
          defaultValue={msWord.extract_headers_footers}
          name="extract_headers_footers"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>

      <div className="filters-params-option">
        <div>
          <h3>Extract hidden text</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
          </p>
        </div>
        <Controller
          control={control}
          defaultValue={msWord.extract_hidden_text}
          name="extract_hidden_text"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>

      <div className="filters-params-option">
        <div>
          <h3>Extract comments</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
          </p>
        </div>
        <Controller
          control={control}
          defaultValue={msWord.extract_comments}
          name="extract_comments"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>

      <div className="filters-params-option">
        <div>
          <h3>Extract documents properties</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
          </p>
        </div>
        <Controller
          control={control}
          defaultValue={msWord.extract_doc_properties}
          name="extract_doc_properties"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>

      <div className="filters-params-option">
        <div>
          <h3>Accept revisions</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
          </p>
        </div>
        <Controller
          control={control}
          defaultValue={msWord.accept_revisions}
          name="accept_revisions"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>

      <div className="filters-params-option">
        <div>
          <h3>Exclude styles</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
          </p>
        </div>
        <Controller
          control={control}
          defaultValue={msWord.exclude_styles}
          name="exclude_styles"
          render={({field: {onChange, value, name}}) => (
            <WordsBadge
              name={name}
              value={value}
              onChange={onChange}
              placeholder={''}
            />
          )}
        />
      </div>
    </div>
  )
}
