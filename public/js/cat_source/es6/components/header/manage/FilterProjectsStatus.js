import React from 'react'

import IconFilter from '../../icons/IconFilter'
import IconTick from '../../icons/IconTick'

class FilterProjectsStatus extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      status: ['active', 'archived', 'cancelled'],
      currentStatus: 'active',
    }

    this.onChangeFunction = this.onChangeFunction.bind(this)
  }

  componentDidMount() {
    let self = this

    const {currentStatus} = this.state

    $(this.dropdown).dropdown({
      onChange: function () {
        self.onChangeFunction()
      },
    })
    this.currentFilter = currentStatus
    $(this.dropdown).dropdown('set selected', currentStatus)
  }

  onChangeFunction() {
    if (this.currentFilter !== $(this.dropdown).dropdown('get value')) {
      this.props.filterFunction($(this.dropdown).dropdown('get value'))
      this.currentFilter = $(this.dropdown).dropdown('get value')

      this.setState({
        currentStatus: this.currentFilter,
      })
    }
  }

  componentDidUpdate() {}

  render = () => {
    const {status} = this.state

    return (
      <div
        className="ui top left pointing dropdown"
        title="Status Filter"
        ref={(dropdown) => (this.dropdown = dropdown)}
        data-testid="status-filter"
      >
        <IconFilter width={36} height={36} color={'#002b5c'} />
        <div style={{textTransform: 'capitalize'}} className="text">
          Active
        </div>
        <div className="menu">
          {status.map((e, i) => (
            <div
              style={{textTransform: 'capitalize'}}
              key={i}
              className="item"
              data-value={e}
              data-testid={`item-${e}`}
            >
              {e}{' '}
              {e === this.currentFilter ? (
                <IconTick width={14} height={14} color={'#ffffff'} />
              ) : null}
            </div>
          ))}
        </div>
      </div>
    )
  }
}

export default FilterProjectsStatus
