import {useState, useEffect, useLayoutEffect, useRef, useCallback} from 'react'
import {fromJS} from 'immutable'
import Cookies from 'js-cookie'

import {getOutsourceQuote} from '../api/getOutsourceQuote'
import CommonUtils from '../utils/commonUtils'

const CACHE_TTL_MS = 10 * 60 * 1000

// Successful quotes shared by the hook instances that opt in with `cache`, so
// reopening the same job (or showing it on another page) does not ask the
// vendor again. Entries are deep copies: no caller can mutate a cached quote
const quoteCache = new Map()

const copyQuote = (quoteData) => JSON.parse(JSON.stringify(quoteData))

const getCacheKey = ({
  jobId,
  jobPassword,
  typeOfService,
  fixedDelivery,
  timezone,
  currency,
}) =>
  [jobId, jobPassword, typeOfService, fixedDelivery, timezone, currency].join(
    '|',
  )

const readCache = (key) => {
  const entry = quoteCache.get(key)
  if (!entry) return null
  if (Date.now() - entry.time > CACHE_TTL_MS) {
    quoteCache.delete(key)
    return null
  }
  return copyQuote(entry.quoteData)
}

export const clearOutsourceQuoteCache = () => quoteCache.clear()

// Drops every cached quote of a job, whatever service, delivery, timezone or
// currency it was asked for. Call it once the job is ordered
export const evictOutsourceQuoteCache = (jobId, jobPassword) => {
  const prefix = `${jobId}|${jobPassword}|`
  for (const key of quoteCache.keys()) {
    if (key.startsWith(prefix)) quoteCache.delete(key)
  }
}

/**
 * Hook that manages the outsource quote lifecycle:
 * fetching, state, revision toggle, and derived values.
 *
 * status is one of 'idle' | 'loading' | 'ready' | 'unavailable' | 'error'.
 * With `cache` the successful quotes are kept for 10 minutes and shared with
 * other instances; a cached quote is never applied to an already ordered job.
 */
const useOutsourceQuote = ({
  projectId,
  projectPassword,
  jobId,
  jobPassword,
  jobOutsource,
  enabled,
  getCurrentCurrency,
  debounceMs = 0,
  cache = false,
}) => {
  const [status, setStatus] = useState('idle')
  const [outsource, setOutsource] = useState(false)
  const [revision, setRevision] = useState(false)
  const [chunkQuote, setChunkQuote] = useState(null)
  const [outsourceConfirmed, setOutsourceConfirmed] = useState(!!jobOutsource)
  const [jobOutsourced, setJobOutsourced] = useState(!!jobOutsource)
  const [quoteNotAvailable, setQuoteNotAvailable] = useState(false)
  const [errorQuote, setErrorQuote] = useState(false)
  const [errorOutsource, setErrorOutsource] = useState(false)
  const [deliveryDate, setDeliveryDate] = useState(() =>
    jobOutsource ? new Date(jobOutsource.delivery_date) : null,
  )
  const [selectedTime, setSelectedTime] = useState('12')

  // Refs for form submission data
  const quoteResponseRef = useRef(null)
  const urlOkRef = useRef(null)
  const urlKoRef = useRef(null)
  const confirmUrlsRef = useRef(null)
  const dataKeyRef = useRef(null)
  const selectedDateRef = useRef(null)

  // Keep latest state available in async callbacks
  const revisionRef = useRef(revision)
  useEffect(() => {
    revisionRef.current = revision
  }, [revision])

  const timezoneRef = useRef(Cookies.get('matecat_timezone'))
  const updateTimezoneRef = useCallback((tz) => {
    timezoneRef.current = tz
  }, [])

  // Only the latest request may update state: a response is dropped when a
  // newer request started, the job changed or the component unmounted
  const requestIdRef = useRef(0)
  const lastOptionsRef = useRef({delivery: undefined, revisionType: undefined})
  // Deferred work that must not outlive the job it was scheduled for
  const selectedTimeTimerRef = useRef(null)
  const toggleTimerRef = useRef(null)
  const cancelPendingWork = useCallback(() => {
    requestIdRef.current++
    clearTimeout(selectedTimeTimerRef.current)
    clearTimeout(toggleTimerRef.current)
  }, [])

  const applyQuote = useCallback((quoteData) => {
    quoteResponseRef.current = quoteData.data[0]
    const chunk = fromJS(quoteData.data[0][0])

    urlOkRef.current = quoteData.return_url.url_ok
    urlKoRef.current = quoteData.return_url.url_ko
    confirmUrlsRef.current = quoteData.return_url.confirm_urls
    dataKeyRef.current = chunk.get('id')

    const isRevision = chunk.get('typeOfService') === 'premium'

    setStatus('ready')
    setOutsource(true)
    setQuoteNotAvailable(false)
    setErrorQuote(false)
    setChunkQuote(chunk)
    setRevision(isRevision)
    setJobOutsourced(chunk.get('outsourced') === '1')
    setOutsourceConfirmed(chunk.get('outsourced') === '1')
    setDeliveryDate(new Date(chunk.get('delivery')))

    clearTimeout(selectedTimeTimerRef.current)
    selectedTimeTimerRef.current = setTimeout(() => {
      const deliveryStr =
        isRevision && chunk.get('r_delivery')
          ? chunk.get('r_delivery')
          : chunk.get('delivery')
      const date = CommonUtils.getGMTDate(deliveryStr)
      if (date?.time2) {
        setSelectedTime(date.time2.split(':')[0])
      }
    })
  }, [])

  const getQuoteOptions = useCallback(
    (delivery, revisionType) => ({
      jobId,
      jobPassword,
      typeOfService:
        revisionType || (revisionRef.current ? 'premium' : 'professional'),
      fixedDelivery: delivery || '',
      timezone: timezoneRef.current,
      currency: getCurrentCurrency(),
    }),
    [jobId, jobPassword, getCurrentCurrency],
  )

  const fetchQuote = useCallback(
    (delivery, revisionType) => {
      lastOptionsRef.current = {delivery, revisionType}
      const options = getQuoteOptions(delivery, revisionType)
      const cacheKey = getCacheKey(options)
      const requestId = ++requestIdRef.current

      const cached = cache && !jobOutsource ? readCache(cacheKey) : null
      if (cached) {
        applyQuote(cached)
        return
      }

      setStatus('loading')
      getOutsourceQuote(
        projectId,
        projectPassword,
        options.jobId,
        options.jobPassword,
        options.fixedDelivery,
        options.typeOfService,
        options.timezone,
        options.currency,
      )
        .then((quoteData) => {
          if (requestId !== requestIdRef.current) return
          if (quoteData.data && quoteData.data.length > 0) {
            if (
              quoteData.data[0][0].quote_available !== '1' &&
              quoteData.data[0][0].outsourced !== '1'
            ) {
              setStatus('unavailable')
              setOutsource(true)
              setQuoteNotAvailable(true)
              return
            } else if (
              quoteData.data[0][0].quote_result !== '1' &&
              quoteData.data[0][0].outsourced !== '1'
            ) {
              setStatus('error')
              setOutsource(true)
              setErrorQuote(true)
              return
            }

            if (cache) {
              quoteCache.set(cacheKey, {
                quoteData: copyQuote(quoteData),
                time: Date.now(),
              })
            }
            applyQuote(quoteData)
          } else {
            setStatus('error')
            setOutsource(false)
            setErrorQuote(true)
            setErrorOutsource(true)
          }
        })
        .catch(() => {
          if (requestId !== requestIdRef.current) return
          setStatus('error')
          setOutsource(false)
          setErrorQuote(true)
          setErrorOutsource(true)
        })
    },
    [
      projectId,
      projectPassword,
      cache,
      jobOutsource,
      getQuoteOptions,
      applyQuote,
    ],
  )

  const retry = useCallback(() => {
    const {delivery, revisionType} = lastOptionsRef.current
    fetchQuote(delivery, revisionType)
  }, [fetchQuote])

  // The effect below only re-runs on job identity changes, so it reads the
  // latest values through refs, updated after render and before that effect
  const fetchQuoteRef = useRef(fetchQuote)
  const getQuoteOptionsRef = useRef(getQuoteOptions)
  const jobOutsourceRef = useRef(jobOutsource)
  const cacheRef = useRef(cache)
  useLayoutEffect(() => {
    fetchQuoteRef.current = fetchQuote
    getQuoteOptionsRef.current = getQuoteOptions
    jobOutsourceRef.current = jobOutsource
    cacheRef.current = cache
  })
  const previousJobKeyRef = useRef(null)

  // Fetch the quote when enabled and whenever the job changes
  useEffect(() => {
    const hasJob = jobId != null && !!jobPassword
    const jobKey = hasJob ? `${jobId}-${jobPassword}` : null

    // A different job starts from a clean slate
    if (previousJobKeyRef.current && previousJobKeyRef.current !== jobKey) {
      const currentOutsource = jobOutsourceRef.current
      lastOptionsRef.current = {delivery: undefined, revisionType: undefined}
      selectedDateRef.current = null
      setStatus('idle')
      setOutsource(false)
      setRevision(false)
      revisionRef.current = false
      setChunkQuote(null)
      setQuoteNotAvailable(false)
      setErrorQuote(false)
      setErrorOutsource(false)
      setOutsourceConfirmed(!!currentOutsource)
      setJobOutsourced(!!currentOutsource)
      setDeliveryDate(
        currentOutsource ? new Date(currentOutsource.delivery_date) : null,
      )
    }
    previousJobKeyRef.current = jobKey

    if (!enabled || !hasJob) {
      setStatus('idle')
      return cancelPendingWork
    }

    let timer
    const isCached =
      cacheRef.current &&
      !jobOutsourceRef.current &&
      !!readCache(getCacheKey(getQuoteOptionsRef.current()))
    if (debounceMs > 0 && !isCached) {
      setStatus('loading')
      timer = setTimeout(() => fetchQuoteRef.current(), debounceMs)
    } else {
      fetchQuoteRef.current()
    }

    return () => {
      clearTimeout(timer)
      cancelPendingWork()
    }
  }, [enabled, jobId, jobPassword, debounceMs, cancelPendingWork])

  const getDeliveryDateFromQuote = useCallback(
    (isRevision) => {
      if (jobOutsource != null) {
        return CommonUtils.getGMTDate(jobOutsource.delivery_date)
      } else if (chunkQuote) {
        if (isRevision && chunkQuote.get('r_delivery')) {
          return CommonUtils.getGMTDate(chunkQuote.get('r_delivery'))
        } else {
          return CommonUtils.getGMTDate(chunkQuote.get('delivery'))
        }
      }
    },
    [jobOutsource, chunkQuote],
  )

  const checkChosenDateIsAfter = useCallback(() => {
    if (outsource && selectedDateRef.current) {
      if (revision && chunkQuote.get('r_delivery')) {
        return (
          selectedDateRef.current >
          new Date(chunkQuote.get('r_delivery')).getTime()
        )
      } else {
        return (
          selectedDateRef.current >
          new Date(chunkQuote.get('delivery')).getTime()
        )
      }
    }
    return false
  }, [outsource, revision, chunkQuote])

  const toggleRevision = useCallback(() => {
    const newRevision = !revisionRef.current
    const service = newRevision ? 'premium' : 'professional'
    setRevision(newRevision)
    clearTimeout(toggleTimerRef.current)
    toggleTimerRef.current = setTimeout(() => {
      fetchQuote(selectedDateRef.current, service)
    })
  }, [fetchQuote])

  return {
    // State
    status,
    outsource,
    setOutsource,
    revision,
    chunkQuote,
    setChunkQuote,
    outsourceConfirmed,
    jobOutsourced,
    quoteNotAvailable,
    errorQuote,
    errorOutsource,
    deliveryDate,
    setDeliveryDate,
    selectedTime,
    setSelectedTime,

    // Refs (needed for form submission data)
    quoteResponseRef,
    urlOkRef,
    urlKoRef,
    confirmUrlsRef,
    dataKeyRef,
    selectedDateRef,

    // Actions
    fetchQuote,
    retry,
    toggleRevision,
    updateTimezoneRef,

    // Derived
    getDeliveryDateFromQuote,
    checkChosenDateIsAfter,
  }
}

export default useOutsourceQuote
