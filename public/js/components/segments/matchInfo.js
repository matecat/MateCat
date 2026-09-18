/**
 * Extra metadata shown next to a translation match in the segment footer.
 *
 * Core renders nothing. Plugins assign over this member
 * (`matchInfo.getMatchInfoMetadata = ...`) instead of patching the component
 * prototype, which stops working once the host becomes a function component.
 *
 * @param {{match: object, segment: object}} params
 * @returns {import('react').ReactNode}
 */
const matchInfo = {
  // eslint-disable-next-line no-unused-vars
  getMatchInfoMetadata({match, segment}) {
    return ''
  },
}

export default matchInfo
