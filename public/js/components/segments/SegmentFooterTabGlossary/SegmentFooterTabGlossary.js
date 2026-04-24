import React, {
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
} from 'react'
import PropTypes from 'prop-types'
import SegmentActions from '../../../actions/SegmentActions'
import SegmentStore from '../../../stores/SegmentStore'
import CatToolStore from '../../../stores/CatToolStore'
import SegmentConstants from '../../../constants/SegmentConstants'
import CatToolConstants from '../../../constants/CatToolConstants'
import CatToolActions from '../../../actions/CatToolActions'
import {TabGlossaryContext} from './TabGlossaryContext'
import {SearchTerms} from './SearchTerms'
import GlossaryList from './GlossaryList'
import TermForm from './TermForm'
import {SegmentContext} from '../SegmentContext'
import SegmentUtils from '../../../utils/segmentUtils'
import {SegmentFooterTabError} from '../SegmentFooterTabError'
import {checkMymemoryStatus} from '../../../api/checkMymemoryStatus'
import AppDispatcher from '../../../stores/AppDispatcher'
import {removeZeroWidthSpace} from '../utils/DraftMatecatUtils/tagUtils'

import {TERM_FORM_FIELDS} from './GlossaryConstants'
export {
  TERM_FORM_FIELDS,
  DeleteIcon,
  ModifyIcon,
  GlossaryDefinitionIcon,
  MoreIcon,
  LockIcon,
} from './GlossaryConstants'

const initialState = {
  keys: [],
  domains: [],
  subdomains: [],
  terms: [],
  termForm: Object.entries(TERM_FORM_FIELDS).reduce(
    (acc, [, value]) => ({...acc, [value]: ''}),
    {},
  ),
}

export const SegmentFooterTabGlossary = ({
  code,
  active_class,
  segment,
  notifyLoadingStatus,
}) => {
  const [isActive, setIsActive] = useState(false)
  const [searchTerm, setSearchTerm] = useState('')
  const [showForm, setShowForm] = useState(false)
  const [showMore, setShowMore] = useState(false)
  const [domainsResponse, setDomainsResponse] = useState(undefined)
  const [keys, setKeys] = useState(initialState.keys)
  const [domains, setDomains] = useState(initialState.domains)
  const [subdomains, setSubdomains] = useState(initialState.subdomains)
  const [selectsActive, setSelectsActive] = useState({
    keys: [],
    domain: undefined,
    subdomain: undefined,
  })
  const [terms, setTerms] = useState(initialState.terms)
  const [modifyElement, setModifyElement] = useState()
  const [termForm, setTermForm] = useState(initialState.termForm)
  const [isLoading, setIsLoading] = useState(true)
  const [haveKeysGlossary, setHaveKeysGlossary] = useState(undefined)
  const [termsStatusDeleting, setTermsStatusDeleting] = useState([])

  const {clientConnected, clientId} = useContext(SegmentContext)
  const ref = useRef()
  const previousSearchTermRef = useRef('')

  const notifyLoadingStatusToParent = useCallback(
    (value) => {
      if (notifyLoadingStatus) notifyLoadingStatus({code, isLoading: value})
    },
    [code, notifyLoadingStatus],
  )

  const resetForm = useCallback(() => {
    setTermForm(initialState.termForm)
    setShowForm(false)
    setShowMore(false)
    setModifyElement(undefined)
  }, [])

  const openForm = useCallback(() => {
    resetForm()
    setShowForm(true)
  }, [resetForm])

  const getRequestPayloadTemplate = useCallback(
    ({term = modifyElement, isDelete} = {}) => {
      const getFieldValue = (value) => (value ? value : null)

      const {
        definition,
        originalTerm,
        originalDescription,
        originalExample,
        translatedTerm,
        translatedDescription,
        translatedExample,
      } = termForm
      const {keys = {}, domain, subdomain} = selectsActive
      const {
        term_id = null,
        matching_words = null,
        metadata: {
          create_date = null,
          last_update = null,
          key,
          key_name = null,
        } = {},
      } = term || {}

      const source = !isDelete
        ? {
            term: removeZeroWidthSpace(getFieldValue(originalTerm)),
            note: getFieldValue(originalDescription),
            sentence: getFieldValue(originalExample),
          }
        : null
      const target = !isDelete
        ? {
            term: removeZeroWidthSpace(getFieldValue(translatedTerm)),
            note: getFieldValue(translatedDescription),
            sentence: getFieldValue(translatedExample),
          }
        : null
      const metadata = !isDelete
        ? {
            definition,
            ...(term
              ? {key, key_name}
              : {
                  keys: keys.map(({key, name, isMissingName}) => ({
                    key,
                    key_name: !isMissingName ? name : '',
                  })),
                }),
            domain: domain ? domain.name : '',
            subdomain: subdomain ? subdomain.name : '',
            create_date,
            last_update,
          }
        : {
            key,
            definition: null,
            key_name: null,
            domain: null,
            subdomain: null,
            create_date: null,
            last_update: null,
          }

      return {
        id_segment: segment.sid,
        id_client: clientId,
        id_job: config.id_job,
        password: config.password,
        term: {
          term_id,
          source_language: config.source_code,
          target_language: config.target_code,
          source,
          target,
          matching_words,
          metadata,
        },
      }
    },
    [modifyElement, segment.sid, selectsActive, termForm, clientId],
  )

  const pollMymemoryStatus = async (
    {uuid},
    successCallback,
    timeoutCallback,
  ) => {
    const startTime = Date.now()

    const checkCondition = async () => {
      try {
        const data = await checkMymemoryStatus({uuid})

        if (data.responseData?.id > 0) {
          successCallback(data)
          return
        }

        if (Date.now() - startTime >= 60000) {
          if (timeoutCallback) {
            timeoutCallback()
          }
          return
        }

        setTimeout(checkCondition, 1000)
      } catch (error) {
        setTimeout(checkCondition, 1000)
      }
    }

    await checkCondition()
  }

  // get TM keys and add actions listener
  useEffect(() => {
    const refreshCheckQa = () =>
      SegmentActions.getSegmentsQa(SegmentStore.getCurrentSegment())
    const updateGlossaryItem = (payload) => addGlossaryItem(payload, true)
    const addGlossaryCallback = (update = false) => {
      setSearchTerm('')
      resetForm()
      refreshGlossary()
      setTimeout(refreshCheckQa, 500)
      AppDispatcher.dispatch({
        actionType: SegmentConstants.SHOW_FOOTER_MESSAGE,
        sid: segment.sid,
        message: update
          ? 'A termbase entry has been updated'
          : 'A termbase entry has been added',
      })
    }
    const addGlossaryItem = (payload, update) => {
      pollMymemoryStatus(
        {uuid: payload.request_id},
        () => setTimeout(() => addGlossaryCallback(update), 1000),
        () => setTimeout(() => addGlossaryCallback(update), 1000),
      )
    }
    const setDomains = ({entries}) => {
      setDomainsResponse(entries)
    }
    const setJobTmKeys = (keys) => {
      setKeys(keys)
    }
    const refreshGlossary = () =>
      SegmentActions.getGlossaryForSegment({
        sid: segment.sid,
        text: segment.segment,
        shouldRefresh: true,
      })
    const onReceiveHaveKeysGlossary = ({value, wasAlreadyVerified}) => {
      setHaveKeysGlossary(value)
      if (value && !wasAlreadyVerified) {
        SegmentActions.getGlossaryForSegment({
          sid: segment.sid,
          text: segment.segment,
        })
      } else {
        setIsLoading(false)
      }
    }
    const onDeleteTerm = (sid, term) => {
      setTermsStatusDeleting((prevState) =>
        prevState.filter((value) => value !== term.term_id),
      )
      refreshCheckQa()
    }
    // eslint-disable-next-line
    const openFormPrefill = ({sid, actionType, ...filledFields}) => {
      setTermForm({
        ...initialState.termForm,
        ...filledFields,
      })
      setShowForm(true)
    }

    SegmentStore.addListener(
      SegmentConstants.ADD_GLOSSARY_ITEM,
      addGlossaryItem,
    )
    SegmentStore.addListener(
      SegmentConstants.CHANGE_GLOSSARY,
      updateGlossaryItem,
    )
    CatToolStore.addListener(CatToolConstants.UPDATE_DOMAINS, setDomains)
    CatToolStore.addListener(CatToolConstants.UPDATE_TM_KEYS, setJobTmKeys)
    CatToolStore.addListener(
      CatToolConstants.HAVE_KEYS_GLOSSARY,
      onReceiveHaveKeysGlossary,
    )
    SegmentStore.addListener(
      CatToolConstants.DELETE_FROM_GLOSSARY,
      onDeleteTerm,
    )
    SegmentStore.addListener(
      SegmentConstants.OPEN_GLOSSARY_FORM_PREFILL,
      openFormPrefill,
    )

    return () => {
      SegmentStore.removeListener(
        SegmentConstants.ADD_GLOSSARY_ITEM,
        addGlossaryItem,
      )
      SegmentStore.removeListener(
        SegmentConstants.CHANGE_GLOSSARY,
        updateGlossaryItem,
      )
      CatToolStore.removeListener(CatToolConstants.UPDATE_DOMAINS, setDomains)
      CatToolStore.removeListener(CatToolConstants.UPDATE_TM_KEYS, setJobTmKeys)
      CatToolStore.removeListener(
        CatToolConstants.HAVE_KEYS_GLOSSARY,
        onReceiveHaveKeysGlossary,
      )
      SegmentStore.removeListener(
        CatToolConstants.DELETE_FROM_GLOSSARY,
        onDeleteTerm,
      )
      SegmentStore.removeListener(
        SegmentConstants.OPEN_GLOSSARY_FORM_PREFILL,
        openFormPrefill,
      )
    }
  }, [segment.sid, segment.segment, resetForm])

  // search results listener
  useEffect(() => {
    const onReceiveGlossaryBySearch = () =>
      !isLoading && notifyLoadingStatusToParent(false)

    SegmentStore.addListener(
      SegmentConstants.SET_GLOSSARY_TO_CACHE_BY_SEARCH,
      onReceiveGlossaryBySearch,
    )

    return () => {
      SegmentStore.removeListener(
        SegmentConstants.SET_GLOSSARY_TO_CACHE_BY_SEARCH,
        onReceiveGlossaryBySearch,
      )
    }
  }, [isLoading, notifyLoadingStatusToParent])

  // error listener for set/update/delete
  useEffect(() => {
    const onError = () => setIsLoading(false)
    const onErrorDelete = (sid, error) => {
      onError()
      const {term_id} = error?.payload?.term ?? {}
      setTermsStatusDeleting((prevState) =>
        prevState.filter((value) => value !== term_id),
      )
    }

    SegmentStore.addListener(SegmentConstants.ERROR_ADD_GLOSSARY_ITEM, onError)
    SegmentStore.addListener(SegmentConstants.ERROR_CHANGE_GLOSSARY, onError)
    SegmentStore.addListener(
      SegmentConstants.ERROR_DELETE_FROM_GLOSSARY,
      onErrorDelete,
    )

    return () => {
      SegmentStore.removeListener(
        SegmentConstants.ERROR_ADD_GLOSSARY_ITEM,
        onError,
      )
      SegmentStore.removeListener(
        SegmentConstants.ERROR_CHANGE_GLOSSARY,
        onError,
      )
      SegmentStore.removeListener(
        SegmentConstants.ERROR_DELETE_FROM_GLOSSARY,
        onErrorDelete,
      )
    }
  }, [])

  // set domains by key
  useEffect(() => {
    if (!selectsActive.keys.length || !domainsResponse) {
      setDomains([])
      return
    }
    const selectedKeys = selectsActive.keys
    const domainsResponseAllKeys = selectedKeys.reduce(
      (acc, {key}) => [
        ...acc,
        ...(Array.isArray(domainsResponse[key])
          ? [...domainsResponse[key]]
          : []),
      ],
      [],
    )
    setDomains(
      domainsResponseAllKeys.map(({domain, subdomains}, index) => ({
        id: index.toString(),
        name: domain,
        subdomains,
      })),
    )
  }, [domainsResponse, selectsActive.keys])

  // set subdomains by domain
  useEffect(() => {
    if (!selectsActive.domain || !selectsActive.domain?.subdomains) {
      setSubdomains([])
      return
    }
    if (selectsActive.domain?.subdomains)
      setSubdomains(
        selectsActive.domain?.subdomains.map((name, index) => ({
          id: index.toString(),
          name,
        })),
      )
  }, [selectsActive.domain])

  useEffect(() => {
    if (!segment?.glossary_search_results) return
    setTerms(segment.glossary_search_results)
    setIsLoading(false)
  }, [segment?.glossary_search_results])

  useEffect(() => {
    setSelectsActive((prevState) => ({
      ...prevState,
      keys:
        keys.length > 1
          ? SegmentUtils.getSelectedKeysGlossary(keys)
          : keys.length === 1
            ? [keys[0]]
            : [],
    }))
  }, [keys])

  useEffect(() => {
    const {metadata = {}} = modifyElement ?? {}

    setSelectsActive((prevState) =>
      prevState.domain?.name !== metadata.domain || !modifyElement
        ? {
            ...prevState,
            domain: undefined,
            subdomain: undefined,
          }
        : prevState,
    )
  }, [domains, modifyElement])

  useEffect(() => {
    const {metadata = {}} = modifyElement ?? {}

    setSelectsActive((prevState) =>
      prevState.domain?.name !== metadata.domain || !modifyElement
        ? {
            ...prevState,
            subdomain: undefined,
          }
        : prevState,
    )
  }, [subdomains, modifyElement])

  // prefill term form
  useEffect(() => {
    if (!modifyElement) return
    const {
      DEFINITION,
      ORIGINAL_TERM,
      ORIGINAL_DESCRIPTION,
      ORIGINAL_EXAMPLE,
      TRANSLATED_TERM,
      TRANSLATED_DESCRIPTION,
      TRANSLATED_EXAMPLE,
    } = TERM_FORM_FIELDS
    const {metadata, source, target} = modifyElement
    setTermForm({
      [DEFINITION]: metadata.definition,
      [ORIGINAL_TERM]: source.term,
      [ORIGINAL_DESCRIPTION]: source.note,
      [ORIGINAL_EXAMPLE]: source.sentence,
      [TRANSLATED_TERM]: target.term,
      [TRANSLATED_DESCRIPTION]: target.note,
      [TRANSLATED_EXAMPLE]: target.sentence,
    })

    const domainsForActiveKeys = domainsResponse?.[metadata.key]?.map(
      ({domain, subdomains}, index) => ({
        id: index.toString(),
        name: domain,
        subdomains,
      }),
    )

    if (domainsForActiveKeys)
      setSelectsActive((prevState) => ({
        ...prevState,
        domain: domainsForActiveKeys.find(({name}) => name === metadata.domain),
        subdomain: domainsForActiveKeys
          .find(({name}) => name === metadata.domain)
          ?.subdomains.map((name, index) => ({
            id: index.toString(),
            name,
          }))
          ?.find(({name}) => name === metadata.subdomain),
      }))
  }, [modifyElement, domainsResponse])

  // notify loading status to parent (SegmentFooter)
  useEffect(() => {
    notifyLoadingStatusToParent(isLoading)
  }, [isLoading, notifyLoadingStatusToParent])

  useEffect(() => {
    setIsActive(!!active_class)
  }, [active_class])

  useEffect(() => {
    if (clientConnected) {
      CatToolActions.retrieveJobKeys()
    }
  }, [clientConnected])

  return (
    <TabGlossaryContext.Provider
      value={{
        ref,
        isActive,
        segment,
        keys,
        domains,
        setDomains,
        subdomains,
        setSubdomains,
        terms,
        searchTerm,
        setSearchTerm,
        previousSearchTermRef,
        haveKeysGlossary,
        isLoading,
        setIsLoading,
        openForm,
        termForm,
        setTermForm,
        selectsActive,
        setSelectsActive,
        modifyElement,
        setModifyElement,
        showMore,
        setShowMore,
        resetForm,
        domainsResponse,
        getRequestPayloadTemplate,
        setShowForm,
        termsStatusDeleting,
        setTermsStatusDeleting,
        notifyLoadingStatusToParent,
      }}
    >
      <div
        ref={ref}
        className={`tab sub-editor glossary ${active_class}`}
        tabIndex="0"
      >
        {!clientConnected ? (
          clientConnected === false && <SegmentFooterTabError />
        ) : haveKeysGlossary ? (
          <>
            <SearchTerms />
            {showForm && <TermForm />}
            <GlossaryList />
          </>
        ) : showForm ? (
          <TermForm />
        ) : haveKeysGlossary === false ? (
          <div className="no_keys_glossary">
            <p>No glossary available.</p>
            <button className="glossary__button-add" onClick={openForm}>
              + Click here to create one
            </button>
          </div>
        ) : (
          <span className="loading_label">Loading</span>
        )}
      </div>
    </TabGlossaryContext.Provider>
  )
}

SegmentFooterTabGlossary.propTypes = {
  code: PropTypes.string,
  active_class: PropTypes.string,
  segment: PropTypes.object,
  notifyLoadingStatus: PropTypes.func,
}

