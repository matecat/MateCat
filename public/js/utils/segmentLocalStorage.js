const clearStorage = (prefix) => {
  const keys = Object.keys(localStorage)
  for (let i = 0; i < keys.length; i++) {
    if (keys[i].substring(0, prefix.length) === prefix) {
      localStorage.removeItem(keys[i])
    }
  }
}

export const getLastSegmentFromLocalStorage = () => {
  const key = 'currentSegmentId-' + config.id_job + config.password
  return localStorage.getItem(key)
}

export const setLastSegmentFromLocalStorage = (segmentId) => {
  const key = 'currentSegmentId-' + config.id_job + config.password
  try {
    localStorage.setItem(key, segmentId)
  } catch (e) {
    clearStorage('currentSegmentId')
    localStorage.setItem(key, segmentId)
  }
}
