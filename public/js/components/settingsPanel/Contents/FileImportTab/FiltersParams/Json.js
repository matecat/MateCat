import React from 'react'
import Switch from '../../../../common/Switch'
import {KeysBadge} from '../../../../common/KeysBadge/KeysBadge'
import {Controller} from 'react-hook-form'
import {TranslatableKeysField} from './TranslatableKeysField'
import {useTranslatableKeysSection} from './useTranslatableKeysSection'
import {Select} from '../../../../common/Select'

const SEGMENTED_CONTROL_OPTIONS = [
  {id: 'translate_keys', name: 'Translatable'},
  {id: 'do_not_translate_keys', name: 'Non-translatable'},
]

const INNER_CONTENT_TYPE_OPTIONS = [{id: 'text/html', name: 'HTML'}]

export const Json = () => {
  const {control, formData} = useTranslatableKeysSection(
    'json',
    SEGMENTED_CONTROL_OPTIONS,
  )

  return (
    <div className="filters-params-accordion-content">
      <div className="filters-params-option">
        <div>
          <h3>Translate arrays</h3>
          <p>Choose whether to translate text contained within arrays.</p>
        </div>
        <Controller
          control={control}
          name="extract_arrays"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>

      <div className="filters-params-option">
        <div>
          <h3>Escape forward slashes</h3>
          <p>
            Choose whether to escape forward slashes in the translated file
            (i.e. \/ in place of /).
          </p>
        </div>
        <Controller
          control={control}
          name="escape_forward_slashes"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>

      <TranslatableKeysField
        control={control}
        formData={formData}
        options={SEGMENTED_CONTROL_OPTIONS}
        title="Translatable keys"
        description={
          <>
            Choose which keys should be translated. If left empty, all keys in
            the file will be extracted as translatable.
            <br />
            Key names are case sensitive.
            <br />
            If the toggle is set to &quot;Translatable&quot;, only the keys
            entered will be extracted as translatable.
            <br />
            If the toggle is set to &quot;Non-translatable&quot;, all the keys
            in the file <b>except</b> those entered will be extracted as
            translatable.
          </>
        }
      />

      <div className="filters-params-option">
        <div>
          <h3>Context keys</h3>
          <p>
            Choose which keys should be extracted as context for translatable
            keys.
            <br />
            Key names are case sensitive.
            <br />
            The extracted notes will be applied to translatable keys in the same
            object scope.
          </p>
        </div>
        <Controller
          control={control}
          name="context_keys"
          render={({field: {onChange, value, name}}) => (
            <KeysBadge
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
          <h3>Character limit keys</h3>
          <p>
            Choose which keys should be extracted as the character limit for
            translatable keys.
            <br />
            Key names are case sensitive.
            <br />
            Character limits will be applied to translatable keys in the same
            object.
            <br />
            Keys with a character limit won&apos;t be segmented.
          </p>
        </div>
        <Controller
          control={control}
          name="character_limit"
          render={({field: {onChange, value, name}}) => (
            <KeysBadge
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
          <h3>Translatable text content type</h3>
          <p>
            Select the content type of the translatable text to optimize
            segmentation and tags.
          </p>
        </div>
        <Controller
          control={control}
          name="inner_content_type"
          render={({field: {onChange, value, name}}) => (
            <Select
              name={name}
              placeholder="Select inner content type"
              isPortalDropdown={true}
              options={INNER_CONTENT_TYPE_OPTIONS}
              activeOption={INNER_CONTENT_TYPE_OPTIONS?.find(
                ({id}) => id === value,
              )}
              onSelect={(option) => onChange(option.id)}
              maxHeightDroplist={260}
            />
          )}
        />
      </div>
    </div>
  )
}
