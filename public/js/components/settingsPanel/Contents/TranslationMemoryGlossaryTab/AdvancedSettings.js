import React, {useContext} from 'react'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import Switch from '../../../common/Switch'
import {Select} from '../../../common/Select'
import {
  fromStatusLockId,
  PRETRANSLATE_ENABLED_DEFAULT,
  PRETRANSLATE_STATUS_OPTIONS,
  toStatusLockId,
} from '../../../../utils/editorStatusLock'

const PRETRANSLATE_MATCHES = [
  {key: 'match_101', label: 'Pre-confirm 101% matches'},
  {key: 'match_100', label: 'Pre-confirm 100% matches'},
]

const VARIANT_MATCHING_OPTIONS = [
  {dialectStrict: false, label: 'All variants'},
  {dialectStrict: true, label: 'Exact variant only'},
]

export const AdvancedSettings = () => {
  const {modifyingCurrentTemplate, currentProjectTemplate, projectTemplates} =
    useContext(SettingsPanelContext)

  const pretranslate = currentProjectTemplate.pretranslate
  // Switching off puts back the saved status, so on then off is not a change
  const getSavedPretranslateMatch = (key) =>
    projectTemplates?.find(
      ({isSelected, isTemporary}) => isSelected && !isTemporary,
    )?.pretranslate?.[key]
  const disablePretranslateMatch = (key) => {
    const saved = getSavedPretranslateMatch(key)
    setPretranslateMatch(key, {
      enabled: false,
      ...(saved && {status: saved.status, lock: saved.lock}),
    })
  }
  const setPretranslateMatch = (key, value) =>
    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      pretranslate: {
        ...prevTemplate.pretranslate,
        [key]: {...prevTemplate.pretranslate[key], ...value},
      },
    }))

  const isDialectStrictActive = currentProjectTemplate.dialectStrict
  const setIsDialectStrictActive = (value) =>
    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      dialectStrict: value,
    }))

  return (
    <div className="translation-memory-glossary-advanced">
      <section className="translation-memory-glossary-advanced-section">
        <div className="translation-memory-glossary-advanced-heading">
          <h2>Pre-confirmation</h2>
          <p>
            Choose if 100% and 101% matches should be pre-confirmed when
            creating a project.
            <br />
            By default, pre-confirmed segments are displayed as approved and
            locked. You can customize these settings using the options below to
            suit your project&apos;s needs.
          </p>
        </div>
        <div className="translation-memory-glossary-pretranslate-cards">
          {PRETRANSLATE_MATCHES.map(({key, label}) => (
            <div
              key={key}
              className="translation-memory-glossary-pretranslate-card"
              data-testid={`pretranslate-${key}`}
            >
              <div className="translation-memory-glossary-pretranslate-toggle">
                <Switch
                  active={pretranslate[key].enabled}
                  onChange={(enabled) =>
                    enabled
                      ? setPretranslateMatch(key, PRETRANSLATE_ENABLED_DEFAULT)
                      : disablePretranslateMatch(key)
                  }
                  testId={`pretranslate-${key}-switch`}
                  showText={false}
                />
                {label}
              </div>
              <div className="translation-memory-glossary-pretranslate-status">
                <span className="translation-memory-glossary-pretranslate-status-label">
                  State in editor
                </span>
                <Select
                  isPortalDropdown={true}
                  name={`pretranslate-${key}-status`}
                  options={PRETRANSLATE_STATUS_OPTIONS}
                  maxHeightDroplist={260}
                  activeOption={PRETRANSLATE_STATUS_OPTIONS.find(
                    ({id}) =>
                      id ===
                      toStatusLockId(
                        pretranslate[key].enabled
                          ? pretranslate[key]
                          : PRETRANSLATE_ENABLED_DEFAULT,
                      ),
                  )}
                  onSelect={(option) =>
                    setPretranslateMatch(key, fromStatusLockId(option.id))
                  }
                  isDisabled={!pretranslate[key].enabled}
                />
              </div>
            </div>
          ))}
        </div>
      </section>
      <section className="translation-memory-glossary-advanced-section">
        <div className="translation-memory-glossary-advanced-heading">
          <h2>Variant matching</h2>
          <p>
            Set the rule for retrieving matches from different variants of your
            project languages. Matches from other variants will be flagged in
            the editor and penalized by 1%.
          </p>
        </div>
        <div
          className="translation-memory-glossary-variant-matching"
          role="radiogroup"
          aria-label="Variant matching"
        >
          {VARIANT_MATCHING_OPTIONS.map(({dialectStrict, label}) => (
            <label key={label}>
              <input
                type="radio"
                name="variant-matching"
                checked={Boolean(isDialectStrictActive) === dialectStrict}
                onChange={() => setIsDialectStrictActive(dialectStrict)}
                data-testid={`variant-matching-${dialectStrict ? 'exact' : 'all'}`}
              />
              {label}
            </label>
          ))}
        </div>
      </section>
    </div>
  )
}
