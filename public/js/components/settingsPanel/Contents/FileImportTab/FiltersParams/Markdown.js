import React from 'react'
import {Controller} from 'react-hook-form'
import Switch from '../../../../common/Switch'
import {TranslatableKeysField} from './TranslatableKeysField'
import {useTranslatableKeysSection} from './useTranslatableKeysSection'

const SEGMENTED_CONTROL_OPTIONS = [
  {id: 'translate_frontmatter_keys', name: 'Translatable'},
  {id: 'do_not_translate_frontmatter_keys', name: 'Non-translatable'},
]

export const Markdown = () => {
  const {control, formData} = useTranslatableKeysSection(
    'markdown',
    SEGMENTED_CONTROL_OPTIONS,
  )

  return (
    <div className="filters-params-accordion-content">
      <div className="filters-params-option">
        <div>
          <h3>Translate frontmatter</h3>
          <p>
            Choose whether to translate text contained in the frontmatter block
            at the beginning of the file.
          </p>
        </div>
        <Controller
          control={control}
          name="extract_frontmatter"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>

      <TranslatableKeysField
        control={control}
        formData={formData}
        options={SEGMENTED_CONTROL_OPTIONS}
        title="Translatable frontmatter keys"
        disabled={!formData?.extract_frontmatter}
        description={
          <>
            Choose which frontmatter keys should be translated. If left empty,
            all frontmatter keys will be extracted as translatable.
            <br />
            Key names are case sensitive.
            <br />
            If the toggle is set to &apos;Translatable&apos;, only the keys
            entered will be extracted as translatable.
            <br />
            If the toggle is set to &apos;Non-translatable&apos;, all
            frontmatter keys <b>except</b> those entered will be extracted as
            translatable.
          </>
        }
      />
    </div>
  )
}
