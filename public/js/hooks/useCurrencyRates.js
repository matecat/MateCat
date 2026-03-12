import {useState, useEffect, useCallback, useMemo} from 'react'
import Cookies from 'js-cookie'
import {isUndefined} from 'lodash'
import {isNull} from 'lodash/lang'
import $ from 'jquery'

import {getChangeRates} from '../api/getChangeRates'
import {currencies} from '../components/outsource/outsourceConstants'

/**
 * Hook that manages currency exchange rates and currency selection.
 */
const useCurrencyRates = () => {
  const initialChangeRates = useMemo(() => {
    const stored = Cookies.get('matecat_changeRates')
    return !isUndefined(stored) && !isNull(stored) ? $.parseJSON(stored) : {}
  }, [])

  const [changeRates, setChangeRates] = useState(initialChangeRates)

  const getCurrentCurrency = useCallback(() => {
    const currency = Cookies.get('matecat_currency')
    if (!isUndefined(currency) && !isNull(currency) && currency !== 'null') {
      return currency
    }
    Cookies.set('matecat_currency', 'EUR', {secure: true})
    return 'EUR'
  }, [])

  const getCurrencyPrice = useCallback(
    (price) => {
      const current = getCurrentCurrency()
      if (changeRates) {
        return parseFloat(
          (price * changeRates[current]) / changeRates['EUR'],
        ).toFixed(2)
      }
      return price.toString()
    },
    [changeRates, getCurrentCurrency],
  )

  const getPriceCurrencySymbol = useCallback((chunkQuote) => {
    if (chunkQuote) {
      const currency = chunkQuote.get('currency')
      return currencies[currency]?.symbol ?? ''
    }
    return ''
  }, [])

  const onCurrencyChange = useCallback((value, chunkQuote, setChunkQuote) => {
    Cookies.set('matecat_currency', value, {secure: true})
    setChunkQuote(chunkQuote.set('currency', value))
  }, [])

  // Fetch exchange rates on mount if not cached
  useEffect(() => {
    const stored = Cookies.get('matecat_changeRates')
    if (isUndefined(stored) || isNull(stored) || stored === 'null') {
      getChangeRates().then((response) => {
        const rates = $.parseJSON(response.data)
        if (!isUndefined(rates)) {
          setChangeRates(rates)
          Cookies.set('matecat_changeRates', response.data, {
            expires: 1,
            secure: true,
          })
        }
      })
    }
  }, [])

  return {
    changeRates,
    getCurrentCurrency,
    getCurrencyPrice,
    getPriceCurrencySymbol,
    onCurrencyChange,
  }
}

export default useCurrencyRates
