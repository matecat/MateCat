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
