import React, {
  createRef,
  Fragment,
  useEffect,
  useId,
  useRef,
  useState,
} from 'react'
import PropTypes from 'prop-types'
import {ANALYSIS_WORKFLOW_TYPES, UNIT_COUNT} from '../../../constants/Constants'
import CommonUtils from '../../../utils/commonUtils'
import UserStore from '../../../stores/UserStore'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../../common/Button/Button'
import Tooltip from '../../common/Tooltip'
import ChevronDown from '../../../../img/icons/ChevronDown'
import Copy from '../../../../img/icons/Copy'
import Check from '../../../../img/icons/Check'
import {formatCount} from '../utils/buckets'
import {Pending, PENDING_ALIGN} from '../shared/Pending'
import {useValueFlash, VALUE_FLASH_CLASS} from '../shared/useValueFlash'
import {DisabledHint} from '../shared/DisabledHint'
import styles from './LanguageTree.module.scss'

export const ACTIONS_DISABLED_TITLE = 'Available when the analysis is complete'
export const CUSTOM_BILLING_MESSAGE =
  'Jobs created with custom billing models cannot be outsourced to Translated'
const COPIED_DURATION = 2000

export const getLanguagePair = (job) => `${job.source} › ${job.target}`

// Jobs created with a custom billing model are never sold by Translated
export const isCustomBillingJob = (job) =>
  !job.outsourceAvailable && !!job.outsourceInfo?.custom_payable_rate

// Opens the chunk in the CAT tool; the first click of the session is tracked
export const openTranslate = (chunk) => {
  const key = `first_translate_click${config.id_project}`
  if (!sessionStorage.getItem(key)) {
    const userInfo = UserStore.getUser()
    if (userInfo) {
      CommonUtils.dispatchAnalyticsEvents({
        event: 'open_job',
        userStatus: 'loggedUser',
        userId: userInfo.user.uid,
        idProject: parseInt(config.id_project),
      })
      sessionStorage.setItem(key, 'true')
    }
  }
  window.open(chunk.urls.t, '_blank')
}

const CountCell = ({value, known, live}) => {
  const flashing = useValueFlash(value, {enabled: live && known})
  const className = [
    VALUE_FLASH_CLASS.base,
    flashing && VALUE_FLASH_CLASS.active,
  ]
    .filter(Boolean)
    .join(' ')
  return (
    <td className={className}>
      <Pending known={known} align={PENDING_ALIGN.END}>
        {formatCount(value)}
      </Pending>
    </td>
  )
}

CountCell.propTypes = {
  value: PropTypes.number,
  known: PropTypes.bool.isRequired,
  live: PropTypes.bool,
}

export const CopyLinkButton = ({label, url, disabled}) => {
  // 'copied' after a successful copy, 'fallback' when the clipboard failed
  const [state, setState] = useState(null)
  const timerRef = useRef()
  useEffect(() => () => clearTimeout(timerRef.current), [])

  const link = encodeURI(url ?? '')

  const copyLink = async () => {
    clearTimeout(timerRef.current)
    try {
      if (!navigator.clipboard?.writeText) throw new Error('No clipboard')
      await navigator.clipboard.writeText(link)
      setState('copied')
      timerRef.current = setTimeout(() => setState(null), COPIED_DURATION)
    } catch {
      setState('fallback')
    }
  }

  return (
    <span className={styles.copy}>
      <button
        type="button"
        className={styles.iconButton}
        aria-label={label}
        title={disabled ? undefined : label}
        disabled={disabled}
        onClick={copyLink}
      >
        {state === 'copied' ? <Check size={16} /> : <Copy size={16} />}
      </button>
      {/* Overlays the row: the confirmation never moves the other actions */}
      <span className={styles.copied} role="status">
        {state === 'copied' ? 'Link copied' : ''}
      </span>
      {state === 'fallback' && (
        <span className={styles.copyFallback}>
          <label>
            <span>Copy this link</span>
            <input
              type="text"
              readOnly
              value={link}
              autoFocus
              onFocus={(event) => event.target.select()}
              onBlur={() => setState(null)}
              onKeyDown={(event) => {
                if (event.key === 'Escape') setState(null)
              }}
            />
          </label>
        </span>
      )}
    </span>
  )
}

CopyLinkButton.propTypes = {
  label: PropTypes.string.isRequired,
  url: PropTypes.string,
  disabled: PropTypes.bool,
}

const BuyButton = ({job, outsourced, disabled, onBuy}) => {
  if (isCustomBillingJob(job)) {
    return (
      <Tooltip content={CUSTOM_BILLING_MESSAGE}>
        <Button
          ref={createRef()}
          mode={BUTTON_MODE.GHOST}
          size={BUTTON_SIZE.SMALL}
          disabled={true}
        >
          Buy translation
        </Button>
      </Tooltip>
    )
  }
  return (
    <Button
      mode={BUTTON_MODE.GHOST}
      size={BUTTON_SIZE.SMALL}
      tabIndex="0"
      disabled={disabled}
      onClick={onBuy}
    >
      {outsourced ? 'View order' : 'Buy translation'}
    </Button>
  )
}

BuyButton.propTypes = {
  job: PropTypes.object.isRequired,
  outsourced: PropTypes.bool,
  disabled: PropTypes.bool,
  onBuy: PropTypes.func.isRequired,
}

const RowActions = ({
  copyLabel,
  job,
  chunk,
  outsourced,
  actionsEnabled,
  onBuy,
}) => {
  const disabled = !actionsEnabled
  return (
    <div className={styles.actions}>
      <DisabledHint hint={ACTIONS_DISABLED_TITLE} active={disabled}>
        <CopyLinkButton
          label={copyLabel}
          url={chunk.urls?.t}
          disabled={disabled}
        />
      </DisabledHint>
      {config.enable_outsource && (
        <DisabledHint
          hint={ACTIONS_DISABLED_TITLE}
          active={disabled && !isCustomBillingJob(job)}
        >
          <BuyButton
            job={job}
            outsourced={outsourced}
            disabled={disabled}
            onBuy={onBuy}
          />
        </DisabledHint>
      )}
      <DisabledHint hint={ACTIONS_DISABLED_TITLE} active={disabled}>
        <Button
          type={BUTTON_TYPE.PRIMARY}
          size={BUTTON_SIZE.SMALL}
          tabIndex="0"
          disabled={disabled}
          onClick={() => openTranslate(chunk)}
        >
          Translate
        </Button>
      </DisabledHint>
    </div>
  )
}

RowActions.propTypes = {
  copyLabel: PropTypes.string.isRequired,
  job: PropTypes.object.isRequired,
  chunk: PropTypes.object.isRequired,
  outsourced: PropTypes.bool,
  actionsEnabled: PropTypes.bool,
  onBuy: PropTypes.func.isRequired,
}

const isSelected = (selection, jobId, chunkIndex = null) =>
  selection?.jobId === jobId && (selection?.chunkIndex ?? null) === chunkIndex

/**
 * One row per job, its chunks nested below it and collapsed until asked for.
 * A row is selected with its name; per-row actions are on unsplit jobs and
 * chunks only. The header sticks to the top of the scroll area, the project
 * total to its bottom.
 */
export const LanguageTree = ({
  jobs,
  workflowType,
  known,
  actionsEnabled,
  totals,
  selection,
  outsourcedChunks,
  onSelect,
  onBuy,
}) => {
  const [expanded, setExpanded] = useState(() => new Set())
  const idPrefix = useId()

  const showIndustry = workflowType === ANALYSIS_WORKFLOW_TYPES.STANDARD
  const characters = jobs[0]?.countUnit === UNIT_COUNT.CHARACTERS
  const weightedKnown = known.weighted || known.liveWeighted
  const live = known.liveWeighted
  const isOutsourced = (job, chunk) =>
    !!outsourcedChunks?.has(`${job.id}-${chunk.password}`)

  const toggle = (jobId) =>
    setExpanded((current) => {
      const next = new Set(current)
      if (next.has(jobId)) next.delete(jobId)
      else next.add(jobId)
      return next
    })

  // The whole row selects with the mouse; the name button stays the keyboard
  // target, and clicks on the row's own controls keep their own action
  const selectFromRow = (target) => (event) => {
    if (event.target.closest('button, a, input')) return
    onSelect(target)
  }

  const renderCounts = (item) => (
    <>
      <CountCell value={item.totals.raw} known={known.raw} />
      {showIndustry && (
        <CountCell
          value={item.totals.industry}
          known={weightedKnown}
          live={live}
        />
      )}
      <CountCell
        value={item.totals.equivalent}
        known={weightedKnown}
        live={live}
      />
    </>
  )

  return (
    <section className={styles.card} aria-label="Language combinations">
      <table className={styles.table}>
        {/* Fixed widths: no cell content can widen the table past the card */}
        <colgroup>
          <col />
          <col className={styles.countColumn} />
          {showIndustry && <col className={styles.countColumn} />}
          <col className={styles.countColumn} />
          <col className={styles.actionsColumn} />
        </colgroup>
        <thead className={styles.stickyHead}>
          <tr>
            <th scope="col">Language combination</th>
            <th scope="col">
              {characters ? 'Total character count' : 'Total word count'}
            </th>
            {showIndustry && <th scope="col">Industry weighted</th>}
            <th scope="col">Matecat weighted</th>
            <th scope="col">
              <span className={styles.hidden}>Actions</span>
            </th>
          </tr>
        </thead>
        <tbody>
          {jobs.map((job) => {
            const pair = getLanguagePair(job)
            const isOpen = expanded.has(job.id)
            const chunkRowId = (chunk) =>
              `${idPrefix}-job-${job.id}-chunk-${chunk.index}`
            const jobSelected = isSelected(selection, job.id)

            return (
              <Fragment key={job.id}>
                <tr
                  className={[styles.jobRow, jobSelected && styles.selected]
                    .filter(Boolean)
                    .join(' ')}
                  onClick={selectFromRow({jobId: job.id, chunkIndex: null})}
                >
                  <th scope="row">
                    <div className={styles.label}>
                      {job.isSplit ? (
                        <button
                          type="button"
                          className={styles.chevron}
                          aria-expanded={isOpen}
                          aria-controls={job.chunks.map(chunkRowId).join(' ')}
                          aria-label={`Chunks of ${pair}`}
                          onClick={() => toggle(job.id)}
                        >
                          <ChevronDown size={14} />
                        </button>
                      ) : (
                        <span className={styles.chevronSpacer} />
                      )}
                      <button
                        type="button"
                        className={styles.select}
                        aria-current={jobSelected ? 'true' : undefined}
                        onClick={() =>
                          onSelect({jobId: job.id, chunkIndex: null})
                        }
                      >
                        <span
                          className={styles.pair}
                          title={`${job.sourceName} › ${job.targetName}`}
                        >
                          {pair}
                        </span>
                        <span className={styles.meta}>
                          {`ID ${job.id}`}
                          {job.isSplit &&
                            ` · split into ${job.chunkCount} chunks`}
                        </span>
                      </button>
                    </div>
                  </th>
                  {renderCounts(job)}
                  <td>
                    {!job.isSplit && job.chunks[0] && (
                      <RowActions
                        copyLabel={`Copy the ${job.target} link`}
                        job={job}
                        chunk={job.chunks[0]}
                        outsourced={isOutsourced(job, job.chunks[0])}
                        actionsEnabled={actionsEnabled}
                        onBuy={() => onBuy(job, job.chunks[0])}
                      />
                    )}
                  </td>
                </tr>
                {job.isSplit &&
                  job.chunks.map((chunk) => {
                    const chunkSelected = isSelected(
                      selection,
                      job.id,
                      chunk.index,
                    )
                    return (
                      <tr
                        key={chunk.index}
                        id={chunkRowId(chunk)}
                        hidden={!isOpen}
                        className={[
                          styles.chunkRow,
                          chunkSelected && styles.selected,
                        ]
                          .filter(Boolean)
                          .join(' ')}
                        onClick={selectFromRow({
                          jobId: job.id,
                          chunkIndex: chunk.index,
                        })}
                      >
                        <th scope="row">
                          <div className={styles.label}>
                            <span className={styles.chevronSpacer} />
                            <button
                              type="button"
                              className={styles.select}
                              aria-current={chunkSelected ? 'true' : undefined}
                              aria-label={`${pair}, chunk ${chunk.index}`}
                              onClick={() =>
                                onSelect({
                                  jobId: job.id,
                                  chunkIndex: chunk.index,
                                })
                              }
                            >
                              <span className={styles.pair}>
                                {`Chunk ${chunk.index}`}
                              </span>
                            </button>
                          </div>
                        </th>
                        {renderCounts(chunk)}
                        <td>
                          <RowActions
                            copyLabel={`Copy the chunk ${chunk.index} link`}
                            job={job}
                            chunk={chunk}
                            outsourced={isOutsourced(job, chunk)}
                            actionsEnabled={actionsEnabled}
                            onBuy={() => onBuy(job, chunk)}
                          />
                        </td>
                      </tr>
                    )
                  })}
              </Fragment>
            )
          })}
        </tbody>
        <tfoot className={styles.stickyTotal}>
          <tr>
            <th scope="row">Project total</th>
            {renderCounts({totals})}
            <td />
          </tr>
        </tfoot>
      </table>
    </section>
  )
}

const totalsShape = PropTypes.shape({
  raw: PropTypes.number,
  industry: PropTypes.number,
  equivalent: PropTypes.number,
})

LanguageTree.propTypes = {
  jobs: PropTypes.arrayOf(
    PropTypes.shape({
      id: PropTypes.oneOfType([PropTypes.number, PropTypes.string]).isRequired,
      source: PropTypes.string,
      target: PropTypes.string,
      sourceName: PropTypes.string,
      targetName: PropTypes.string,
      countUnit: PropTypes.string,
      isSplit: PropTypes.bool,
      chunkCount: PropTypes.number,
      outsourceAvailable: PropTypes.bool,
      outsourceInfo: PropTypes.object,
      totals: totalsShape,
      chunks: PropTypes.arrayOf(
        PropTypes.shape({
          index: PropTypes.number.isRequired,
          password: PropTypes.string,
          urls: PropTypes.object,
          totals: totalsShape,
        }),
      ).isRequired,
    }),
  ).isRequired,
  workflowType: PropTypes.oneOf(Object.values(ANALYSIS_WORKFLOW_TYPES)),
  known: PropTypes.shape({
    raw: PropTypes.bool,
    weighted: PropTypes.bool,
    liveWeighted: PropTypes.bool,
  }).isRequired,
  actionsEnabled: PropTypes.bool,
  totals: totalsShape.isRequired,
  // {jobId, chunkIndex}; chunkIndex null selects the whole job
  selection: PropTypes.shape({
    jobId: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
    chunkIndex: PropTypes.number,
  }),
  // "<job id>-<chunk password>" of the chunks already ordered
  outsourcedChunks: PropTypes.instanceOf(Set),
  onSelect: PropTypes.func.isRequired,
  onBuy: PropTypes.func.isRequired,
}
