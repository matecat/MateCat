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
  /**
   * Filters the matches shown in the tab. Core keeps every match; a plugin
   * returns something falsy to drop one. This was a prototype method on
   * SegmentFooterTabMatches, documented there as "used by the plugins to
   * override matches", which converting that component to a function would
   * have made unreachable.
   *
   * @param item
   * @returns {*}
   */
  processMatchCallback(item) {
    return item
  },

  // eslint-disable-next-line no-unused-vars
  getMatchInfoMetadata({match, segment}) {
    return ''
  },
}

export default matchInfo
