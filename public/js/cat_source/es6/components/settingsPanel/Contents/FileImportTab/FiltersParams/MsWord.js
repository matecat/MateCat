import React, {useContext, useEffect, useRef, useState} from 'react'
import Switch from '../../../../common/Switch'
import {WordsBadge} from '../../../../common/WordsBadge/WordsBadge'
import {FiltersParamsContext} from './FiltersParams'
import {Controller, useForm} from 'react-hook-form'
import {isEqual} from 'lodash'

export const MsWord = () => {
  const {currentTemplate, modifyingCurrentTemplate} =
    useContext(FiltersParamsContext)

  const {control, watch, setValue} = useForm()

  const [formData, setFormData] = useState()

  const msWord = useRef()
  msWord.current = currentTemplate.msWord

  const temporaryFormData = watch()
  const previousData = useRef()

  useEffect(() => {
    if (!isEqual(temporaryFormData, previousData.current))
      setFormData(temporaryFormData)

    previousData.current = temporaryFormData
  }, [temporaryFormData])

  useEffect(() => {
    if (typeof formData === 'undefined') return

    if (!isEqual(msWord.current, formData) && Object.keys(formData).length) {
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        msWord: formData,
      }))
    }
  }, [formData, modifyingCurrentTemplate, setValue])

  // set default values for current template
  useEffect(() => {
    Object.entries(msWord.current).forEach(([key, value]) =>
      setValue(key, value),
    )
  }, [currentTemplate.id, setValue])

  return (
    <div className="filters-params-accordion-content">
      <div className="filters-params-option">
        <div>
          <h3>Translate headers and footers</h3>
          <p>
            Choose whether to translate the headers and footers of the file.
          </p>
        </div>
        <Controller
          control={control}
          name="extract_headers_footers"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>

      <div className="filters-params-option">
        <div>
          <h3>Translate hidden text</h3>
          <p>Choose whether to translate hidden text.</p>
        </div>
        <Controller
          control={control}
          name="extract_hidden_text"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>

      <div className="filters-params-option">
        <div>
          <h3>Translate comments</h3>
          <p>
            Choose whether to translate the text in comments made in the
            document.
          </p>
        </div>
        <Controller
          control={control}
          name="extract_comments"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>

      <div className="filters-params-option">
        <div>
          <h3>Translate documents properties</h3>
          <p>
            Choose whether to translate document properties (e.g. the author's
            name).
          </p>
        </div>
        <Controller
          control={control}
          name="extract_doc_properties"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>

      <div className="filters-params-option">
        <div>
          <h3>Automatically accept revisions</h3>
          <p>
            Choose whether to automatically accept revisions upon upload of the
            file.
            <br />
            If the document contains revisions and this option is set to
            inactive, an error will be shown.
          </p>
        </div>
        <Controller
          control={control}
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
            Enter the names of styles applied to text that should not be
            translated.
            <br />
            Style names are case sensitive, for styles whose names are comprised
            of multiple words, remove the whitespaces: if a style's name is
            "test Style", the relevant parameter value will be testStyle.
          </p>
        </div>
        <Controller
          control={control}
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

      <div className="filters-params-option">
        <div>
          <h3>Exclude highlight colors</h3>
          <p>
            Enter the name of highlighting colors applied to text that should
            not be translated.
            <br />
            Color names are case sensitive. Common color names are available{' '}
            <a
              href="https://guides.matecat.com/file-import#:~:text=MS%20Word%202007,97%2D2003%20(DOC)"
              target="_blank"
            >
              here
            </a>
            .
          </p>
        </div>
        <Controller
          control={control}
          name="exclude_highlight_colors"
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
