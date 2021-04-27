/**
 * Duplicated from draft-js - not part of the public API
 */

import Immutable from 'immutable'
import {genKey} from 'draft-js'
import ContentBlockNode from './src/model/immutable/ContentBlockNode'

const {OrderedMap} = Immutable

const randomizeContentBlockNodeKeys = (blockMap) => {
  const newKeysRef = {}

  // we keep track of root blocks in order to update subsequent sibling links
  let lastRootBlock

  return OrderedMap(
    blockMap
      .withMutations((blockMapState) => {
        blockMapState.forEach((block, index) => {
          const oldKey = block.getKey()
          const nextKey = block.getNextSiblingKey()
          const prevKey = block.getPrevSiblingKey()
          const childrenKeys = block.getChildKeys()
          const parentKey = block.getParentKey()

          // new key that we will use to build linking
          const key = genKey()

          // we will add it here to re-use it later
          newKeysRef[oldKey] = key

          if (nextKey) {
            const nextBlock = blockMapState.get(nextKey)
            if (nextBlock) {
              blockMapState.setIn([nextKey, 'prevSibling'], key)
            } else {
              // this can happen when generating random keys for fragments
              blockMapState.setIn([oldKey, 'nextSibling'], null)
            }
          }

          if (prevKey) {
            const prevBlock = blockMapState.get(prevKey)
            if (prevBlock) {
              blockMapState.setIn([prevKey, 'nextSibling'], key)
            } else {
              // this can happen when generating random keys for fragments
              blockMapState.setIn([oldKey, 'prevSibling'], null)
            }
          }

          if (parentKey && blockMapState.get(parentKey)) {
            const parentBlock = blockMapState.get(parentKey)
            const parentChildrenList = parentBlock.getChildKeys()
            blockMapState.setIn(
              [parentKey, 'children'],
              parentChildrenList.set(
                parentChildrenList.indexOf(block.getKey()),
                key,
              ),
            )
          } else {
            // blocks will then be treated as root block nodes
            blockMapState.setIn([oldKey, 'parent'], null)

            if (lastRootBlock) {
              blockMapState.setIn([lastRootBlock.getKey(), 'nextSibling'], key)
              blockMapState.setIn(
                [oldKey, 'prevSibling'],
                newKeysRef[lastRootBlock.getKey()],
              )
            }

            lastRootBlock = blockMapState.get(oldKey)
          }

          childrenKeys.forEach((childKey) => {
            const childBlock = blockMapState.get(childKey)
            if (childBlock) {
              blockMapState.setIn([childKey, 'parent'], key)
            } else {
              blockMapState.setIn(
                [oldKey, 'children'],
                block.getChildKeys().filter((child) => child !== childKey),
              )
            }
          })
        })
      })
      .toArray()
      .map((block) => [
        newKeysRef[block.getKey()],
        block.set('key', newKeysRef[block.getKey()]),
      ]),
  )
}

const randomizeContentBlockKeys = (blockMap) => {
  return OrderedMap(
    blockMap.toArray().map((block) => {
      const key = genKey()
      return [key, block.set('key', key)]
    }),
  )
}

const randomizeBlockMapKeys = (blockMap) => {
  const isTreeBasedBlockMap = blockMap.first() instanceof ContentBlockNode

  if (!isTreeBasedBlockMap) {
    return randomizeContentBlockKeys(blockMap)
  }

  return randomizeContentBlockNodeKeys(blockMap)
}

export default randomizeBlockMapKeys
