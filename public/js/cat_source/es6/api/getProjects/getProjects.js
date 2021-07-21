import _ from 'lodash'

import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Fetch the project list based on:
 *
 * - the given team
 * - the given page
 *
 * @param {number | undefined} param.page
 * @param {object} param.searchFilter
 * @param {object} param.team
 * @returns {JQuery.jqXHR<object>}
 */
export const getProjects = ({
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

  console.log({searchFilter, team, page})

  return $.ajax({
    data,
    type: 'POST',
    xhrFields: {withCredentials: true},
    url: `${getMatecatApiDomain()}?action=getProjects`,
  })
}
