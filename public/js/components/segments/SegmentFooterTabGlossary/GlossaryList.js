import React, {
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
} from 'react'
import {isEqual} from 'lodash'
import SegmentStore from '../../../stores/SegmentStore'
import {GlossaryItem} from './GlossaryItem'
import {TabGlossaryContext} from './TabGlossaryContext'
import SegmentConstants from '../../../constants/SegmentConstants'
import SegmentActions from '../../../actions/SegmentActions'

const setIntervalCounter = ({callback, delay, maximumNumOfTime}) => {
  let count = 0
  let interval

  const reset = () => {
    clearInterval(interval)
    count = 0
  }

  const set = ({callback, delay, maximumNumOfTime}) => {
    reset()
    interval = setInterval(() => {
      if (
        typeof maximumNumOfTime === 'number' &&
        count > maximumNumOfTime - 1
      ) {
        reset()
        return
      }

      const result = callback()
      count++

      if (result) reset()
    }, delay)
  }

  set({callback, delay, maximumNumOfTime})
}

const GlossaryList = () => {
  const {
    terms,
    searchTerm,
    previousSearchTermRef,
    isLoading,
    setSearchTerm,
    segment,
    keys,
    setShowForm,
    setModifyElement,
    setShowMore,
    setSelectsActive,
    domains,
    subdomains,
    getRequestPayloadTemplate,
    termsStatusDeleting,
    setTermsStatusDeleting,
  } = useContext(TabGlossaryContext)

  const [termHighlight, setTermHighlight] = useState(undefined)

  const scrollItemsRef = useRef()
  const previousTerms = useRef()

  const scrollToTerm = useCallback(
    async ({id, isTarget, type}) => {
      if (!id || !scrollItemsRef.current) return
      // reset search results
      setSearchTerm('')

      await new Promise((resolve) => {
        if (
          !isEqual(
            segment.glossary.map(({term_id}) => term_id),
            segment.glossary_search_results.map(({term_id}) => term_id),
          )
        ) {
          setIntervalCounter({
            callback: () => {
              if (scrollItemsRef.current?.children.length) {
                resolve()
                return true
              }
            },
            delay: 100,
            maximumNumOfTime: 5,
          })
        } else {
          resolve()
        }
      })
      const indexToScroll = Array.from(
        scrollItemsRef.current?.children,
      ).findIndex((element) => element.getAttribute('id') === id)

      const element = scrollItemsRef.current?.children[indexToScroll]

      if (element) {
        await new Promise((resolve) => {
          setIntervalCounter({
            callback: () => {
              if (element.offsetHeight) {
                resolve()
                return true
              }
            },
            delay: 100,
            maximumNumOfTime: 5,
          })
        })

        scrollItemsRef.current.scrollTo(0, indexToScroll * element.offsetHeight)
        setTermHighlight({index: indexToScroll, isTarget, type})
        const labelElement =
          element.getElementsByClassName('glossary_word')[isTarget ? 1 : 0]
        labelElement.onanimationend = () => setTermHighlight(undefined)
      }
    },
    [segment.glossary, segment.glossary_search_results, setSearchTerm],
  )

  // register listener highlight term
  useEffect(() => {
    const highlightTerm = ({sid, termId, isTarget, type}) => {
      if (sid === segment.sid) scrollToTerm({id: termId, isTarget, type})
    }

    SegmentStore.addListener(
      SegmentConstants.HIGHLIGHT_GLOSSARY_TERM,
      highlightTerm,
    )

    return () => {
      SegmentStore.removeListener(
        SegmentConstants.HIGHLIGHT_GLOSSARY_TERM,
        highlightTerm,
      )
    }
  }, [scrollToTerm, segment.sid])

  useEffect(() => {
    if (
      previousTerms?.current &&
      isEqual(
        terms.map(({term_id}) => term_id),
        previousTerms?.current.map(({term_id}) => term_id),
      )
    )
      return

    if (scrollItemsRef?.current) scrollItemsRef.current.scrollTo(0, 0)
    previousTerms.current = terms
  }, [terms])

  const onModifyItem = (term) => {
    setShowMore(true)
    setShowForm(true)
    setModifyElement(term)
    // prefill selects active keys, domain and subdomain
    const {metadata} = term
    setSelectsActive((prevState) => ({
      ...prevState,
      keys: [keys.find(({id}) => id === metadata?.key)],
      domain: domains.find(({name}) => name === metadata?.domain),
      subdomain: subdomains.find(({name}) => name === metadata?.subdomain),
    }))
  }

  const onDeleteItem = (term) => {
    const {term_id, metadata} = term
    setTermsStatusDeleting((prevState) => [...prevState, term_id])
    SegmentActions.deleteGlossaryItem(
      getRequestPayloadTemplate({
        term: {term_id, metadata: {key: metadata.key}},
        isDelete: true,
      }),
    )
  }

  const onClickTerm = (term) =>
    SegmentActions.copyGlossaryItemInEditarea(term, segment)

  return (
    <div ref={scrollItemsRef} className="glossary_items">
      {!terms.length && isLoading ? (
        <span className="loading_label">Loading</span>
      ) : (
        terms.map((term, index) => (
          <GlossaryItem
            key={index}
            item={term}
            modifyElement={() => onModifyItem(term)}
            deleteElement={() => onDeleteItem(term)}
            highlight={index === termHighlight?.index && termHighlight}
            onClick={onClickTerm}
            isEnabledToModify={
              !!keys.find(({key}) => key === term?.metadata?.key) &&
              !term.isBlacklist &&
              !isLoading
            }
            isStatusDeleting={
              !!termsStatusDeleting.find((value) => value === term.term_id)
            }
            isBlacklist={term.isBlacklist}
          />
        ))
      )}
      {!isLoading && !terms.length && (
        <div className="no-terms-result">
          {searchTerm && searchTerm === previousSearchTermRef.current ? (
            <span>
              No results for <b>{searchTerm}</b>
            </span>
          ) : !searchTerm ? (
            <span>No results</span>
          ) : undefined}
        </div>
      )}
    </div>
  )
}

export default GlossaryList
