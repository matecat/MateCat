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

export const TERM_FORM_FIELDS = {
  DEFINITION: 'definition',
  ORIGINAL_TERM: 'originalTerm',
  ORIGINAL_DESCRIPTION: 'originalDescription',
  ORIGINAL_EXAMPLE: 'originalExample',
  TRANSLATED_TERM: 'translatedTerm',
  TRANSLATED_DESCRIPTION: 'translatedDescription',
  TRANSLATED_EXAMPLE: 'translatedExample',
}

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
            term: getFieldValue(originalTerm),
            note: getFieldValue(originalDescription),
            sentence: getFieldValue(originalExample),
          }
        : null
      const target = !isDelete
        ? {
            term: getFieldValue(translatedTerm),
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
    [modifyElement, segment.sid, selectsActive, termForm],
  )

  // get TM keys and add actions listener
  useEffect(() => {
    const refreshCheckQa = () =>
      SegmentActions.getSegmentsQa(SegmentStore.getCurrentSegment())
    const addGlossaryItem = () => {
      setTimeout(() => {
        setIsLoading(false)
        setSearchTerm('')
        resetForm()
        refreshGlossary()
        refreshCheckQa()
      }, 500)
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

    SegmentStore.addListener(
      SegmentConstants.ADD_GLOSSARY_ITEM,
      addGlossaryItem,
    )
    SegmentStore.addListener(SegmentConstants.CHANGE_GLOSSARY, addGlossaryItem)
    CatToolStore.addListener(CatToolConstants.UPDATE_DOMAINS, setDomains)
    CatToolStore.addListener(CatToolConstants.UPDATE_TM_KEYS, setJobTmKeys)
    CatToolStore.addListener(
      CatToolConstants.ON_TM_KEYS_CHANGE_STATUS,
      refreshGlossary,
    )
    CatToolStore.addListener(
      CatToolConstants.HAVE_KEYS_GLOSSARY,
      onReceiveHaveKeysGlossary,
    )
    SegmentStore.addListener(
      CatToolConstants.DELETE_FROM_GLOSSARY,
      onDeleteTerm,
    )

    return () => {
      SegmentStore.removeListener(
        SegmentConstants.ADD_GLOSSARY_ITEM,
        addGlossaryItem,
      )
      SegmentStore.removeListener(
        SegmentConstants.CHANGE_GLOSSARY,
        addGlossaryItem,
      )
      CatToolStore.removeListener(CatToolConstants.UPDATE_DOMAINS, setDomains)
      CatToolStore.removeListener(CatToolConstants.UPDATE_TM_KEYS, setJobTmKeys)
      CatToolStore.removeListener(
        CatToolConstants.ON_TM_KEYS_CHANGE_STATUS,
        refreshGlossary,
      )
      CatToolStore.removeListener(
        CatToolConstants.HAVE_KEYS_GLOSSARY,
        onReceiveHaveKeysGlossary,
      )
      SegmentStore.removeListener(
        CatToolConstants.DELETE_FROM_GLOSSARY,
        onDeleteTerm,
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
          <SegmentFooterTabError />
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

export const DeleteIcon = () => {
  return (
    <svg width="14" height="16" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path
        d="M1 3.333a.667.667 0 0 0 0 1.334V3.333Zm12 1.334a.667.667 0 1 0 0-1.334v1.334ZM11.667 4h.666a.667.667 0 0 0-.666-.667V4Zm-9.334 9.333h-.666.666ZM3.667 4A.667.667 0 1 0 5 4H3.667Zm2-2.667V.667v.666Zm2.666 0V.667v.666ZM9 4a.667.667 0 0 0 1.333 0H9ZM6.333 7.333a.667.667 0 1 0-1.333 0h1.333Zm-1.333 4a.667.667 0 0 0 1.333 0H5Zm4-4a.667.667 0 0 0-1.333 0H9Zm-1.333 4a.667.667 0 1 0 1.333 0H7.667ZM1 4.667h1.333V3.333H1v1.334Zm1.333 0H13V3.333H2.333v1.334ZM11 4v9.333h1.333V4H11Zm0 9.333c0 .177-.07.347-.195.472l.943.943a2 2 0 0 0 .585-1.415H11Zm-.195.472a.667.667 0 0 1-.472.195v1.333a2 2 0 0 0 1.415-.585l-.943-.943Zm-.472.195H3.667v1.333h6.666V14Zm-6.666 0a.667.667 0 0 1-.472-.195l-.943.943a2 2 0 0 0 1.415.585V14Zm-.472-.195A.667.667 0 0 1 3 13.333H1.667a2 2 0 0 0 .585 1.415l.943-.943ZM3 13.333V4H1.667v9.333H3Zm-.667-8.666h9.334V3.333H2.333v1.334ZM5 4V2.667H3.667V4H5Zm0-1.333c0-.177.07-.347.195-.472l-.943-.943a2 2 0 0 0-.585 1.415H5Zm.195-.472A.667.667 0 0 1 5.667 2V.667a2 2 0 0 0-1.415.585l.943.943ZM5.667 2h2.666V.667H5.667V2Zm2.666 0c.177 0 .347.07.472.195l.943-.943A2 2 0 0 0 8.333.667V2Zm.472.195A.667.667 0 0 1 9 2.667h1.333a2 2 0 0 0-.585-1.415l-.943.943ZM9 2.667V4h1.333V2.667H9ZM5 7.333v4h1.333v-4H5Zm2.667 0v4H9v-4H7.667Z"
        fillRule="evenodd"
        fill="currentColor"
      />
    </svg>
  )
}

export const ModifyIcon = () => {
  return (
    <svg width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path
        d="M7.333 3.333a.667.667 0 0 0 0-1.333v1.333Zm-4.666-.666V2v.667ZM1.333 4H.667h.666Zm0 9.333H.667h.666ZM14 8.667a.667.667 0 0 0-1.333 0H14Zm-1.667-7-.471-.472.471.472Zm1-.415V.586v.666Zm1 2.415-.471-.472.471.472ZM8 10l.162.647a.668.668 0 0 0 .31-.176L8 10Zm-2.667.667-.647-.162a.667.667 0 0 0 .809.808l-.162-.646ZM6 8l-.471-.471a.667.667 0 0 0-.176.31L6 8Zm1.333-6H2.667v1.333h4.666V2ZM2.667 2a2 2 0 0 0-1.415.586l.943.943a.667.667 0 0 1 .472-.196V2Zm-1.415.586A2 2 0 0 0 .667 4H2c0-.177.07-.346.195-.471l-.943-.943ZM.667 4v9.333H2V4H.667Zm0 9.333a2 2 0 0 0 .585 1.415l.943-.943A.667.667 0 0 1 2 13.333H.667Zm.585 1.415a2 2 0 0 0 1.415.585V14a.666.666 0 0 1-.472-.195l-.943.943Zm1.415.585H12V14H2.667v1.333Zm9.333 0a2 2 0 0 0 1.414-.585l-.943-.943A.666.666 0 0 1 12 14v1.333Zm1.414-.585A2 2 0 0 0 14 13.332h-1.333c0 .177-.07.347-.196.472l.943.943ZM14 13.332V8.667h-1.333v4.666H14ZM12.805 2.138c.14-.14.33-.219.528-.219V.586c-.552 0-1.08.219-1.471.61l.943.942Zm.528-.219c.198 0 .389.079.529.22l.943-.944c-.39-.39-.92-.61-1.472-.61V1.92Zm.529.22c.14.14.219.33.219.528h1.333c0-.552-.22-1.082-.61-1.472l-.942.943Zm.219.528a.748.748 0 0 1-.22.528l.944.943c.39-.39.61-.92.61-1.471H14.08Zm-.22.528L7.53 9.53l.942.942 6.334-6.333-.943-.943ZM7.839 9.353l-2.666.667.323 1.293 2.667-.666-.324-1.294ZM5.98 10.828l.667-2.666-1.294-.324-.667 2.667 1.294.323Zm.491-2.357 6.334-6.333-.943-.943L5.529 7.53l.942.942Z"
        fillRule="evenodd"
        fill="currentColor"
      />
    </svg>
  )
}

export const GlossaryDefinitionIcon = () => {
  return (
    <svg width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path
        d="M13.333 12.667v-2H4.667a2 2 0 0 0-2 2m3.2 2H11.2c.747 0 1.12 0 1.405-.146.251-.128.455-.332.583-.582.145-.286.145-.659.145-1.406V3.467c0-.747 0-1.12-.145-1.406a1.333 1.333 0 0 0-.583-.582c-.285-.146-.658-.146-1.405-.146H5.867c-1.12 0-1.68 0-2.108.218a2 2 0 0 0-.875.874c-.217.428-.217.988-.217 2.108v6.934c0 1.12 0 1.68.217 2.108a2 2 0 0 0 .875.874c.427.218.987.218 2.108.218Z"
        stroke="currentColor"
      />
    </svg>
  )
}

export const MoreIcon = () => {
  return (
    <svg width="12" height="8" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path
        d="M5.2 6.933 1.2 1.6A1 1 0 0 1 2 0h8a1 1 0 0 1 .8 1.6l-4 5.333a1 1 0 0 1-1.6 0Z"
        fill="#AEBDCD"
      />
    </svg>
  )
}

export const LockIcon = () => {
  return (
    <svg
      width="20"
      height="22"
      viewBox="0 0 20 22"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path
        d="M5 9V7C5 4.23858 7.23858 2 10 2C12.0503 2 13.8124 3.2341 14.584 5M10 13.5V15.5M6.8 20H13.2C14.8802 20 15.7202 20 16.362 19.673C16.9265 19.3854 17.3854 18.9265 17.673 18.362C18 17.7202 18 16.8802 18 15.2V13.8C18 12.1198 18 11.2798 17.673 10.638C17.3854 10.0735 16.9265 9.6146 16.362 9.32698C15.7202 9 14.8802 9 13.2 9H6.8C5.11984 9 4.27976 9 3.63803 9.32698C3.07354 9.6146 2.6146 10.0735 2.32698 10.638C2 11.2798 2 12.1198 2 13.8V15.2C2 16.8802 2 17.7202 2.32698 18.362C2.6146 18.9265 3.07354 19.3854 3.63803 19.673C4.27976 20 5.11984 20 6.8 20Z"
        stroke="#9E9E9E"
        strokeWidth={2.66}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  )
}
