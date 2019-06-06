import React from 'react';
import PropTypes from 'prop-types';
import Chart from './Chart';
import { getData } from './utils';
import { fitWidth } from 'react-stockcharts/lib/helper';
const { Component } = wp.element;

let config_data = JSON.stringify(stockinfo_config);
let config_content = JSON.parse(config_data);
let apiKey = config_content.apiKey;

class ChartComponent extends Component {

  constructor (props) {
    super(...arguments);
    super(props);

    console.log("PROPS @ CHART");
    console.log(props);

    if(props.symbol && props.apiKey) {
      this.getDataFromUtils(props.apiKey, props.symbol);
    }

    if (props.symbol) {
      this.setState({ symbol: props.symbol });
    }
    if (props.apiKey) {
      this.setState({ apiKey: props.apiKey });
    }
  }

  componentDidMount(props){
    console.log("Chart Component componentDidMount - PROPS")
    console.log(props);
  }

  /**
   * Sets data values for the given Symbol and ApiKey.
   * @param apiKey
   * @param symbol
   */
  getDataFromUtils (apiKey, symbol) {
    let oldData = (undefined != this.state && undefined != this.state.data)? this.state.data : null;
    getData(symbol).then(data => {
      if(data) {
        this.setState({ data, apiKey, symbol, render: true });
      }
      else if(oldData){
        // Backup: if state was not retrieved.
        this.setState({data: oldData, render: true});
      }
      else{
        this.setState({data: null, render: false});
      }
    });
  }

  componentDidMount () {
    setTimeout(function() { //Start the timer
      this.setState({render: true}) //After 2 seconds, set render to true
    }.bind(this), 2000);
  }

  /**
   * Render component.
   * @param props
   * @returns {*}
   */
  render () {
      if ( null === this.state || null === this.state.data || '' === this.state.symbol || undefined === this.state.symbol || this.state.symbol.includes('$')) {
        return <div>Loading...</div>;
      }
      return (
        <Chart type={'hybrid'} data={this.state.data} name={this.state.name} symbol={this.state.symbol}/>
      );
  }
}

ChartComponent.propTypes = {
  data: PropTypes.array,
  width: PropTypes.number.isRequired,
  ratio: PropTypes.number.isRequired,
  type: PropTypes.oneOf(['svg', 'hybrid']).isRequired,
  symbol: PropTypes.string,
  apiKey: PropTypes.string.isRequired,
};

ChartComponent.defaultProps = {
  type: 'hybrid',
  width: 600,
  height: 480,
  symbol: '',
  apiKey: '',
};
ChartComponent = fitWidth(ChartComponent);

export default ChartComponent;
