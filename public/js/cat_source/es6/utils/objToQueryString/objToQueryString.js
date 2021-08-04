export const objToQueryString = (obj) => {
  const keyValuePairs = []
  for (const key in obj) {
    if (obj[key] && typeof obj[key] === 'object') {
      for (const subKey in obj[key]) {
        if (obj[key][subKey]) {
          keyValuePairs.push(
            encodeURIComponent(`${key}[${subKey}]`) +
              '=' +
              encodeURIComponent(obj[key][subKey]),
          )
        }
      }
    } else if (obj[key]) {
      keyValuePairs.push(
        encodeURIComponent(key) + '=' + encodeURIComponent(obj[key]),
      )
    }
  }
  return keyValuePairs.join('&')
}
