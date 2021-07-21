export const getMatecatApiDomain = () => {
  if (config.enableMultiDomainApi) {
    const randomInt = Math.floor(Math.random() * config.ajaxDomainsNumber)

    return `//${randomInt}.ajax.${location.host}/`
  }

  return config.basepath
}
