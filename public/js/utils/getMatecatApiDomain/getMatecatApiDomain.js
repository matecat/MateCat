/**
 * utils that generates the domain to use for fetching MateCat APIs
 *
 * @todo move config stuff to an envvars system
 * @returns {string}
 */
export const getMatecatApiDomain = () => {
  if (config.enableMultiDomainApi) {
    const randomInt = Math.floor(Math.random() * config.ajaxDomainsNumber)

    return `//${randomInt}.ajax.${location.host}/`
  }

  return config.basepath
}
