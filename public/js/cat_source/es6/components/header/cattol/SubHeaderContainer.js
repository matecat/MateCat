import React from 'react'
import $ from 'jquery'
import _ from 'lodash'

import CatToolConstants from '../../../constants/CatToolConstants'
import CatToolStore from '../../../stores/CatToolStore'
import {BulkSelectionBar} from './bulk_selection_bar/BulkSelectionBar'
import SegmentsFilter from './segment_filter/SegmentsFilter'
import Search from './search/Search'
import QaComponent from './QAComponent'
import SegmentConstants from '../../../constants/SegmentConstants'
import SegmentStore from '../../../stores/SegmentStore'

class SubHeaderContainer extends React.Component {
  state = {
    selectionBar: true,
    search: false,
    segmentFilter: false,
    qaComponent: false,
    totalWarnings: 0,
    warnings: {
      ERROR: {
        Categories: {},
        total: 0,
      },
      WARNING: {
        Categories: {},
        total: 0,
      },
      INFO: {
        Categories: {},
        total: 0,
      },
    },
  }

  showContainer = (container) => {
    switch (container) {
      case 'search':
        this.setState({
          search: true,
          segmentFilter: false,
          qaComponent: false,
        })
        break
      case 'segmentFilter':
        this.setState({
          search: false,
          segmentFilter: true,
          qaComponent: false,
        })
        break
      case 'qaComponent':
        this.setState({
          search: false,
          segmentFilter: false,
          qaComponent: true,
        })
        break
    }
  }
  toggleContainer = (container) => {
    switch (container) {
      case 'search':
        this.setState({
          search: !this.state.search,
          segmentFilter: false,
          qaComponent: false,
        })
        break
      case 'segmentFilter':
        this.setState({
          search: false,
          segmentFilter: !this.state.segmentFilter,
          qaComponent: false,
        })
        break
      case 'qaComponent':
        this.setState({
          search: false,
          segmentFilter: false,
          qaComponent: !this.state.qaComponent,
        })
        break
    }
  }
  updateIcon = (total, warnings) => {
    if (total > 0) {
      if (warnings.ERROR.total > 0) {
        $('#notifbox')
          .attr('class', 'warningbox action-submenu')
          .attr('title', 'Click to see the segments with potential issues')
          .find('.numbererror')
          .text(total)
          .removeClass('numberwarning numberinfo action-submenu')
      } else if (warnings.WARNING.total > 0) {
        $('#notifbox')
          .attr('class', 'warningbox')
          .attr('title', 'Click to see the segments with potential issues')
          .find('.numbererror')
          .text(total)
          .addClass('numberwarning')
          .removeClass('numberinfo')
      } else {
        $('#notifbox')
          .attr('class', 'warningbox action-submenu')
          .attr('title', 'Click to see the segments with potential issues')
          .find('.numbererror')
          .text(total)
          .addClass('numberinfo')
          .removeClass('numberwarning')
      }
    } else {
      $('#notifbox')
        .attr('class', 'notific disabled action-submenu')
        .attr('title', 'Well done, no errors found!')
        .find('.numbererror')
        .text('')
    }
  }
  closeSubHeader = () => {
    this.setState({
      search: false,
      segmentFilter: false,
      qaComponent: false,
    })
  }

  receiveGlobalWarnings = (warnings) => {
    let totalWarnings = []
    if (warnings.lexiqa && warnings.lexiqa.length > 0) {
      warnings.matecat.INFO.Categories['lexiqa'] = _.uniq(warnings.lexiqa)
    }
    Object.keys(warnings.matecat).map((key) => {
      let totalCategoryWarnings = []
      Object.keys(warnings.matecat[key].Categories).map((key2) => {
        totalCategoryWarnings.push(...warnings.matecat[key].Categories[key2])
        totalWarnings.push(...warnings.matecat[key].Categories[key2])
      })
      warnings.matecat[key].total = totalCategoryWarnings.filter(
        (value, index, self) => {
          return self.indexOf(value) === index
        },
      ).length
    })
    let tot = totalWarnings.filter((value, index, self) => {
      return self.indexOf(value) === index
    }).length
    this.updateIcon(tot, warnings.matecat)
    this.setState({
      warnings: warnings.matecat,
      totalWarnings: tot,
    })
  }

  componentDidMount() {
    CatToolStore.addListener(
      CatToolConstants.SHOW_CONTAINER,
      this.showContainer,
    )
    CatToolStore.addListener(
      CatToolConstants.TOGGLE_CONTAINER,
      this.toggleContainer,
    )
    CatToolStore.addListener(
      CatToolConstants.CLOSE_SUBHEADER,
      this.closeSubHeader,
    )
    SegmentStore.addListener(
      SegmentConstants.UPDATE_GLOBAL_WARNINGS,
      this.receiveGlobalWarnings,
    )
  }

  componentWillUnmount() {
    CatToolStore.removeListener(
      CatToolConstants.SHOW_CONTAINER,
      this.showContainer,
    )
    CatToolStore.removeListener(
      CatToolConstants.TOGGLE_CONTAINER,
      this.toggleContainer,
    )
    CatToolStore.removeListener(
      CatToolConstants.CLOSE_SUBHEADER,
      this.closeSubHeader,
    )
    SegmentStore.removeListener(
      SegmentConstants.UPDATE_GLOBAL_WARNINGS,
      this.receiveGlobalWarnings,
    )
  }

  render() {
    const {filtersEnabled} = this.props
    const {search, segmentFilter, qaComponent, warnings, totalWarnings} =
      this.state

    return (
      <div>
        <Search
          active={search}
          isReview={config.isReview}
          searchable_statuses={config.searchable_statuses}
        />

        {filtersEnabled ? (
          <SegmentsFilter active={segmentFilter} isReview={config.isReview} />
        ) : null}

        <QaComponent
          active={qaComponent}
          isReview={config.isReview}
          warnings={warnings}
          totalWarnings={totalWarnings}
        />

        <BulkSelectionBar isReview={config.isReview} />
      </div>
    )
  }
}

export default SubHeaderContainer
