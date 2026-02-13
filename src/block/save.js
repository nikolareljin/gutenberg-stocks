/**
 * BLOCK SAVE: WSJ Video Block Plugin
 *
 * Define WSJ Video block save
 */

/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';

import PropTypes from 'prop-types';
import {getShortcode, getData, getParsedData} from './utils';
import ChartComponent from './ChartComponent';

let config_data = JSON.stringify(stockinfo_config);
let config_content = JSON.parse(config_data);

console.log('Content from Save');
console.log(config_content);

// Read config settings
const apiKey = config_content.token;

const StocksSave = (props) => {

  console.log("Stocks Save props:");
  console.log(props);

  const {
    attributes: {
      symbol,
      name,
    },
  } = props;

  let symbol_val = symbol;

  const getSavedData = (symbol) => {
    return getData(symbol);
  };

  if(undefined != symbol_val) {
    symbol_val = symbol_val.replace("$", "");
    symbol_val = symbol_val.replace("<p>", "");
    symbol_val = symbol_val.replace("</p>", "");
  }

  // return (
  //   <div>
  //     <ChartComponent symbol={symbol_val} apiKey={apiKey}/>
  //   </div>
  // );

  return(
    <div className="stock-information-item" data-symbol={symbol_val}>
      {
        getShortcode(
          `[stocks-info symbol="${symbol_val}"]`
        )
      }
    </div>
  );

  // return(
  //   ReactDOM.render(
  //     <ChartComponent symbol={symbol_val} apiKey={apiKey}/>, // call canvas and pass the symbol.
  //     this // need to specify the element to render on
  //   )
  // )
};

StocksSave.propTypes = {
  attributes: PropTypes.object,
};

StocksSave.defaultProps = {
  attributes: {},
};

export default StocksSave;
