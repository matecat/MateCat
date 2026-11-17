import React from 'react'
import PropTypes from 'prop-types'
import {Controller} from 'react-hook-form'
import {SegmentedControl} from '../../../../common/SegmentedControl'
import {KeysBadge} from '../../../../common/KeysBadge/KeysBadge'

/**
 * The Translatable / Non-translatable keys option shared by the JSON, YAML and
 * Markdown sections. Its state lives in useTranslatableKeysSection: pass the
 * `control` and `formData` that hook returns, and the same options.
 */
export const TranslatableKeysField = ({
  control,
  formData,
  options,
  title,
  description,
  disabled = false,
}) => {
  const {segmentedControl} = formData ?? {}

  return (
    <div className="filters-params-option">
      <div>
        <h3>{title}</h3>
        <p>{description}</p>
      </div>
      <div className="container-segmented-control">
        <Controller
          control={control}
          name="segmentedControl"
          render={({field: {onChange, value, name}}) => (
            <SegmentedControl
              name={name}
              className="custom-segmented-control"
              options={options}
              selectedId={value}
              onChange={onChange}
              disabled={disabled}
            />
          )}
        />
        {options
          .filter(({id}) => id === segmentedControl)
          .map(({id}) => (
            <Controller
              key={id}
              control={control}
              name={id}
              render={({field: {onChange, value, name}}) => (
                <KeysBadge
                  name={name}
                  value={value}
                  onChange={onChange}
                  placeholder={''}
                  disabled={disabled}
                />
              )}
            />
          ))}
      </div>
    </div>
  )
}

TranslatableKeysField.propTypes = {
  control: PropTypes.object.isRequired,
  formData: PropTypes.object,
  options: PropTypes.arrayOf(
    PropTypes.shape({
      id: PropTypes.string.isRequired,
      name: PropTypes.string.isRequired,
    }),
  ).isRequired,
  title: PropTypes.string.isRequired,
  description: PropTypes.node.isRequired,
  disabled: PropTypes.bool,
}
