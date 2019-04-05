import { csvParse, tsvParse } from 'd3-dsv';
import { timeParse } from 'd3-time-format';
import ChartComponent from './ChartComponent';

let config_data = JSON.stringify(stockinfo_config);
let config_content = JSON.parse(config_data);

// Read config settings
const apiKey = config_content.token;
const host = config_content.host;
const entrypoint = config_content.entrypoint;

function parseData (parse) {
  return function (d) {
    if('' != d.timestamp && undefined != d.timestamp && null != d.timestamp){
      d.date = parse(d.timestamp);
    }
    else {
      d.date = parse(d.date);
    }
    d.open = +d.open;
    d.high = +d.high;
    d.low = +d.low;
    d.close = +d.close;
    d.volume = +d.volume;

    return d;
  };
}

// @TODO: to parse other APIs you can re-purpose this block:
// function parseStocks(obj) {
//   obj.map((fields, key) => {
//     d.date = key;
//     d.open = fields.open;
//     d.high = fields.high;
//     d.low = fields.low;
//     d.close = fields.close;
//     d.volume = fields.volume;
//   });
//
//   return d;
// }

const parseDate = timeParse('%Y-%m-%d');

export function getData (symbol) {

  // @TODO: time series intraday.
  // const type = 'TIME_SERIES_INTRADAY';
  const type = 'TIME_SERIES_DAILY';

  // @TODO: for Time Series intraday.
  // const interval = '&interval=30min';
  const interval = '';

  const encodedSymbol = encodeURIComponent(symbol).replace(/\./g, '%2E');

  let url = `${host}/${entrypoint}?function=${type}&symbol=${encodedSymbol}&apikey=${apiKey}&datatype=csv${interval}`;

  // const encodedUrl = encodeURI(url).replace(/\./g, '%2E');
  console.log(url);

  let promiseStock = null;
  let promiseData = null;
  let result = null;

  const api_limit = 'Our standard API call frequency is 5 calls per minute and 500 calls per day.';

  try {
    promiseStock = fetch(url,
      {
        crossDomain: true,
        method: 'GET',
        headers: { 'Content-Type': 'application/json' },
      }
    )
      .then(response => response.text());

    promiseData = promiseStock.then(x => (x.toString().includes(api_limit))? null : x );

    if(null != promiseData) {
      result = promiseStock.then(data => csvParse(data, parseData(parseDate)))
        .then(result => result.reverse());
    }
  }
  catch(ex){
    console.log("Error using API.");
    return null;
  }

  if(promiseStock.status == 'pending'){
    console.log("Error using API.");
    return null;
  }


  console.log(result);

  return result;
}


/**
 * bindEvent
 * @param {*} element
 * @param {*} eventName
 * @param {*} eventHandler
 */
export const bindEvent = (element, eventName, eventHandler, options = {}) => {
  if (element.addEventListener) {
    return element.addEventListener(eventName, eventHandler, options);
  } else if (element.attachEvent) {
    return element.attachEvent(`on${eventName}`, eventHandler, options);
  }
  return false;
};

/**
 * getParsedData
 * @param {String} data
 */
export const getParsedData = (data) => {
  if ('string' === typeof data && '' !== data) {
    try {
      return JSON.parse(data);
    } catch (e) {
      console.error(e); /* eslint-disable-line no-console */
    }
  }
  return {};
};

const regex = /stocks-info symbol=(.*)/g;
let matches;

const getSymbolValue = (text) => {
  let matches = regex.exec(text);
  return matches[1];
};

/**
 * getShortcode
 * @param {*} text
 */
export const getShortcode = (text) => {
  // matches = regex.exec(text)
  // //
  // // // let matches = text.match(/stocks-info symbol=(.*)"/i);
  // console.log(matches);
  // let symbol = matches[1];
  // symbol = symbol.replace('"','');
  // symbol = symbol.replace(']','');

  // wp.element.createElement(
  //   <div>
  //     {symbol}
  //     <ChartComponent apiKey={symbol} symbol={symbol} data={getData(symbol)}/>
  //   </div>
  // );

  wp.element.createElement(
    'wp-raw-html',
    { dangerouslySetInnerHTML: { __html: text } }
  );
};
