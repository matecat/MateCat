import {useCallback, useState} from 'react'
import CommonUtils from '../../../utils/commonUtils'
import {CONTACT_US_URL} from '../OutsourceOfferCard'

/**
 * What "Buy translation" does on the analysis pages: a job Translated cannot
 * sell goes to the contact page; any other job or chunk opens the order
 * drawer, and only then is the outsource request tracked.
 *
 * @param {object} params
 * @param {object} params.project plain project, as returned by the project API
 * @param {function} [params.onOpen] called with (job, chunk) when the drawer opens
 * @returns {{order: object|null, openBuy: function, closeBuy: function}}
 */
export const useBuyTranslation = ({project, onOpen}) => {
  const [order, setOrder] = useState(null)

  const closeBuy = useCallback(() => setOrder(null), [])

  const openBuy = useCallback(
    (job, chunk) => {
      if (!job.outsourceAvailable) {
        window.open(CONTACT_US_URL, '_blank')
        return
      }
      const projectJob = (project.jobs ?? []).find(
        (current) =>
          String(current.id) === String(job.id) &&
          current.password === chunk.password,
      ) ?? {id: job.id, password: chunk.password}
      CommonUtils.dispatchAnalyticsEvents({event: 'outsource_request'})
      onOpen?.(job, chunk)
      setOrder({job, chunk, projectJob})
    },
    [project, onOpen],
  )

  return {order, openBuy, closeBuy}
}
