import {useState, useEffect, useRef, useCallback} from 'react'
import {fromJS} from 'immutable'
import Cookies from 'js-cookie'
import {isNull} from 'lodash/lang'

import {getOutsourceQuote} from '../api/getOutsourceQuote'
import CommonUtils from '../utils/commonUtils'

/**
 * Hook that manages the outsource quote lifecycle:
 * fetching, state, revision toggle, and derived values.
 */
const useOutsourceQuote = ({job, project, getCurrentCurrency}) => {
  const [outsource, setOutsource] = useState(false)
  const [revision, setRevision] = useState(false)
  const [chunkQuote, setChunkQuote] = useState(null)
  const [outsourceConfirmed, setOutsourceConfirmed] = useState(
    !!job.get('outsource'),
  )
  const [jobOutsourced, setJobOutsourced] = useState(!!job.get('outsource'))
  const [quoteNotAvailable, setQuoteNotAvailable] = useState(false)
  const [errorQuote, setErrorQuote] = useState(false)
  const [errorOutsource, setErrorOutsource] = useState(false)
  const [deliveryDate, setDeliveryDate] = useState(() =>
    job && job.get('outsource')
      ? new Date(job.get('outsource').get('delivery_date'))
      : null,
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

  const fetchQuote = useCallback(
    (delivery, revisionType) => {
      let typeOfService = revisionRef.current ? 'premium' : 'professional'
      if (revisionType) typeOfService = revisionType
      const fixedDelivery = delivery || ''
      const timezoneToShow = timezoneRef.current
      const currency = getCurrentCurrency()

      getOutsourceQuote(
        project.get('id'),
        project.get('password'),
        job.get('id'),
        job.get('password'),
        fixedDelivery,
        typeOfService,
        timezoneToShow,
        currency,
      )
        .then((quoteData) => {
          if (quoteData.data && quoteData.data.length > 0) {
            if (
              quoteData.data[0][0].quote_available !== '1' &&
              quoteData.data[0][0].outsourced !== '1'
            ) {
              setOutsource(true)
              setQuoteNotAvailable(true)
              return
            } else if (
              quoteData.data[0][0].quote_result !== '1' &&
              quoteData.data[0][0].outsourced !== '1'
            ) {
              setOutsource(true)
              setErrorQuote(true)
              return
            }

            quoteResponseRef.current = quoteData.data[0]
            const chunk = fromJS(quoteData.data[0][0])

            urlOkRef.current = quoteData.return_url.url_ok
            urlKoRef.current = quoteData.return_url.url_ko
            confirmUrlsRef.current = quoteData.return_url.confirm_urls
            dataKeyRef.current = chunk.get('id')

            const isRevision = chunk.get('typeOfService') === 'premium'

            setOutsource(true)
            setQuoteNotAvailable(false)
            setErrorQuote(false)
            setChunkQuote(chunk)
            setRevision(isRevision)
            setJobOutsourced(chunk.get('outsourced') === '1')
            setOutsourceConfirmed(chunk.get('outsourced') === '1')
            setDeliveryDate(new Date(chunk.get('delivery')))

            setTimeout(() => {
              const deliveryStr =
                isRevision && chunk.get('r_delivery')
                  ? chunk.get('r_delivery')
                  : chunk.get('delivery')
              const date = CommonUtils.getGMTDate(deliveryStr)
              if (date?.time2) {
                setSelectedTime(date.time2.split(':')[0])
              }
            })
          } else {
            setOutsource(false)
            setErrorQuote(true)
            setErrorOutsource(true)
          }
        })
        .catch(() => {
          setOutsource(false)
          setErrorQuote(true)
          setErrorOutsource(true)
        })
    },
    [project, job, getCurrentCurrency],
  )

  // Fetch quote on mount
  useEffect(() => {
    if (config.enable_outsource) {
      fetchQuote()
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const getDeliveryDateFromQuote = useCallback(
    (isRevision) => {
      if (!isNull(job.get('outsource'))) {
        return CommonUtils.getGMTDate(job.get('outsource').get('delivery_date'))
      } else if (chunkQuote) {
        if (isRevision && chunkQuote.get('r_delivery')) {
          return CommonUtils.getGMTDate(chunkQuote.get('r_delivery'))
        } else {
          return CommonUtils.getGMTDate(chunkQuote.get('delivery'))
        }
      }
    },
    [job, chunkQuote],
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
    setTimeout(() => {
      fetchQuote(selectedDateRef.current, service)
    })
  }, [fetchQuote])

  const confirmOutsource = useCallback(() => setOutsourceConfirmed(true), [])
  const goBack = useCallback(() => setOutsourceConfirmed(false), [])

  return {
    // State
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

    // Refs (needed for form submission)
    quoteResponseRef,
    urlOkRef,
    urlKoRef,
    confirmUrlsRef,
    dataKeyRef,
    selectedDateRef,

    // Actions
    fetchQuote,
    toggleRevision,
    confirmOutsource,
    goBack,
    updateTimezoneRef,

    // Derived
    getDeliveryDateFromQuote,
    checkChosenDateIsAfter,
  }
}

export default useOutsourceQuote
