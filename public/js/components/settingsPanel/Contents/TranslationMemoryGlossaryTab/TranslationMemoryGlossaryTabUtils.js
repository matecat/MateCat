import {omit} from 'lodash'

export const SPECIAL_ROWS_ID = {
  defaultTranslationMemory: 'mmSharedKey',
  addSharedResource: 'addSharedResource',
  newResource: 'newResource',
}

export const isOwnerOfKey = (key) => !/[*]/g.test(key)

export const orderTmKeys = (tmKeys, keysOrdered) => {
  const order = (acc, cur) => {
    const copyAcc = [...acc]
    const index = keysOrdered.findIndex((key) => key === cur.key)

    if (index >= 0) {
      const previousItem = copyAcc[index]
      copyAcc[index] = cur
      if (previousItem) copyAcc.push(previousItem)
    } else {
      copyAcc.push(cur)
    }
    return copyAcc
  }
  return Array.isArray(keysOrdered)
    ? tmKeys.reduce(order, []).filter((row) => row)
    : tmKeys
}

// The editor's key rows: the job keys first, then the user's keyring keys the job does not use.
// A job key the owner chose is marked for everyone but the owner, who alone can change how the job uses it
export const mergeJobAndUserTmKeys = ({
  jobKeys = [],
  userKeys = [],
  ownerIsMe = false,
}) =>
  [
    ...jobKeys.map((key) => ({
      ...key,
      isJobOwnerKey: Boolean(key.owner) && !ownerIsMe,
    })),
    ...userKeys,
  ]
    .reduce(
      (acc, cur) =>
        !acc.some(({key}) => key === cur.key) ? [...acc, cur] : acc,
      [],
    )
    .map((key) => ({
      ...key,
      id: key.key,
      isActive: Boolean(key.r || key.w),
      isLocked: !key.owner,
    }))

export const getTmDataStructureToSendServer = ({tmKeys = [], keysOrdered}) => {
  const mine = tmKeys
    .filter(({key, isActive}) => isOwnerOfKey(key) && isActive)
    .map(({tm, glos, key, name, r, w, penalty}) => ({
      tm,
      glos,
      key,
      name,
      r,
      w,
      penalty,
    }))

  return JSON.stringify({
    ownergroup: [],
    mine: orderTmKeys(mine, keysOrdered),
    anonymous: [],
  })
}

// The keys of the selected template as saved, before any unsaved change
export const getSavedTemplateTm = (projectTemplates = []) =>
  projectTemplates.find(
    ({isSelected, isTemporary}) => isSelected && !isTemporary,
  )?.tm ?? []

// A key as the template stores it, without the row-only fields. A zero penalty
// is left out when the saved entry has none, so undoing a change gives back the
// saved entry and the template is no longer shown as modified
export const toTemplateTmEntry = (tmKey, savedTm = []) => {
  const entry = omit(tmKey, ['id', 'isActive', 'isJobOwnerKey'])
  const saved = savedTm.find(({key}) => key === entry.key)

  return entry.penalty || !saved || 'penalty' in saved
    ? entry
    : omit(entry, ['penalty'])
}
