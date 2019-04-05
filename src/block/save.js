/**
 * BLOCK SAVE: WSJ Video Block Plugin
 *
 * Define WSJ Video block save
 */

/**
 * External dependencies
 */
import PropTypes from 'prop-types';
import { getShortcode } from './utils';

const StocksSave = (props) => {
  const {
    attributes: {
      symbol,
      name,
    },
  } = props;

  let symbol_val = symbol;

  if(undefined != symbol_val) {
    symbol_val = symbol_val.replace("$", "");
    symbol_val = symbol_val.replace("<p>", "");
    symbol_val = symbol_val.replace("</p>", "");
  }

  return (
    <div>
      {
        getShortcode(
          `[stocks-info
          symbol="${symbol_val}"
          name="${name}"
          ]`
        )
      }
    </div>
  );
};

StocksSave.propTypes = {
  attributes: PropTypes.object,
};

StocksSave.defaultProps = {
  attributes: {},
};

export default StocksSave;
