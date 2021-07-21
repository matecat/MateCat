/**
 * Fetch the project list based on:
 *
 * - the given team
 * - the given page
 *
 * @param {number | undefined} param.page
 * @param {object} param.searchFilter
 * @param {object} param.team
 * @returns {Promise<object>}
 */
export const getProjects = async ({searchFilter, team, page: _page}) => {
  const page = _page ?? searchFilter.currentPage
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
    url: `${APP.getRandomUrl()}?action=getProjects`,
  })
}
