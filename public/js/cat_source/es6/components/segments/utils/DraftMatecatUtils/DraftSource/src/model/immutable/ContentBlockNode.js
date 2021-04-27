/**
 * Duplicated from draft-js - not part of the public API
 *
 * "This file is a fork of ContentBlock adding support for nesting references by
 * providing links to children, parent, prevSibling, and nextSibling.
 *
 * This is unstable and not part of the public API and should not be used by
 * production systems. This file may be update/removed without notice."
 */

import findRangesImmutable from './findRangesimmutable'
import Immutable from 'immutable'
import {CharacterMetadata} from 'draft-js'
const {List, Map, OrderedSet, Record, Repeat} = Immutable
const EMPTY_SET = OrderedSet()

const defaultRecord = {
  parent: null,
  characterList: List(),
  data: Map(),
  depth: 0,
  key: '',
  text: '',
  type: 'unstyled',
  children: List(),
  prevSibling: null,
  nextSibling: null,
}

const haveEqualStyle = (charA, charB) => charA.getStyle() === charB.getStyle()

const haveEqualEntity = (charA, charB) =>
  charA.getEntity() === charB.getEntity()

const decorateCharacterList = (config) => {
  if (!config) {
    return config
  }

  const {characterList, text} = config

  if (text && !characterList) {
    config.characterList = List(Repeat(CharacterMetadata.EMPTY, text.length))
  }

  return config
}

class ContentBlockNode extends Record(defaultRecord) {
  constructor(props = defaultRecord) {
    super(decorateCharacterList(props))
  }

  getKey() {
    return this.get('key')
  }

  getType() {
    return this.get('type')
  }

  getText() {
    return this.get('text')
  }

  getCharacterList() {
    return this.get('characterList')
  }

  getLength() {
    return this.getText().length
  }

  getDepth() {
    return this.get('depth')
  }

  getData() {
    return this.get('data')
  }

  getInlineStyleAt(offset) {
    const character = this.getCharacterList().get(offset)
    return character ? character.getStyle() : EMPTY_SET
  }

  getEntityAt(offset) {
    const character = this.getCharacterList().get(offset)
    return character ? character.getEntity() : null
  }

  getChildKeys() {
    return this.get('children')
  }

  getParentKey() {
    return this.get('parent')
  }

  getPrevSiblingKey() {
    return this.get('prevSibling')
  }

  getNextSiblingKey() {
    return this.get('nextSibling')
  }

  findStyleRanges(filterFn, callback) {
    findRangesImmutable(
      this.getCharacterList(),
      haveEqualStyle,
      filterFn,
      callback,
    )
  }

  findEntityRanges(filterFn, callback) {
    findRangesImmutable(
      this.getCharacterList(),
      haveEqualEntity,
      filterFn,
      callback,
    )
  }
}

export default ContentBlockNode
