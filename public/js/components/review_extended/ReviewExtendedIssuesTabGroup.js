import React, {useEffect, useMemo, useState} from 'react'
import PropTypes from 'prop-types'
import classnames from 'classnames'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'

const ReviewExtendedIssuesTabGroup = ({tabs, selectedTabId}) => {
  const [selectedTab, setSelectedTab] = useState(
    tabs.find(({id}) => id === selectedTabId) ?? tabs[0],
  )

  const onChangeTab = useMemo(
    () => (id) => setSelectedTab(tabs.find((tab) => tab.id === id)),
    [tabs],
  )

  useEffect(() => {
    const issueAdded = () =>
      onChangeTab(
        config.secondRevisionsCount && config.secondRevisionsCount > 0
          ? 'r2'
          : 'r1',
      )
    SegmentStore.addListener(SegmentConstants.ISSUE_ADDED, issueAdded)

    return () => {
      SegmentStore.removeListener(SegmentConstants.ISSUE_ADDED, issueAdded)
    }
  }, [onChangeTab])

  return (
    <div className="review-extended-issues-tab-group">
      <div className="tabs-container">
        {tabs.map((tab) => (
          <a
            key={tab.id}
            className={classnames(
              'tab',
              tab.id === selectedTab?.id && 'active',
              tab.disabled && 'disabled',
            )}
            onClick={() => onChangeTab(tab.id)}
          >
            {tab.label}
          </a>
        ))}
      </div>
      <div className="tab-content">
        {tabs.find(({id}) => id === selectedTab?.id)?.content}
      </div>
    </div>
  )
}

ReviewExtendedIssuesTabGroup.propTypes = {
  tabs: PropTypes.arrayOf(
    PropTypes.shape({
      id: PropTypes.string,
      label: PropTypes.string,
      content: PropTypes.node,
      disabled: PropTypes.bool,
    }),
  ).isRequired,
  selectedTabId: PropTypes.string,
}

export default ReviewExtendedIssuesTabGroup
