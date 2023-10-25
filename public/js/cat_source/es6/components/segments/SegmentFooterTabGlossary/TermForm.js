import React, {useCallback, useContext, useEffect, useState} from 'react'
import {MoreIcon, TERM_FORM_FIELDS} from './SegmentFooterTabGlossary'
import {TabGlossaryContext} from './TabGlossaryContext'
import SegmentActions from '../../../actions/SegmentActions'
import CatToolActions from '../../../actions/CatToolActions'
import {KeysSelect} from './KeysSelect'
import {DomainSelect} from './DomainSelect'
import {SubdomainSelect} from './SubdomainSelect'

const TermForm = () => {
  const {
    isLoading,
    termForm,
    setTermForm,
    selectsActive,
    modifyElement,
    showMore,
    setShowMore,
    resetForm,
    domainsResponse,
    getRequestPayloadTemplate,
    setIsLoading,
    segment,
    ref,
  } = useContext(TabGlossaryContext)

  const [highlightMandatoryOnSubmit, setHighlightMandatoryOnSubmit] = useState(
    {},
  )

  const onSubmitAddOrUpdateTerm = useCallback(() => {
    const {ORIGINAL_TERM, TRANSLATED_TERM} = TERM_FORM_FIELDS

    // check mandatory fields
    const {[ORIGINAL_TERM]: originalTerm, [TRANSLATED_TERM]: translatedTerm} =
      termForm
    const {keys, domain, subdomain} = selectsActive
    setHighlightMandatoryOnSubmit({
      originalTerm: !originalTerm,
      translatedTerm: !translatedTerm,
      keys: !keys.length,
    })
    if (!originalTerm || !translatedTerm || !keys.length) return

    setIsLoading(true)
    if (modifyElement) {
      SegmentActions.updateGlossaryItem(getRequestPayloadTemplate())
    } else {
      SegmentActions.addGlossaryItem(getRequestPayloadTemplate())
      CatToolActions.setHaveKeysGlossary(true)

      const updatedDomains = domain
        ? keys.reduce(
            (acc, {key}) => {
              const aggregator = [
                ...(acc[key]?.length ? acc[key] : []),
                ...(!acc[key]?.length ||
                !acc[key]?.find((item) => item.domain === domain.name)
                  ? [{domain: domain.name, subdomains: []}]
                  : []),
              ]
              return {
                ...acc,
                [key]: aggregator.map((item) =>
                  item.domain === domain.name
                    ? {
                        domain: domain.name,
                        subdomains: [
                          ...new Set([
                            ...item.subdomains,
                            ...(subdomain?.name ? [subdomain.name] : []),
                          ]),
                        ],
                      }
                    : item,
                ),
              }
            },
            {...domainsResponse},
          )
        : []

      // dispatch actions set domains
      CatToolActions.setDomains({
        sid: segment.sid,
        entries: {...domainsResponse, ...updatedDomains},
      })
    }
  }, [
    domainsResponse,
    getRequestPayloadTemplate,
    modifyElement,
    segment.sid,
    selectsActive,
    setIsLoading,
    termForm,
  ])

  useEffect(() => {
    if (!ref) return
    const {current} = ref

    const onKeyUp = (e) => {
      if (e.key === 'Escape') {
        resetForm()
      } else if (e.ctrlKey && e.key === 'Enter') {
        if (!isLoading) onSubmitAddOrUpdateTerm()
      } else {
        return
      }

      e.stopPropagation()
    }

    current.addEventListener('keydown', onKeyUp)

    return () => current.removeEventListener('keydown', onKeyUp)
  }, [isLoading, onSubmitAddOrUpdateTerm, ref, resetForm])

  useEffect(() => {
    setHighlightMandatoryOnSubmit({})
  }, [
    termForm?.[TERM_FORM_FIELDS.ORIGINAL_TERM],
    termForm?.[TERM_FORM_FIELDS.TRANSLATED_TERM],
  ])

  const {
    DEFINITION,
    ORIGINAL_TERM,
    ORIGINAL_DESCRIPTION,
    ORIGINAL_EXAMPLE,
    TRANSLATED_TERM,
    TRANSLATED_DESCRIPTION,
    TRANSLATED_EXAMPLE,
  } = TERM_FORM_FIELDS

  const updateTermForm = (key, value) =>
    setTermForm((prevState) => ({...prevState, [key]: value}))

  return (
    <div className="glossary_add-container">
      <div className="glossary-form-line">
        <div className="input-with-label__wrapper">
          <label>Definition</label>
          <input
            name="glossary-term-definition"
            value={termForm[DEFINITION]}
            onChange={(event) => updateTermForm(DEFINITION, event.target.value)}
          />
        </div>
        <div className="glossary-tm-container">
          <KeysSelect
            className={`${
              highlightMandatoryOnSubmit.keys
                ? ' select_highlight_mandatory'
                : ''
            }`}
            onToggleOption={() => setHighlightMandatoryOnSubmit({})}
          />
          <div className="input-with-label__wrapper">
            <DomainSelect />
          </div>
          <div className="input-with-label__wrapper">
            <SubdomainSelect />
          </div>
        </div>
      </div>

      <div className="glossary-form-line">
        <div
          className={`input-with-label__wrapper ${
            config.isSourceRTL ? ' rtl' : ''
          }`}
        >
          <label>Original term*</label>
          <input
            className={`${
              highlightMandatoryOnSubmit.originalTerm
                ? 'highlight_mandatory'
                : ''
            } glossary-term-original`}
            name="glossary-term-original"
            value={termForm[ORIGINAL_TERM]}
            onChange={(event) =>
              updateTermForm(ORIGINAL_TERM, event.target.value)
            }
          />
        </div>
        <div
          className={`input-with-label__wrapper ${
            config.isTargetRTL ? ' rtl' : ''
          }`}
        >
          <label>Translated term*</label>
          <input
            className={`${
              highlightMandatoryOnSubmit.translatedTerm
                ? 'highlight_mandatory'
                : ''
            } glossary-term-translated`}
            name="glossary-term-translated"
            value={termForm[TRANSLATED_TERM]}
            onChange={(event) =>
              updateTermForm(TRANSLATED_TERM, event.target.value)
            }
          />
        </div>
      </div>
      {showMore && (
        <div className="glossary-form-line more-line">
          <div>
            <div
              className={`input-with-label__wrapper ${
                config.isTargetRTL ? ' rtl' : ''
              }`}
            >
              <label>Notes</label>
              <textarea
                className="input-large"
                name="glossary-term-description-source"
                value={termForm[ORIGINAL_DESCRIPTION]}
                onChange={(event) =>
                  updateTermForm(ORIGINAL_DESCRIPTION, event.target.value)
                }
              />
            </div>
            <div
              className={`input-with-label__wrapper ${
                config.isSourceRTL ? ' rtl' : ''
              }`}
            >
              <label>Example of use</label>
              <textarea
                className="input-large"
                name="glossary-term-example-source"
                value={termForm[ORIGINAL_EXAMPLE]}
                onChange={(event) =>
                  updateTermForm(ORIGINAL_EXAMPLE, event.target.value)
                }
              />
            </div>
          </div>
          <div>
            <div
              className={`input-with-label__wrapper ${
                config.isTargetRTL ? ' rtl' : ''
              }`}
            >
              <label>Notes</label>
              <textarea
                className="input-large"
                name="glossary-term-description-target"
                value={termForm[TRANSLATED_DESCRIPTION]}
                onChange={(event) =>
                  updateTermForm(TRANSLATED_DESCRIPTION, event.target.value)
                }
              />
            </div>
            <div
              className={`input-with-label__wrapper ${
                config.isTargetRTL ? ' rtl' : ''
              }`}
            >
              <label>Example of use</label>
              <textarea
                className="input-large"
                name="glossary-term-example-target"
                value={termForm[TRANSLATED_EXAMPLE]}
                onChange={(event) =>
                  updateTermForm(TRANSLATED_EXAMPLE, event.target.value)
                }
              />
            </div>
          </div>
        </div>
      )}
      <div className="glossary_buttons-container">
        <div></div>
        <div
          className={`glossary-more ${!showMore ? 'show-less' : 'show-more'}`}
          onClick={() => setShowMore(!showMore)}
        >
          <MoreIcon />
          <span>{showMore ? 'Hide options' : 'More options'}</span>
        </div>
        <div className="glossary_buttons">
          <button
            className="glossary__button-cancel"
            disabled={isLoading}
            onClick={resetForm}
          >
            Cancel
          </button>
          <button
            className="glossary__button-add"
            onClick={onSubmitAddOrUpdateTerm}
            disabled={isLoading}
          >
            {isLoading && <div className="loader loader_on"></div>}
            {modifyElement ? 'Update' : 'Add'}
          </button>
        </div>
      </div>
    </div>
  )
}

export default TermForm
