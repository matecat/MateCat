import Header from '../header/Header'
import CattoolFooter from './CattoolFooter'

class Footer extends React.Component {
  constructor(props) {
    super(props)
  }

  render = () => {
    if (this.props.cattool) {
      return <CattoolFooter {...this.props} />
    } else return ''
  }
}

Header.defaultProps = {
  cattool: false,
}

export default Footer
