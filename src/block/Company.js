import React, { Fragment } from 'react';
import PropTypes from 'prop-types';
import ChartComponent from './block';

const Company = (
  {
    symbol = '',
    name = '',
    url = '',
    className = 'company',
    title = 'Company',
    apiKey
  }
) => (
  <Fragment>
    <ChartComponent type={'hybrid'} symbol={symbol} apiKey={apiKey}/>
    <span id={symbol} title={title} className={className}>
      {symbol} ({name})
    </span>
    &nbsp;
  </Fragment>
);

Company.propTypes = {
  symbol: PropTypes.string,
  apiKey: PropTypes.string,
  name: PropTypes.string,
  className: PropTypes.string,
};

Company.defaultProps = {
  title: 'Company',
  apiKey: '',
  symbol: '',
  name: '',
  className: '',
};

export default Company;
