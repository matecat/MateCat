/**
 * Match typing sequence
 *
 * @param {Array} sequence
 * @param {number} [delay=1000]
 * @returns {Object} {get, reset}
 */
export default (sequence, delay = 1000) => {
  if (!sequence?.length) throw new Error('sequence prop is not defined.')
  const typingCollection = []
  let tmOut = undefined

  return {
    get: (keyIdentifier) => {
      if (tmOut) clearTimeout(tmOut)
      if (!keyIdentifier) throw new Error('keyIdentifier prop is not defined.')
      typingCollection.push(keyIdentifier)
      const hasBeenMatched = sequence.every((key, index) =>
        Array.isArray(key)
          ? !!key.find((nestedKey) => nestedKey === typingCollection[index])
          : key === typingCollection[index],
      )
      if (!hasBeenMatched) {
        if (typingCollection.length >= sequence.length) {
          typingCollection.length = 0
        } else {
          tmOut = setTimeout(() => (typingCollection.length = 0), delay)
        }
      }
      if (hasBeenMatched) typingCollection.length = 0

      return hasBeenMatched
    },
    reset: () => {
      if (tmOut) clearTimeout(tmOut)
      typingCollection.length = 0
    },
  }
}
