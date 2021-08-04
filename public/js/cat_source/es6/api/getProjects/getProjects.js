import _ from 'lodash'

import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Fetch the project list based on:
 *
 * - the given team
 * - the given page
 *
 * @param {object} param
 * @param {number} param.page
 * @param {object} param.searchFilter
 * @param {object} param.team
 * @returns {Promise<object>}
 */
export const getProjects = async ({
  searchFilter,
  team,
  page = searchFilter.currentPage,
}) => {
  const data = {
    id_team: team.id,
    page,
    filter: _.isEmpty(searchFilter.filter) ? 0 : 1,
    ...searchFilter.filter,
  }

  const formData = new FormData()

  Object.keys(data).forEach((key) => {
    formData.append(key, data[key])
  })

  const res = await fetch(`${getMatecatApiDomain()}?action=getProjects`, {
    method: 'POST',
    credentials: 'include',
    body: formData,
  })

  if (!res.ok) {
    return Promise.reject(res)
  }

  const {errors, ...resData} = await res.json()

  if (errors != null && errors.length > 0) {
    return Promise.reject(errors)
  }

  return resData
}
