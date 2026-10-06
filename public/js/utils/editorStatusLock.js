/**
 * One select value stands for an editor status plus its lock flag:
 * `APPROVED` is the status alone, `APPROVED_locked` the same status locked.
 * The status keeps its own case, so the module serves both the uppercase
 * project pre-translate statuses and the lowercase XLIFF rule editor values.
 */
const LOCKED_SUFFIX = '_locked'

// A segment left to translate can not be locked
const UNLOCKABLE_STATUSES = ['new', 'draft']

export const isLockableStatus = (status) =>
  !UNLOCKABLE_STATUSES.includes(status.toLowerCase())

export const toStatusLockId = ({status, lock}) =>
  lock && isLockableStatus(status) ? `${status}${LOCKED_SUFFIX}` : status

export const fromStatusLockId = (id) =>
  id.endsWith(LOCKED_SUFFIX)
    ? {status: id.slice(0, -LOCKED_SUFFIX.length), lock: true}
    : {status: id, lock: false}

/**
 * Select options for the given statuses, each followed by its locked variant
 * when the status can be locked.
 *
 * @param {string[]} statuses
 * @param {function(string): string} getName label of a status
 * @returns {{id: string, name: string}[]}
 */
export const getStatusLockOptions = (statuses, getName) =>
  statuses.flatMap((status) => [
    {id: toStatusLockId({status, lock: false}), name: getName(status)},
    ...(isLockableStatus(status)
      ? [
          {
            id: toStatusLockId({status, lock: true}),
            name: `${getName(status)} (locked)`,
          },
        ]
      : []),
  ])

export const PRETRANSLATE_STATUSES = ['TRANSLATED', 'APPROVED', 'APPROVED2']

const PRETRANSLATE_STATUS_NAMES = {
  TRANSLATED: 'Translated',
  APPROVED: 'Approved',
  APPROVED2: 'Approved 2',
}

export const PRETRANSLATE_STATUS_OPTIONS = getStatusLockOptions(
  PRETRANSLATE_STATUSES,
  (status) => PRETRANSLATE_STATUS_NAMES[status],
)

/**
 * The flat create-project params for the template `pretranslate` object.
 *
 * @param {{match_101: {enabled: boolean, status: string, lock: boolean}, match_100: {enabled: boolean, status: string, lock: boolean}}} pretranslate
 * @returns {Object}
 */
export const getPretranslateParams = ({match_101, match_100}) => ({
  pretranslate_101: match_101.enabled ? 1 : 0,
  pretranslate_101_status: match_101.status,
  pretranslate_101_lock: match_101.lock ? 1 : 0,
  pretranslate_100: match_100.enabled ? 1 : 0,
  pretranslate_100_status: match_100.status,
  pretranslate_100_lock: match_100.lock ? 1 : 0,
})
