import React, {Component, createRef} from 'react'
import {debounce, find} from 'lodash'

import TooltipInfo from '../TooltipInfo/TooltipInfo.component'
import {tagSignatures, getTooltipTag} from '../utils/DraftMatecatUtils/tagModel'
import SegmentStore from '../../../stores/SegmentStore'
import SegmentConstants from '../../../constants/SegmentConstants'
import EditAreaConstants from '../../../constants/EditAreaConstants'
import SegmentActions from '../../../actions/SegmentActions'
import SearchUtils from '../../header/cattol/search/searchUtils'

class TagEntity extends Component {
  constructor(props) {
    super(props)

    const {entityKey, contentState} = this.props
    const {
      data: {name: entityName},
    } = contentState.getEntity(entityKey)

    this.state = {
      showTooltip: false,
      tagStyle: this.selectCorrectStyle(),
      tagWarningStyle: '',
      tooltipAvailable: getTooltipTag().includes(entityName),
      shouldTooltipOnHover: false,
      clicked: false,
      focused: false,
      searchParams: this.props.getSearchParams(),
      entityKey: this.props.entityKey,
    }
    this.updateTagStyleDebounced = debounce(this.updateTagStyle, 500)
    this.updateTagWarningStyleDebounced = debounce(
      this.updateTagWarningStyle,
      500,
    )

    this.focusedState = createRef({})
    this.focusedState.current = {}
  }

  tooltipToggle = (show = false) => {
    // this will trigger a rerender in the main Editor Component
    this.setState({showTooltip: show})
  }

  markSearch = (text, searchParams) => {
    let {
      active,
      currentActive,
      textToReplace,
      params,
      occurrences,
      currentInSearchIndex,
    } = searchParams
    let currentOccurrence = find(
      occurrences,
      (occ) => occ.searchProgressiveIndex === currentInSearchIndex,
    )
    let isCurrent =
      currentOccurrence &&
      currentOccurrence.matchPosition >= this.props.start &&
      currentOccurrence.matchPosition < this.props.end

    if (active && isCurrent)
      SegmentActions.setIsCurrentSearchOccurrenceTag(true)

    if (active) {
      let regex = SearchUtils.getSearchRegExp(
        textToReplace,
        params.ingnoreCase,
        params.exactMatch,
      )
      let parts = text.split(regex)
      for (let i = 1; i < parts.length; i += 2) {
        let color =
          currentActive && isCurrent ? 'rgb(255 210 14)' : 'rgb(255 255 0)'
        parts[i] = (
          <span key={i} style={{backgroundColor: color}}>
            {parts[i]}
          </span>
        )
      }
      return parts
    }
    return text
  }

  addSearchParams = (sid) => {
    const {getSearchParams, isTarget} = this.props
    if (sid !== this.props.sid) return
    let searchParams = getSearchParams()
    if (
      searchParams.active &&
      ((searchParams.isTarget && isTarget) ||
        (!searchParams.isTarget && !isTarget))
    ) {
      this.setState({
        searchParams,
      })
    }
  }

  updateSearchParams = (sid, currentInSearchIndex) => {
    const {getSearchParams} = this.props
    if (
      sid !== this.props.sid ||
      (sid === this.props.sid && !this.state.searchParams.active)
    )
      return
    let searchParamsNew = getSearchParams()
    searchParamsNew.currentInSearchIndex = currentInSearchIndex
    this.setState({
      searchParams: searchParamsNew,
    })
  }

  removeSearchParams = () => {
    if (this.state.searchParams.active) {
      const {getSearchParams} = this.props
      let searchParams = getSearchParams()
      this.setState({
        searchParams,
      })
    }
  }

  componentDidMount() {
    SegmentStore.addListener(
      SegmentConstants.SET_SEGMENT_WARNINGS,
      this.updateTagWarningStyleDebounced,
    )
    SegmentStore.addListener(
      SegmentConstants.HIGHLIGHT_TAGS,
      this.highlightTags,
    )
    SegmentStore.addListener(
      EditAreaConstants.EDIT_AREA_CHANGED,
      this.updateTagStyleDebounced,
    )
    SegmentStore.addListener(
      SegmentConstants.ADD_SEARCH_RESULTS,
      this.addSearchParams,
    )
    SegmentStore.addListener(
      SegmentConstants.ADD_CURRENT_SEARCH,
      this.updateSearchParams,
    )
    SegmentStore.addListener(
      SegmentConstants.REMOVE_SEARCH_RESULTS,
      this.removeSearchParams,
    )
    SegmentStore.addListener(SegmentConstants.FOCUS_TAGS, this.focusTag)

    const textSpanDisplayed =
      this.tagRef && this.tagRef.querySelector('span[data-text="true"]')
    const shouldTooltipOnHover =
      textSpanDisplayed &&
      textSpanDisplayed.offsetWidth < textSpanDisplayed.scrollWidth
    this.setState({shouldTooltipOnHover})
  }

  shouldComponentUpdate() {
    return true
  }

  componentDidUpdate(prevProps) {
    if (prevProps.entitykey !== this.props.entityKey) {
      const textSpanDisplayed =
        this.tagRef && this.tagRef.querySelector('span[data-text="true"]')
      const shouldTooltipOnHover =
        textSpanDisplayed &&
        textSpanDisplayed.offsetWidth < textSpanDisplayed.scrollWidth
      if (shouldTooltipOnHover !== this.state.shouldTooltipOnHover) {
        this.setState({shouldTooltipOnHover})
      }
    }
  }

  componentWillUnmount() {
    SegmentStore.removeListener(
      SegmentConstants.SET_SEGMENT_WARNINGS,
      this.updateTagWarningStyleDebounced,
    )
    SegmentStore.removeListener(
      SegmentConstants.HIGHLIGHT_TAGS,
      this.highlightTags,
    )
    SegmentStore.removeListener(
      EditAreaConstants.EDIT_AREA_CHANGED,
      this.updateTagStyleDebounced,
    )
    SegmentStore.removeListener(
      SegmentConstants.ADD_SEARCH_RESULTS,
      this.addSearchParams,
    )
    SegmentStore.removeListener(
      SegmentConstants.ADD_CURRENT_SEARCH,
      this.updateSearchParams,
    )
    SegmentStore.removeListener(
      SegmentConstants.REMOVE_SEARCH_RESULTS,
      this.removeSearchParams,
    )
    SegmentStore.removeListener(SegmentConstants.FOCUS_TAGS, this.focusTag)
  }

  getChildrenContent(index) {
    return (
      <>
        {this.props.children}
        {index >= 0 && <span className="index-counter">{index + 1}</span>}
      </>
    )
  }

  render() {
    const {children, entityKey, contentState, getUpdatedSegmentInfo} =
      this.props
    const {
      tagStyle,
      tagWarningStyle,
      tooltipAvailable,
      showTooltip,
      shouldTooltipOnHover,
      searchParams,
      focused,
    } = this.state
    const {tooltipToggle, markSearch} = this
    const style =
      this.props.entityKey === this.state.entityKey
        ? tagStyle
        : this.selectCorrectStyle()
    const {openSplit} = getUpdatedSegmentInfo()
    const {
      data: {id: entityId, placeholder: entityPlaceholder, index},
    } = contentState.getEntity(entityKey)
    const decoratedText = Array.isArray(children)
      ? children[0].props.text
      : children.props.decoratedText

    return (
      <div className={'tag-container'}>
        <span
          ref={(ref) => (this.tagRef = ref)}
          className={`tag ${style}${
            focused ? ' tag-focused' : ''
          } ${tagWarningStyle}`}
          data-offset-key={this.props.offsetkey}
          unselectable="on"
          suppressContentEditableWarning={true}
          onMouseEnter={() => tooltipToggle(shouldTooltipOnHover)}
          onMouseLeave={() => tooltipToggle()}
          onClick={(e) => {
            e.stopPropagation()
            this.onClickBound(entityId, entityPlaceholder)
            !openSplit &&
              setTimeout(() => {
                SegmentActions.highlightTags(
                  entityId,
                  entityPlaceholder,
                  entityKey,
                )
              })
            this.focusedState.current = {
              skipTmOut: true,
            }
          }}
        >
          {tooltipAvailable && showTooltip && (
            <TooltipInfo text={entityPlaceholder} isTag tagStyle={style} />
          )}
          {searchParams.active && markSearch(decoratedText, searchParams)}
          {searchParams.active ? (
            <span style={{display: 'none'}}>
              {this.getChildrenContent(index)}
            </span>
          ) : (
            this.getChildrenContent(index)
          )}
        </span>
      </div>
    )
  }

  onClickBound = (entityId, entityPlaceholder) => {
    const {start, end, onClick: onClickAction} = this.props
    onClickAction(start, end, entityId, entityPlaceholder)
  }

  highlightTags = (tagId, tagPlaceholder, triggerEntityKey) => {
    const {entityKey, contentState} = this.props
    const {clicked} = this.state
    const {
      data: {id: entityId, placeholder: entityPlaceholder},
    } = contentState.getEntity(entityKey)
    // Turn OFF
    if (clicked && (!tagId || tagId !== entityId)) {
      this.setState({
        tagStyle: this.selectCorrectStyle(),
        clicked: false,
        entityKey,
      })
    } else if (entityKey === triggerEntityKey) {
      this.setState({
        tagStyle: this.selectCorrectStyle(tagId, tagPlaceholder, true),
        clicked: true,
        entityKey,
      })
    } else if (
      tagId === entityId &&
      entityPlaceholder === tagPlaceholder &&
      entityKey !== triggerEntityKey
    ) {
      this.setState({
        tagStyle: this.selectCorrectStyle(tagId, tagPlaceholder),
        clicked: true,
        entityKey,
      })
    }
  }

  updateTagStyle = (sid, isTarget) => {
    if (!this.props.isTarget && isTarget) return
    const {selectCorrectStyle} = this
    const newStyle = selectCorrectStyle()
    if (newStyle !== this.state.tagStyle) {
      this.setState({
        tagStyle: newStyle,
        entityKey: this.props.entityKey,
      })
    }
  }

  updateTagWarningStyle = () => {
    const {tagWarningStyle: prevTagWarningStyle} = this.state
    const tagWarningStyle = this.highlightOnWarnings()
    if (prevTagWarningStyle !== tagWarningStyle) {
      this.setState({tagWarningStyle})
    }
  }

  selectCorrectStyle = (clickedTagId = null, clickedTagText = null) => {
    const {entityKey, contentState, getUpdatedSegmentInfo, isRTL} = this.props
    const {segmentOpened} = getUpdatedSegmentInfo()
    const {
      data: {id: entityId, placeholder: entityPlaceholder, name: entityName},
    } = contentState.getEntity(entityKey)

    // Basic style accordin to language direction
    const baseStyle =
      tagSignatures[entityName] &&
      (isRTL && tagSignatures[entityName].styleRTL
        ? tagSignatures[entityName].styleRTL
        : tagSignatures[entityName].style)

    // Check if tag is in an active segment
    const tagInactive = !segmentOpened ? 'tag-inactive' : ''

    // Click
    const tagClicked =
      entityId &&
      clickedTagId &&
      clickedTagId === entityId &&
      clickedTagText &&
      clickedTagText === entityPlaceholder
        ? 'tag-clicked'
        : '' // green

    return `${baseStyle} ${tagInactive} ${tagClicked}`.trim()
  }

  focusTag = ({tagsSelected}) => {
    const {skipTmOut, tmOut} = this.focusedState.current
    if (tmOut) clearTimeout(tmOut)
    this.focusedState.current = {}

    if (!tagsSelected?.length) {
      // reset
      this.setState({
        focused: false,
      })
      return
    }

    const updateState = () => {
      this.setState({
        focused: tagsSelected.some(
          ({entityKey}) => entityKey === this.props.entityKey,
        ),
      })
    }

    if (!skipTmOut) {
      this.focusedState.current.tmOut = setTimeout(() => updateState(), 100)
    } else {
      updateState()
    }
  }

  highlightOnWarnings = () => {
    const {getUpdatedSegmentInfo, contentState, entityKey, isTarget} =
      this.props
    const {tagMismatch, segmentOpened} = getUpdatedSegmentInfo()
    const {data: entityData} = contentState.getEntity(entityKey) || {}

    if (!segmentOpened || !tagMismatch) return
    let tagWarningStyle = ''
    if (tagMismatch.target && tagMismatch.target.length > 0 && isTarget) {
      // Todo: Check tag type and tag id instead of string
      tagMismatch.target.forEach((tagString) => {
        if (entityData.encodedText === tagString) {
          tagWarningStyle = 'tag-mismatch-error'
        }
      })
    } else if (
      tagMismatch.source &&
      tagMismatch.source.length > 0 &&
      !isTarget
    ) {
      tagMismatch.source.forEach((tagString) => {
        if (entityData.encodedText === tagString) {
          tagWarningStyle = 'tag-mismatch-error'
        }
      })
    } else if (tagMismatch.order && isTarget) {
      tagMismatch.order.forEach((tagString) => {
        if (entityData.encodedText === tagString) {
          tagWarningStyle = 'tag-mismatch-warning'
        }
      })
    }
    return tagWarningStyle
  }
}

export default TagEntity
