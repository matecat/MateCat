import {useCallback, useState} from 'react'
import CommonUtils from '../../../utils/commonUtils'
import {CONTACT_US_URL} from '../OutsourceOfferCard'

/**
 * What "Buy translation" does on the analysis pages: a job Translated cannot
 * sell goes to the contact page; any other job or chunk opens the order
 * drawer, and only then is the outsource request tracked. A whole split job
 * (no chunk) opens the drawer on its chunk picker.
 *
 * @param {object} params
 * @param {function} [params.onOpen] called with (job, chunk) when the drawer opens
 * @returns {{order: object|null, openBuy: function, pickChunk: function, closeBuy: function}}
 */
export const useBuyTranslation = ({onOpen} = {}) => {
  const [order, setOrder] = useState(null)

  const closeBuy = useCallback(() => setOrder(null), [])

  const openBuy = useCallback(
    (job, chunk = null) => {
      if (!job.outsourceAvailable) {
        window.open(CONTACT_US_URL, '_blank')
        return
      }
      CommonUtils.dispatchAnalyticsEvents({event: 'outsource_request'})
      onOpen?.(job, chunk)
      setOrder({job, chunk})
    },
    [onOpen],
  )

  // From the chunk picker of a whole split job
  const pickChunk = useCallback(
    (chunk) => {
      setOrder((current) => current && {...current, chunk})
      if (order) onOpen?.(order.job, chunk)
    },
    [onOpen, order],
  )

  return {order, openBuy, pickChunk, closeBuy}
}
