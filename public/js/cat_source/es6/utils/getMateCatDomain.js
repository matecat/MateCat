export const getMateCatDomain = ({
  enableMultiDomainApi,
  basepath,
  ajaxDomainsNumber,
}) => {
  if (enableMultiDomainApi) {
    const randomNum = Math.floor(Math.random() * ajaxDomainsNumber)

    return `//${randomNum}.ajax.${location.host}/`
  }

  return basepath
}
