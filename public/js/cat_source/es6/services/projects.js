import requestModule from './requestModule'

/**
 * Request projects list
 * @param {Object} team
 * @param {Object} searchFilter
 * @param {Number} page
 * @param {String} randomUrl='/'
 * @returns {Object}
 */
const getProjects = async (team, searchFilter, page, randomUrl = '/') => {
  const data = {
    id_team: team.id,
    page: !page ? searchFilter.currentPage : page,
    filter: Object.keys(searchFilter.filter).length > 0 ? 1 : 0,
    ...searchFilter.filter,
  }

  const formData = new FormData()
  for (const key in data) formData.append(key, data[key])

  const resp = await requestModule(`${randomUrl}?action=getProjects`, {
    method: 'POST',
    credentials: 'include',
    body: formData,
  })()
  return resp
}

export {getProjects}
