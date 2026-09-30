const ZWSP = String.fromCharCode(parseInt('200B', 16))

/**
 * The offsets that select a clicked entity together with the zero-width spaces
 * placed around it.
 *
 * Every tag entity is written with a zero-width space on each side, so
 * selecting the entity's own range alone would leave a sentinel outside the
 * selection and a later edit would strand it. Each side is checked
 * independently, because an entity at the very start or end of a block, or one
 * sitting against another, does not always have both.
 *
 * @param blockText the text of the block the entity sits in
 * @param start     the entity's first offset
 * @param end       the entity's last offset
 * @returns {{anchorOffset: number, focusOffset: number}}
 */
export default function selectionAroundEntity(blockText, start, end) {
  const precededByZwsp = blockText.slice(start - 1, start) === ZWSP
  const followedByZwsp = blockText.slice(end, end + 1) === ZWSP

  return {
    anchorOffset: start - (precededByZwsp ? 1 : 0),
    focusOffset: end + (followedByZwsp ? 1 : 0),
  }
}
